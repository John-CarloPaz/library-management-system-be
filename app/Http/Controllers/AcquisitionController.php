<?php

namespace App\Http\Controllers;

use App\Models\Acquisition;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AcquisitionController extends Controller
{
    /**
     * Create a new acquisition (super_admin only).
     */
    public function createAcquisition(Request $request)
    {
        $user = Auth::user();

        if (!$this->canManageAcquisitions($user)) {
            return $this->accessDeniedResponse();
        }

        $validated = $this->validateAcquisition($request);

        $acquisition = Acquisition::create(array_merge($validated, [
            'created_by' => $user->username,
            'updated_by' => $user->username,
        ]));

        return response()->json([
            'message' => 'Acquisition created successfully.',
            'acquisition' => $acquisition,
        ], 201);
    }

    /**
     * Edit an acquisition (super_admin only).
     */
    public function editAcquisition(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->canManageAcquisitions($user)) {
            return $this->accessDeniedResponse();
        }

        $acquisition = Acquisition::findOrFail($id);

        $validated = array_merge($acquisition->toArray(), $this->validateAcquisition($request, true));


        if ($this->isInvalidReceivedStatus($validated, $acquisition)) {
            return response()->json([
                'message' => 'Cannot set status to received. The quantity acquired must be provided and match the requested quantity.',
            ], 422);
        }

        $acquisition->update(array_merge($validated, [
            'updated_by' => $user->username,
        ]));

        $this->createCatalogueIfNeeded($validated, $acquisition, $user);

        return response()->json([
            'message' => 'Acquisition updated successfully.',
            'acquisition' => $acquisition,
        ]);
    }

    /**
     * List all acquisitions (accessible to all roles).
     */
    public function listAcquisitions()
    {
        $acquisitions = Acquisition::all();

        return response()->json([
            'acquisitions' => $acquisitions,
        ]);
    }

    /**
     * View a specific acquisition (accessible to all roles).
     */
    public function viewAcquisition($id)
    {
        $acquisition = Acquisition::findOrFail($id);

        return response()->json([
            'acquisition' => $acquisition,
        ]);
    }

    /**
     * Archive an acquisition (super_admin only).
     */
    public function archiveAcquisition($id)
    {
        $user = Auth::user();

        if (!$this->canManageAcquisitions($user)) {
            return $this->accessDeniedResponse();
        }

        $acquisition = Acquisition::findOrFail($id);
        $acquisition->update([
            'is_archived' => true,
            'updated_by' => $user->username,
        ]);

        return response()->json([
            'message' => 'Acquisition archived successfully.',
            'acquisition' => $acquisition,
        ]);
    }

    /**
     * Validate acquisition request data.
     */
    private function validateAcquisition(Request $request, $isEdit = false): array
    {
        $rules = [
            'title' => 'required|string|min:3|max:255',
            'author' => 'required|string|min:3|max:255',
            'edition' => 'nullable|string|min:3|max:255',
            'isbn' => 'nullable|string|min:3|max:255',
            'publisher' => 'nullable|string|min:3|max:255',
            'place_of_publication' => 'nullable|string|min:3|max:255',
            'year_of_publication' => 'required|integer',
            'quantity_requested' => 'required|integer|min:1|max:1000',
            'acquisition_method' => 'required|in:book_fair,supplier,donation',
            'supplier_name' => 'nullable|string|min:3|max:255',
            'cost' => 'nullable|integer|min:0|max:1000000',
            'date_acquired' => 'nullable|date',
            'quantity_acquired' => 'nullable|integer|min:0|max:1000',
            'acquisition_status' => 'required|in:received,partial,missing,cancelled,pending',
        ];

        if ($isEdit) {
            $rules = array_intersect_key($rules, $request->all());
        }

        return $request->validate($rules);
    }

    /**
     * Check if the user can manage acquisitions.
     */
    private function canManageAcquisitions($user): bool
    {
        return in_array($user->role, ['super_admin', 'branch_admin']);
    }

    /**
     * Return an access denied response.
     */
    private function accessDeniedResponse()
    {
        return response()->json(['message' => 'Access denied: Only Super and Branch Admin can manage acquisitions.'], 403);
    }

    /**
     * Check if the received status is invalid.
     */
    private function isInvalidReceivedStatus(array $validated, Acquisition $acquisition): bool
    {
        return $validated['acquisition_status'] === 'received' && empty($validated['quantity_acquired']);
    }

    /**
     * Create a catalogue if acquisition status is received.
     */
    private function createCatalogueIfNeeded(array $validated, Acquisition $acquisition, $user)
    {
        if ($validated['acquisition_status'] === 'received' && $validated['quantity_acquired'] > 0) {
            Catalogue::create([
                'acquisition_id' => $acquisition->id,
                'title' => $validated['title'],
                'author' => $validated['author'],
                'edition' => $validated['edition'],
                'isbn' => $validated['isbn'],
                'publisher' => $validated['publisher'],
                'place_of_publication' => $validated['place_of_publication'],
                'year_of_publication' => $validated['year_of_publication'],
                'number_of_copies' => $validated['quantity_acquired'],
                'is_provisional' => true,
                'created_by' => $user->username,
                'updated_by' => $user->username,
            ]);
        }
    }
}
