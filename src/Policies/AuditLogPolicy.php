<?php

namespace ArtisanPackUI\CMSFramework\Policies;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Models\AuditLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
	use HandlesAuthorization;

	public function viewAny( User $user ): bool
	{

	}

	public function view( User $user, AuditLog $auditLog ): bool
	{
	}

	public function create( User $user ): bool
	{
	}

	public function update( User $user, AuditLog $auditLog ): bool
	{
	}

	public function delete( User $user, AuditLog $auditLog ): bool
	{
	}

	public function restore( User $user, AuditLog $auditLog ): bool
	{
	}

	public function forceDelete( User $user, AuditLog $auditLog ): bool
	{
	}
}
