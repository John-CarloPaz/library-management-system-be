<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $validated = $request->validate([
            'acquisition_id' => 'required|exists:acquisitions,id',
            'number_of_copies' => 'required|integer|min:1',
            'dewey' => 'nullable|string',
            'cutter_number' => 'nullable|string',
            'call_number' => 'nullable|string',
            'title' => 'required|string',
            'author' => 'required|string',
            'edition' => 'nullable|string',
            'isbn' => 'nullable|string',
            'publisher' => 'nullable|string',
            'place_of_publication' => 'nullable|string',
            'year_of_publication' => 'nullable|integer',
            'cataloging_status' => 'required|in:pending,in_progress,cataloged,ready_for_labeling,available,on_hold,archived',
        ]);

        $isProvisional = $this->checkProvisional($validated);

        $catalogue = Catalogue::create(array_merge($validated, [
            'is_provisional' => $isProvisional,
            'created_by' => $user->username,
            'updated_by' => $user->username,
        ]));

        if ($validated['cataloging_status'] === 'available' && !$isProvisional) {
            $this->generateBookCopiesAsync($catalogue);
        }

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

        $validated = $request->validate([
            'dewey' => 'nullable|string',
            'cutter_number' => 'nullable|string',
            'call_number' => 'nullable|string',
            'title' => 'required|string',
            'author' => 'required|string',
            'edition' => 'nullable|string',
            'isbn' => 'nullable|string',
            'publisher' => 'nullable|string',
            'place_of_publication' => 'nullable|string',
            'year_of_publication' => 'nullable|integer',
            'number_of_copies' => 'required|integer|min:1',
            'cataloging_status' => 'required|in:pending,in_progress,cataloged,ready_for_labeling,available,on_hold,archived',
        ]);

        $catalogue = Catalogue::findOrFail($id);

        $isProvisional = $this->checkProvisional($validated);

        $catalogue->update(array_merge($validated, [
            'is_provisional' => $isProvisional,
            'updated_by' => $user->username,
        ]));

        if ($validated['cataloging_status'] === 'available' && !$isProvisional) {
            $this->generateBookCopiesAsync($catalogue);
        }

        return response()->json([
            'message' => 'Catalogue updated successfully.',
            'catalogue' => $catalogue,
        ]);
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
        dispatch(function () use ($catalogue) {
            for ($i = 1; $i <= $catalogue->number_of_copies; $i++) {
                $referenceNumber = "{$catalogue->dewey}-{$catalogue->call_number}-{$i}";
                $qrCodeImage = QrCode::format('png')->size(300)->generate($referenceNumber);
                $qrCodePath = "qr_codes/catalogue_{$catalogue->id}_copy_{$i}.png";

                Storage::disk('public')->put($qrCodePath, $qrCodeImage);

                Book::create([
                    'catalogue_id' => $catalogue->id,
                    'copy_number' => $i,
                    'reference_number' => $referenceNumber,
                    'qr_code' => $qrCodePath,
                ]);
            }
        });
    }
}
