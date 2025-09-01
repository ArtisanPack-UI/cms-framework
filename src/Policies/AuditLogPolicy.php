<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\AuditLog;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use TorMorten\Eventy\Facades\Eventy;

class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Guests cannot view audit logs for security reasons
            return Eventy::filter('ap.cms.audit_log.can_view_any', false, null);
        }

        // Only allow users with audit log management permissions to view logs
        $canViewAuditLogs = $user->can('manage_audit_logs');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.audit_log.can_view_any', $canViewAuditLogs, $user);
    }

    public function view(?User $user, AuditLog $auditLog): bool
    {
        // Handle guest/unauthenticated users
        if (! $user) {
            // Guests cannot view audit log details for security reasons
            return Eventy::filter('ap.cms.audit_log.can_view', false, null, $auditLog);
        }

        // Only allow users with audit log management permissions to view logs
        $canViewAuditLog = $user->can('manage_audit_logs');

        // Apply Eventy filter for customizable access control
        return Eventy::filter('ap.cms.audit_log.can_view', $canViewAuditLog, $user, $auditLog);
    }

    public function create(User $user): bool
    {
        return $user->can('manage_audit_logs');
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return $user->can('manage_audit_logs');
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return $user->can('manage_audit_logs');
    }

    public function restore(User $user, AuditLog $auditLog): bool
    {
        return $user->can('manage_audit_logs');
    }

    public function forceDelete(User $user, AuditLog $auditLog): bool
    {
        return $user->can('manage_audit_logs');
    }
}
