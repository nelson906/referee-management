<?php

namespace App\Policies;

use App\Models\Letterhead;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LetterheadPolicy
{
    /**
     * Determine whether the user can view any letterheads.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'national_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can view the letterhead.
     */
    public function view(User $user, Letterhead $letterhead): bool
    {
        // Super admins can view all letterheads
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // Admins can view letterheads in their zone or global letterheads
        if (in_array($user->user_type, ['admin', 'national_admin'])) {
            return $this->isInUserZoneOrGlobal($user, $letterhead);
        }

        return false;
    }

    /**
     * Determine whether the user can create letterheads.
     */
    public function create(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'national_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can update the letterhead.
     */
    public function update(User $user, Letterhead $letterhead): bool
    {
        // Super admins can update all letterheads
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // Admins can update letterheads in their zone
        if (in_array($user->user_type, ['admin', 'national_admin'])) {
            // Cannot update global letterheads unless super admin
            if ($letterhead->zone_id === null) {
                return false;
            }

            return $this->isInUserZone($user, $letterhead);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the letterhead.
     */
    public function delete(User $user, Letterhead $letterhead): bool
    {
        // Cannot delete default letterheads
        if ($letterhead->is_default) {
            return false;
        }

        // Super admins can delete non-default letterheads
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // Admins can delete letterheads in their zone (but not global ones)
        if (in_array($user->user_type, ['admin', 'national_admin'])) {
            if ($letterhead->zone_id === null) {
                return false; // Cannot delete global letterheads
            }

            return $this->isInUserZone($user, $letterhead);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the letterhead.
     */
    public function restore(User $user, Letterhead $letterhead): bool
    {
        return $this->delete($user, $letterhead);
    }

    /**
     * Determine whether the user can permanently delete the letterhead.
     */
    public function forceDelete(User $user, Letterhead $letterhead): bool
    {
        // Only super admins can permanently delete
        return $user->user_type === 'super_admin' && !$letterhead->is_default;
    }

    /**
     * Determine whether the user can duplicate the letterhead.
     */
    public function duplicate(User $user, Letterhead $letterhead): bool
    {
        // Can duplicate if can view and create
        return $this->view($user, $letterhead) && $this->create($user);
    }

    /**
     * Determine whether the user can set the letterhead as default.
     */
    public function setDefault(User $user, Letterhead $letterhead): bool
    {
        // Must be able to update and letterhead must be active
        if (!$this->update($user, $letterhead) || !$letterhead->is_active) {
            return false;
        }

        // Super admins can set any active letterhead as default
        if ($user->user_type === 'super_admin') {
            return true;
        }

        // Admins can set letterheads in their zone as default
        if (in_array($user->user_type, ['admin', 'national_admin'])) {
            return $this->isInUserZone($user, $letterhead);
        }

        return false;
    }

    /**
     * Determine whether the user can toggle the letterhead's active status.
     */
    public function toggleActive(User $user, Letterhead $letterhead): bool
    {
        // Cannot deactivate default letterheads
        if ($letterhead->is_default && $letterhead->is_active) {
            return false;
        }

        return $this->update($user, $letterhead);
    }

    /**
     * Determine whether the user can manage letterhead logo.
     */
    public function manageLogo(User $user, Letterhead $letterhead): bool
    {
        return $this->update($user, $letterhead);
    }

    /**
     * Determine whether the user can preview the letterhead.
     */
    public function preview(User $user, Letterhead $letterhead): bool
    {
        return $this->view($user, $letterhead);
    }

    /**
     * Determine whether the user can export letterhead data.
     */
    public function export(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'national_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can bulk manage letterheads.
     */
    public function bulkManage(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'national_admin', 'super_admin']);
    }

    /**
     * Check if letterhead is in user's zone or is global.
     */
    private function isInUserZoneOrGlobal(User $user, Letterhead $letterhead): bool
    {
        // Global letterheads (zone_id = null) are accessible to all admins
        if ($letterhead->zone_id === null) {
            return true;
        }

        return $this->isInUserZone($user, $letterhead);
    }

    /**
     * Check if letterhead is in user's zone.
     */
    private function isInUserZone(User $user, Letterhead $letterhead): bool
    {
        // If user has no zone restriction, they can access all
        if ($user->zone_id === null) {
            return true;
        }

        // Check if letterhead belongs to user's zone
        return $letterhead->zone_id === $user->zone_id;
    }

    /**
     * Get the zone filter for queries based on user permissions.
     */
    public static function getZoneFilter(User $user): ?int
    {
        // Super admins see everything
        if ($user->user_type === 'super_admin') {
            return null;
        }

        // Admins see their zone + global
        if (in_array($user->user_type, ['admin', 'national_admin'])) {
            return $user->zone_id;
        }

        return null;
    }

    /**
     * Apply zone restrictions to a query.
     */
    public static function applyZoneRestrictions($query, User $user)
    {
        if ($user->user_type === 'super_admin') {
            return $query; // No restrictions for super admins
        }

        if (in_array($user->user_type, ['admin', 'national_admin']) && $user->zone_id) {
            return $query->where(function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id)
                  ->orWhereNull('zone_id'); // Include global letterheads
            });
        }

        return $query;
    }
}
