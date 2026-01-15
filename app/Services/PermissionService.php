<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    /**
     * Generic helper: check if the user has any of the allowed roles.
     */
    public function hasRole(?User $user, array $allowedRoles): bool
    {
        if (!$user) {
            return false;
        }

        return in_array($user->role, $allowedRoles, true);
    }

    /**
     * Super admin only.
     */
    public function isSuperAdmin(?User $user): bool
    {
        return $this->hasRole($user, ['super_admin']);
    }

    /**
     * Admin management (create/edit/list admins).
     * Currently only super_admin is allowed.
     */
    public function canManageAdmins(?User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Can the $actor edit the $target user record?
     *
     * Rules:
     * - Super admin can edit anyone
     * - A user can edit their own record
     * - Branch admin can edit admin (and branch_admin) users within their branch
     */
    public function canEditUser(?User $actor, ?User $target): bool
    {
        if (! $actor || ! $target) {
            return false;
        }

        if ($this->isSuperAdmin($actor)) {
            return true;
        }

        // Allow self-edit
        if ($actor->id === $target->id) {
            return true;
        }

        // Branch admin may edit admins (and branch_admin) within the same branch
        if ($actor->role === 'branch_admin' && $actor->branch_id !== null && $target->branch_id === $actor->branch_id) {
            return in_array($target->role, ['admin', 'branch_admin'], true);
        }

        return false;
    }

    public function canViewAdminDetails(?User $user): bool
    {
        return $this->hasRole($user, ['super_admin', 'branch_admin', 'admin']);
    }

    /**
     * Branch management (CRUD branches).
     * Currently only super_admin is allowed.
     */
    public function canManageBranches(?User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Acquisition management.
     * Currently only super_admin is allowed.
     */
    public function canManageAcquisitions(?User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Procurement creation/editing.
     * Creation: any authenticated user can request.
     * Editing approvals: super_admin or branch_admin.
     */
    public function canCreateProcurement(?User $user): bool
    {
        return (bool) $user; // any authenticated user
    }

    public function canEditProcurementApproval(?User $user): bool
    {
        return $this->hasRole($user, ['super_admin', 'branch_admin', 'admin']);
    }

    /**
     * Catalogue management (create/edit/archive/restore).
     * Currently any authenticated user is allowed; adjust here if needed.
     */
    public function canManageCatalogues(?User $user): bool
    {
        return (bool) $user;
    }

    /**
     * Book management (status, archive/restore).
     * Currently any authenticated user except plain admin according to business rules.
     */
    public function canManageBooks(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        // Mirror existing checks where plain 'admin' was blocked for some actions
        return $this->hasRole($user, ['super_admin', 'branch_admin']);
    }

    /**
     * Borrowing operations (borrow/extend/return/archive/restore).
     * Currently any authenticated user.
     */
    public function canManageBorrows(?User $user): bool
    {
        return (bool) $user;
    }

    /**
     * Student management.
     * Currently any authenticated user.
     */
    public function canManageStudents(?User $user): bool
    {
        return (bool) $user;
    }

    /**
     * Chat module access: admins (super_admin, branch_admin, admin) only.
     */
    public function canUseChat(?User $user): bool
    {
        return $this->hasRole($user, ['super_admin', 'branch_admin', 'admin']);
    }
}
