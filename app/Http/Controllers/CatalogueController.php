<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Models\Catalogue;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CatalogueController extends Controller
{
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
    public function listCatalogues()
    {
        $catalogues = Catalogue::with('acquisition', 'branch')->get();

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

        $hasActiveBooks = $catalogue->books()->where('book_status', 'borrowed')->exists();
        $hasPenalizedBorrows = $catalogue->borrows()->where('is_penalized', true)->exists();
        $hasUnpaidFines = $catalogue->borrows()->where('is_fine_paid', false)->exists();

        if ($hasActiveBooks || $hasPenalizedBorrows || $hasUnpaidFines) {
            return response()->json(['message' => 'Cannot archive catalogue. Some books are active.'], 400);
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

    public function listArchivedCatalogues()
    {
        $catalogues = Catalogue::where('cataloging_status', 'archived')->with('acquisition', 'branch')->get();

        return response()->json([
            'archived_catalogues' => $catalogues,
        ]);
    }

    public function listActiveCatalogues()
    {
        $catalogues = Catalogue::where('cataloging_status', '!=', 'archived')->with('acquisition', 'branch')->get();

        return response()->json([
            'active_catalogues' => $catalogues,
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
            'cataloging_status' => 'available',
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
            $rules = array_intersect_key($rules, $request->all());
        }

        return $request->validate($rules);
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
