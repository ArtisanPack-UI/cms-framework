<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use ArtisanPackUI\CMSFramework\Models\AuditLog;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;

    public function viewAny( User $user ): bool
    {
        return $user->can( 'manage_audit_logs' );
    }

    public function view( User $user, AuditLog $auditLog ): bool
    {
        return $user->can( 'manage_audit_logs' );
    }

    public function create( User $user ): bool
    {
        return $user->can( 'manage_audit_logs' );
    }

    public function update( User $user, AuditLog $auditLog ): bool
    {
        return $user->can( 'manage_audit_logs' );
    }

    public function delete( User $user, AuditLog $auditLog ): bool
    {
        return $user->can( 'manage_audit_logs' );
    }

    public function restore( User $user, AuditLog $auditLog ): bool
    {
        return $user->can( 'manage_audit_logs' );
    }

    public function forceDelete( User $user, AuditLog $auditLog ): bool
    {
        return $user->can( 'manage_audit_logs' );
    }
}
