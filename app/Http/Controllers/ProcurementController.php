<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Models\Procurement;
use App\Models\Acquisition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProcurementController extends Controller
{
    /**
     * Create a new procurement request.
     */
    public function createProcurement(Request $request)
    {
        $validated = $this->validateProcurement($request);

        $user = Auth::user();
        $adminApproval = $this->getAdminApproval($user);

        $procurement = Procurement::create(array_merge($validated, [
            'requested_by' => $user->id,
            'admin_approval' => $adminApproval,
            'created_by' => $user->username,
            'updated_by' => $user->username,
        ]));

        $this->createAcquisitionIfNeeded($procurement, $user);


        GenericActionEvent::dispatch([
            'resource_type' => 'procurement',
            'action' => 'create',
            'resource_id' => $procurement->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Procurement request created successfully.',
            'procurement' => $procurement,
        ], 201);
    }

    /**
     * Edit an existing procurement request.
     */
    public function editProcurement(Request $request, $id)
    {
        $validated = $this->validateProcurement($request, true);

        $procurement = Procurement::findOrFail($id);
        $user = Auth::user();

        if (!$this->canEditApproval($user, $validated)) {
            return response()->json(['message' => 'Access denied: Only Super Admin and Branch Admin can edit procurement approvals.'], 403);
        }

        $procurement->update(array_merge($validated, ['updated_by' => $user->username]));

        $this->createAcquisitionIfNeeded($procurement, $user);


        GenericActionEvent::dispatch([
            'resource_type' => 'procurement',
            'action' => 'update',
            'resource_id' => $procurement->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);


        return response()->json([
            'message' => 'Procurement request updated successfully.',
            'procurement' => $procurement,
        ]);
    }
    public function getAllProcurements()
    {
        $procurements = Procurement::all();

        return response()->json([
            'procurements' => $procurements,
        ]);
    }
    /**
     * Get all active procurements.
     */
    public function getAllActiveProcurements()
    {
        $procurements = Procurement::where('is_archived', false)->get();

        return response()->json([
            'procurements' => $procurements,
        ]);
    }

    /**
     * Archive a procurement request.
     */
    public function archiveProcurement($id)
    {
        $procurement = Procurement::findOrFail($id);
        $procurement->update(['is_archived' => true]);

        GenericActionEvent::dispatch([
            'resource_type' => 'procurement',
            'action' => 'archive',
            'resource_id' => $procurement->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Procurement request archived successfully.',
        ]);
    }

    public function viewProcurement($id)
    {
        $procurement = Procurement::with( 'acquisitions')->findOrFail($id);

        return response()->json([
            'procurement' => $procurement,
        ]);
    }

    public function restoreProcurement($id)
    {
        $procurement = Procurement::findOrFail($id);
        $procurement->update(['is_archived' => false]);


        GenericActionEvent::dispatch([
            'resource_type' => 'procurement',
            'action' => 'restore',
            'resource_id' => $procurement->id,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->username,
            'timestamp' => now(),
        ]);

        return response()->json([
            'message' => 'Procurement request restored successfully.',
        ]);
    }

    public function getAllArchivedProcurements()
    {
        $procurements = Procurement::where('is_archived', true)->get();

        return response()->json([
            'procurements' => $procurements,
        ]);
    }



    /**
     * Validate procurement request data.
     */
    private function validateProcurement(Request $request, $isEdit = false): array
    {
        $rules = [
            'title' => 'required|string',
            'author' => 'required|string',
            'edition' => 'nullable|string',
            'isbn' => 'nullable|string',
            'publisher' => 'nullable|string',
            'place_of_publication' => 'nullable|string',
            'year_of_publication' => 'required|integer',
            'quantity_requested' => 'required|integer',
        ];

        if ($isEdit) {
            // Only validate fields that are present in the request
            $rules = array_intersect_key($rules, $request->all());
            $rules['admin_approval'] = 'nullable|in:approved,rejected,pending';
        }

        return $request->validate($rules);
    }

    /**
     * Determine admin approval status based on user role.
     */
    private function getAdminApproval($user): string
    {
        return $user->role === 'super_admin' ? 'approved' : 'pending';
    }

    /**
     * Check if the user can edit admin approval.
     */
    private function canEditApproval($user, $validated): bool
    {
        return in_array($user->role, ['super_admin', 'branch_admin']) && isset($validated['admin_approval']);
    }

    /**
     * Create an acquisition record if needed.
     */
    private function createAcquisitionIfNeeded(Procurement $procurement, $user)
    {
        if ($procurement->admin_approval === 'approved') {
            $acquisition = Acquisition::firstOrCreate(
                ['procurement_id' => $procurement->id],
                [
                    'title' => $procurement->title,
                    'author' => $procurement->author,
                    'edition' => $procurement->edition,
                    'isbn' => $procurement->isbn,
                    'publisher' => $procurement->publisher,
                    'place_of_publication' => $procurement->place_of_publication,
                    'year_of_publication' => $procurement->year_of_publication,
                    'quantity_requested' => $procurement->quantity_requested,
                    'acquisition_method' => 'supplier',
                    'supplier_name' => '',
                    'cost' => 0,
                    'date_acquired' => null,
                    'quantity_acquired' => null,
                    'acquisition_status' => 'pending',
                    'received_by' => null,
                    'created_by' => $user->username,
                    'updated_by' => $user->username,
                ]
            );

            GenericActionEvent::dispatch([
                'resource_type' => 'acquisition',
                'action' => 'create',
                'resource_id' => $acquisition->id,
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->username,
                'timestamp' => now(),
            ]);
        }
    }
}
