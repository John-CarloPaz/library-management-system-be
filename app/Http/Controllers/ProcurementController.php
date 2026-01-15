<?php

namespace App\Http\Controllers;

use App\Events\GenericActionEvent;
use App\Events\NotificationCreated;
use App\Models\Procurement;
use App\Models\Acquisition;
use App\Models\Notification;
use App\Services\ListQueryService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProcurementController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private ListQueryService $lists,
    ) {
    }
    /**
     * Create a new procurement request.
     */
    public function createProcurement(Request $request)
    {
        $user = Auth::user();
        if (!$this->permissions->canCreateProcurement($user)) {
            return response()->json(['message' => 'Access denied: Cannot create procurement.'], 403);
        }

        $validated = $this->validateProcurement($request);
        $adminApproval = $this->getAdminApproval($user);

        $procurement = Procurement::create(array_merge($validated, [
            'requested_by' => $user->id,
            'admin_approval' => $adminApproval,
            'created_by' => $user->username,
            'updated_by' => $user->username,
        ]));

        $this->createAcquisitionIfNeeded($procurement, $user);

        // Notify requester when procurement is auto-approved on creation
        if (in_array($procurement->admin_approval, ['approved', 'rejected'], true)) {
            $this->notifyProcurementStatusChange($procurement, $procurement->admin_approval);
        }


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
        $previousStatus = $procurement->admin_approval;
        $user = Auth::user();

        if ($user->role === 'admin' && $validated['admin_approval'] === 'approved') {
            return response()->json(['message' => 'Access denied: Admin cannot approve procurements.'], 403);
        }
        
        if (!$this->canEditApproval($user, $validated)) {
            return response()->json(['message' => 'Access denied: Only Super Admin and Branch Admin can edit procurement approvals.'], 403);
        }

        $procurement->update(array_merge($validated, ['updated_by' => $user->username]));

        // If admin_approval changed to approved or rejected, notify requester
        $procurement->refresh();
        $newStatus = $procurement->admin_approval;
        if ($previousStatus !== $newStatus && in_array($newStatus, ['approved', 'rejected'], true)) {
            $this->notifyProcurementStatusChange($procurement, $newStatus);
        }

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
    public function getAllProcurements(Request $request)
    {
        $procurements = $this->lists->build(
            $request,
            Procurement::query(),
            [
                'status_field' => 'admin_approval',
                'archived_field' => 'is_archived',
                'search_fields' => ['title', 'author'],
            ]
        );

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

        // Only pending or rejected procurements can be archived
        if ($procurement->admin_approval === 'approved') {
            return response()->json([
                'message' => 'Cannot archive an approved procurement request.'
            ], 400);
        }

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

    // All procurement list variations (active, archived, all, by status) are now handled
    // via query parameters on getAllProcurements using ListQueryService.
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
        return $this->permissions->canEditProcurementApproval($user) && isset($validated['admin_approval']);
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

    /**
     * Create and broadcast a notification when a procurement is approved or rejected.
     */
    private function notifyProcurementStatusChange(Procurement $procurement, string $status): void
    {
        if (!$procurement->requested_by) {
            return;
        }

        $statusLabel = $status === 'approved' ? 'approved' : 'rejected';

        $notification = Notification::create([
            'user_id' => $procurement->requested_by,
            'type' => 'procurement',
            'title' => 'Procurement ' . $statusLabel,
            'message' => "Your procurement '{$procurement->title}' was {$statusLabel}.",
            'data' => [
                'procurement_id' => $procurement->id,
                'status' => $status,
                'title' => $procurement->title,
            ],
        ]);

        NotificationCreated::dispatch($notification);
    }
}
