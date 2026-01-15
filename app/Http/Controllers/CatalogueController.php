<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Models\Catalogue;
use App\Models\Book;
use App\Services\ListQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CatalogueController extends Controller
{
    public function __construct(private ListQueryService $lists)
    {
    }
    /**
     * Add a new catalogue.
     */
    public function addCatalogue(Request $request)
    {
        $user = Auth::user();
        $validated = $this->validateCatalogue($request);
        $isProvisional = $this->checkProvisional($validated);

        if ($isProvisional) {
            return response()->json([
                'message' => 'Cannot create catalogue. All required fields must be filled.',
            ], 422);
        }

        $catalogue = Catalogue::create(array_merge($validated, [
            'is_provisional' => $isProvisional,
            'created_by' => $user->username,
            'updated_by' => $user->username,
            'is_archived' => false,
        ]));

        if ($validated['cataloging_status'] === 'available') {
            if ($this->catalogueHasBooks($catalogue)) {
                return response()->json([
                    'message' => 'Books already exist for this catalogue.',
                ], 422);
            }

            $this->generateBookCopiesAsync($catalogue);
        }

        GenericActionEvent::dispatch([
            'resource_type' => 'catalogue',
            'action' => 'create',
            'resource_id' => $catalogue->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Catalogue created successfully.',
            'catalogue' => $catalogue,
        ], 201);
    }

    /**
     * Edit an existing catalogue.
     */
    public function editCatalogue(Request $request, $id)
    {
        $user = Auth::user();
        $catalogue = Catalogue::findOrFail($id);
        $validated = $this->validateCatalogue($request, true);
        $isProvisional = $this->checkProvisional(array_merge($catalogue->toArray(), $validated));
        $previousStatus = $catalogue->getOriginal('cataloging_status');

        $catalogue->update(array_merge($validated, [
            'is_provisional' => $isProvisional,
            'updated_by' => $user->username,
        ]));

        if (
            isset($validated['cataloging_status']) &&
            $validated['cataloging_status'] === 'available' &&
            $previousStatus !== 'available' &&
            !$isProvisional
        ) {
            if ($this->catalogueHasBooks($catalogue)) {
                return response()->json([
                    'message' => 'Books were already generated for this catalogue.',
                ], 422);
            }

            $this->generateBookCopiesAsync($catalogue);
        }


        GenericActionEvent::dispatch([
            'resource_type' => 'catalogue',
            'action' => 'update',
            'resource_id' => $catalogue->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Catalogue updated successfully.',
            'catalogue' => $catalogue,
        ]);
    }

    /**
     * Bulk add catalogues from a CSV file.
     * Expects a multipart/form-data upload with field name "file".
     */
    public function bulkAddFromCsv(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();
        $rows = array_map('str_getcsv', file($path));

        if (empty($rows) || count($rows) < 2) {
            return response()->json([
                'message' => 'CSV file is empty or missing data rows.',
            ], 400);
        }

        $header = array_map('trim', array_shift($rows));

        $created = [];
        $errors = [];
        $rowNumber = 1; // data rows start after header

        foreach ($rows as $row) {
            $rowNumber++;

            // Skip completely empty rows
            if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            if (count($row) !== count($header)) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => ['Column count does not match header.'],
                ];
                continue;
            }

            $data = array_combine($header, $row);

            try {
                $rules = $this->getCatalogueValidationRules($data, false);
                $validator = Validator::make($data, $rules);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $validator->errors()->all(),
                    ];
                    continue;
                }

                $validated = $validator->validated();
                $isProvisional = $this->checkProvisional($validated);

                if ($isProvisional) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['Cannot create catalogue. All required fields must be filled.'],
                    ];
                    continue;
                }

                $catalogue = Catalogue::create(array_merge($validated, [
                    'is_provisional' => $isProvisional,
                    'created_by' => $user->username,
                    'updated_by' => $user->username,
                    'is_archived' => false,
                ]));

                if ($validated['cataloging_status'] === 'available') {
                    if ($this->catalogueHasBooks($catalogue)) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'errors' => ['Books already exist for this catalogue.'],
                        ];
                    } else {
                        $this->generateBookCopiesAsync($catalogue);
                    }
                }

                GenericActionEvent::dispatch([
                    'resource_type' => 'catalogue',
                    'action' => 'create',
                    'resource_id' => $catalogue->id,
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->username,
                    'timestamp' => now(),
                ]);

                $created[] = $catalogue;
            } catch (\Throwable $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => [$e->getMessage()],
                ];
            }
        }

        return response()->json([
            'message' => 'Bulk catalogue import completed.',
            'created_count' => count($created),
            'error_count' => count($errors),
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    /**
     * View a specific catalogue.
     */
    public function viewCatalogue($id)
    {
        $catalogue = Catalogue::with('acquisition', 'books', 'branch')->findOrFail($id);

        return response()->json([
            'catalogue' => $catalogue,
        ]);
    }

    /**
     * List all catalogues.
     */
    public function listCatalogues(Request $request)
    {
        $catalogues = $this->lists->build(
            $request,
            Catalogue::with('acquisition', 'branch'),
            [
                'status_field' => 'cataloging_status',
                'archived_field' => 'cataloging_status',
                'archived_flag' => 'archived',
                'branch_field' => 'branch_id',
                'search_fields' => ['title', 'author', 'isbn'],
            ]
        );

        return response()->json([
            'catalogues' => $catalogues,
        ]);
    }

    /**
     * List active (non-archived) catalogues.
     */
    public function listActiveCatalogues(Request $request)
    {
        $catalogues = $this->lists->build(
            $request,
            Catalogue::with('acquisition', 'branch')->where('cataloging_status', '!=', 'archived'),
            [
                'status_field' => 'cataloging_status',
                'archived_field' => 'cataloging_status',
                'archived_flag' => 'archived',
            ]
        );

        return response()->json([
            'catalogues' => $catalogues,
        ]);
    }

    /**
     * Archive a catalogue.
     */
    public function archiveCatalogue($id)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $catalogue = Catalogue::findOrFail($id);
        // Business rule checks
        // Cannot archive when any copy is currently borrowed
        $hasBorrowedBooks = $catalogue->books()->where('is_borrowed', true)->exists();
        $hasActiveOrUnderRepairBooks = $catalogue->books()
            ->whereIn('book_status', ['active', 'under_repair'])
            ->exists();
        $hasPenalizedBorrows = $catalogue->borrows()->where('is_penalized', true)->exists();
        $hasUnpaidFines = $catalogue->borrows()->where('is_fine_paid', false)->exists();
        // Cannot archive when there are borrowed books, penalized borrows, unpaid fines,
        // or when the catalogue is available and has books that are active or under repair.
        if (
            $hasBorrowedBooks ||
            $hasPenalizedBorrows ||
            $hasUnpaidFines ||
            ($catalogue->cataloging_status === 'available' && $hasActiveOrUnderRepairBooks)
        ) {
            return response()->json([
                'message' => 'Cannot archive catalogue due to active/under-repair books or outstanding borrows/fines.',
            ], 400);
        }

        $catalogue->update([
            'cataloging_status' => 'archived',
            'updated_by' => $user->username,
        ]);

        $catalogue->books()->update([
            'is_archived' => true,
            'updated_by' => $user->username,
        ]);


        GenericActionEvent::dispatch([
            'resource_type' => 'catalogue',
            'action' => 'archive',
            'resource_id' => $catalogue->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Catalogue and its books archived successfully.',
            'catalogue' => $catalogue,
        ]);
    }

    public function restoreCatalogue($id)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $catalogue = Catalogue::findOrFail($id);
        $catalogue->update([
            // When restoring, catalogue should go back to pending
            // so it can re-enter the cataloging workflow, not
            // jump straight to available.
            'cataloging_status' => 'pending',
            'updated_by' => $user->username,
        ]);

        $catalogue->books()->update([
            'is_archived' => false,
            'updated_by' => $user->username,
        ]);


        GenericActionEvent::dispatch([
            'resource_type' => 'catalogue',
            'action' => 'restore',
            'resource_id' => $catalogue->id,
            'user_id' => $user->id,
            'user_name' => $user->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Catalogue and its books restored successfully.',
            'catalogue' => $catalogue,
        ]);
    }


    /**
     * Validate catalogue request data.
     */
    private function validateCatalogue(Request $request, $isEdit = false): array
    {
        $rules = $this->getCatalogueValidationRules($request->all(), $isEdit);

        return Validator::make($request->all(), $rules)->validate();
    }

    /**
     * Get validation rules for catalogue data.
     */
    private function getCatalogueValidationRules(array $data, bool $isEdit = false): array
    {
        $rules = [
            'number_of_copies' => 'required|integer|min:1',
            'dewey' => 'required|string',
            'cutter_number' => 'required|string',
            'call_number' => 'required|string',
            'title' => 'required|string',
            'author' => 'required|string',
            'edition' => 'required|string',
            'isbn' => 'required|string',
            'publisher' => 'required|string',
            'place_of_publication' => 'required|string',
            'year_of_publication' => 'required|integer',
            'branch_id' => 'required|exists:branches,id',
            'cataloging_status' => 'required|in:pending,in_progress,cataloged,ready_for_labeling,available,on_hold,archived',
        ];

        if ($isEdit) {
            $rules = array_intersect_key($rules, $data);
        }

        return $rules;
    }

    private function catalogueHasBooks(Catalogue $catalogue): bool
    {
        return $catalogue->books()->exists();
    }

    /**
     * Check if the catalogue is provisional.
     */
    private function checkProvisional(array $data): bool
    {
        $requiredFields = ['dewey', 'cutter_number', 'call_number', 'title', 'author', 'edition', 'isbn', 'publisher', 'place_of_publication', 'year_of_publication'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate book copies asynchronously.
     */
    private function generateBookCopiesAsync(Catalogue $catalogue)
    {
        if ($catalogue->is_provisional) {
            return; // Do not generate books if the catalogue is provisional
        }

        $catalogueId = $catalogue->id;
        $branchId = $catalogue->branch_id;
        $createdBy = $catalogue->created_by;
        $copies = $catalogue->number_of_copies;
        $dewey = $catalogue->dewey;
        $callNumber = $catalogue->call_number;

        dispatch(function () use (
            $catalogueId,
            $branchId,
            $createdBy,
            $copies,
            $dewey,
            $callNumber

        ) {
            for ($i = 1; $i <= $copies; $i++) {
                $referenceNumber = "{$dewey}-{$callNumber}-{$i}";
                $qrCodeImage = QrCode::format('png')->size(300)->generate($referenceNumber);
                $qrCodePath = "qr_codes/catalogue_{$catalogueId}_copy_{$i}.png";
                Storage::disk('public')->put($qrCodePath, $qrCodeImage);
                Book::create([
                    'catalogue_id' => $catalogueId,
                    'copy_number' => $i,
                    'reference_number' => $referenceNumber,
                    'qr_code' => $qrCodePath,
                    'branch_id' => $branchId,
                    'is_archived' => false,
                    'created_by' => $createdBy,
                    'updated_by' => $createdBy,
                    'expiration_date' => now()->addYears(3),
                ]);
            }
        });

    }
}
