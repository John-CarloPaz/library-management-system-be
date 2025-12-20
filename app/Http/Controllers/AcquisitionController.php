<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Models\Acquisition;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        if ($validated['acquisition_status'] === 'received') {
            if ($this->isInvalidReceivedStatus($validated, new Acquisition())) {
                return response()->json([
                    'message' => 'Cannot set status to received. All required fields must be provided.',
                ], 422);
            }
            $validated['received_by'] = $user->username;
        }

        $acquisition = Acquisition::create(array_merge($validated, [
            'total_cost' => ($validated['cost'] ?? 0) * $validated['quantity_acquired'],
            'created_by' => $user->username,
            'updated_by' => $user->username,
            'is_archived' => false,
        ]));
        $this->createCatalogueIfNeeded($validated, $acquisition, $user, true, $acquisition->getOriginal('acquisition_status'));
        GenericActionEvent::dispatch([
            'resource_type' => 'acquisition',
            'action' => 'create',
            'resource_id' => $acquisition->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

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


        if ($validated['acquisition_status'] === 'received') {
            if ($this->isInvalidReceivedStatus($validated, $acquisition)) {
                return response()->json([
                    'message' => 'Cannot set status to received. All required fields must be provided.',
                ], 422);
            }
            $validated['received_by'] = $user->username;
        }
        $this->createCatalogueIfNeeded($validated, $acquisition, $user, false, $acquisition->getOriginal('acquisition_status'));
        $acquisition->update(array_merge($validated, [
            'updated_by' => $user->username,
        ]));

        GenericActionEvent::dispatch([
            'resource_type' => 'acquisition',
            'action' => 'update',
            'resource_id' => $acquisition->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

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

    public function listActiveAcquisitions()
    {
        $acquisitions = Acquisition::where('is_archived', false)->get();

        return response()->json([
            'acquisitions' => $acquisitions,
        ]);
    }

    public function listArchivedAcquisitions()
    {
        $acquisitions = Acquisition::where('is_archived', true)->get();

        return response()->json([
            'archived_acquisitions' => $acquisitions,
        ]);
    }

    public function restoreAcquisition($id)
    {
        $user = Auth::user();

        if (!$this->canManageAcquisitions($user)) {
            return $this->accessDeniedResponse();
        }

        $acquisition = Acquisition::findOrFail($id);
        $acquisition->update([
            'is_archived' => false,
            'updated_by' => $user->username,
        ]);

        GenericActionEvent::dispatch([
            'resource_type' => 'acquisition',
            'action' => 'restore',
            'resource_id' => $acquisition->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Acquisition restored successfully.',
            'acquisition' => $acquisition,
        ]);
    }

    /**
     * View a specific acquisition (accessible to all roles).
     */
    public function viewAcquisition($id)
    {
        $acquisition = Acquisition::with('catalogue')->findOrFail($id);

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

        GenericActionEvent::dispatch([
            'resource_type' => 'acquisition',
            'action' => 'archive',
            'resource_id' => $acquisition->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
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
            'branch_id' => 'required|exists:branches,id',
            'procurement_id' => 'nullable|exists:procurements,id',
            'received_by' => 'nullable|string|min:3|max:255',
            'is_archived' => 'nullable|boolean',
            'created_by' => 'nullable|string|min:3|max:255',
            'updated_by' => 'nullable|string|min:3|max:255',
            'total_cost' => 'nullable|numeric|min:0|max:100000000',
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
        return $validated['acquisition_status'] === 'received' && empty($validated['quantity_acquired']) && $validated['date_acquired'] === null && $validated['cost'] === null;
    }

    /**
     * Create a catalogue if acquisition status is received.
     */
    private function createCatalogueIfNeeded(
        array $validated,
        Acquisition $acquisition,
        $user,
        bool $isCreate = false,
        ?string $oldStatus = null
    ) {
        if ($validated['acquisition_status'] !== 'received') {
            return;
        }
        if (empty($validated['quantity_acquired']) || $validated['quantity_acquired'] <= 0) {
            return;
        }
        if ($acquisition->catalogue()->exists()) {
            return;
        }
        if (!$isCreate && $oldStatus === 'received') {
            return;
        }

        $catalogue = Catalogue::create([
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
            'branch_id' => $validated['branch_id'],
            'created_by' => $user->username,
            'updated_by' => $user->username,
            'is_archived' => false,
        ]);

        GenericActionEvent::dispatch([
            'resource_type' => 'catalogue',
            'action' => 'create',
            'resource_id' => $catalogue->id,
            'user_id' => auth()->id(),
            'user_name' => $user->username,
            'timestamp' => now(),
        ]);
    }
}
