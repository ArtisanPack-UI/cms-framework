<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\Users\Models;

use ArtisanPackUI\Rbac\Models\Permission as RbacPermission;

/**
 * CMS Framework Permission model.
 *
 * A pass-through subclass of {@see RbacPermission}. Exists so the
 * framework can register itself as the permission model in
 * `artisanpack.rbac.models.permission` and so consumers can extend
 * cms-framework's permission-specific behavior in one place if they
 * need to.
 */
class Permission extends RbacPermission
{
}
