<?php

declare( strict_types=1 );

/**
 * Hook alias registration for the CMS Framework package.
 *
 * Centralizes the full set of pre-2.5.0 hook names that have been renamed
 * into the `ap.cmsFramework.*` (and `ap.rbac.*`) namespaces. Each old name
 * is registered with the hooks package's deprecation manager so that
 * existing subscribers continue to fire when the new canonical names
 * are dispatched, and vice versa.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.5.0
 */

namespace ArtisanPackUI\CMSFramework\Support;

/**
 * Registers backward-compatible aliases for every hook renamed in 2.5.0.
 *
 * Called from CMSFrameworkServiceProvider::boot() early in the boot pass
 * so aliases are in place before any subscriber registers a callback.
 *
 * @package    ArtisanPack_UI
 * @subpackage CMSFramework
 *
 * @since      2.5.0
 */
final class HookAliases
{
	/**
	 * Register every 2.5.0 hook alias against the hooks deprecation manager.
	 *
	 * Grouped by rollout wave for traceability against issues #193, #194,
	 * and #195. Intended to be called exactly once from
	 * `CMSFrameworkServiceProvider::boot()`.
	 *
	 * @since 2.5.0
	 */
	public static function register(): void
	{
		foreach ( self::map() as $old => $new ) {
			deprecateHook( $old, $new );
		}
	}

	/**
	 * The full old-to-new hook rename map shipped in 2.5.0.
	 *
	 * @since 2.5.0
	 *
	 * @return array<string, string>
	 */
	public static function map(): array
	{
		return array_merge(
			self::wave4aInfrastructure(),
			self::wave4bLifecycle(),
			self::wave4cAbilities(),
		);
	}

	/**
	 * Wave 4a — infrastructure hooks (issue #193).
	 *
	 * Admin menu, asset enqueues, content-edit extension surfaces, dynamic
	 * content type registration, and RBAC registration events.
	 *
	 * @since 2.5.0
	 *
	 * @return array<string, string>
	 */
	private static function wave4aInfrastructure(): array
	{
		return [
			'ap.admin.menu'                     => 'ap.cmsFramework.admin.menu',
			'ap.admin.enqueuedAssets'           => 'ap.cmsFramework.admin.enqueuedAssets',
			'ap.public.enqueuedAssets'          => 'ap.cmsFramework.public.enqueuedAssets',
			'ap.auth.enqueuedAssets'            => 'ap.cmsFramework.auth.enqueuedAssets',
			'ap.admin.contentEdit.tabs'         => 'ap.cmsFramework.admin.contentEdit.tabs',
			'ap.admin.contentEdit.panels'       => 'ap.cmsFramework.admin.contentEdit.panels',
			'ap.admin.contentEdit.beforeEditor' => 'ap.cmsFramework.admin.contentEdit.beforeEditor',
			'ap.admin.contentEdit.afterEditor'  => 'ap.cmsFramework.admin.contentEdit.afterEditor',
			'ap.admin.contentEdit.saveData'     => 'ap.cmsFramework.admin.contentEdit.saveData',
			'ap.dynamic_content.register-types' => 'ap.cmsFramework.dynamicContent.registerTypes',
			'ap.roleRegistered'                 => 'ap.rbac.roleRegistered',
			'ap.permissionRegistered'           => 'ap.rbac.permissionRegistered',
		];
	}

	/**
	 * Wave 4b — lifecycle hooks (issue #194).
	 *
	 * Plugin and theme install/activate/deactivate/delete/update events,
	 * plus comment-related surfaces.
	 *
	 * `comments.form.action` is the one entry here whose fire site lives
	 * outside this package — `artisanpack-ui/visual-editor` emits it from
	 * its `post-comments-form` block. It is namespaced under
	 * `ap.cmsFramework.*` anyway because comments are this package's
	 * domain (the filter's default value is this package's
	 * `POST /api/v1/comments` endpoint), following the same
	 * emitter-is-not-owner precedent as `ap.rbac.roleRegistered`, which
	 * this package fires under the RBAC namespace. visual-editor declares
	 * the identical alias in its own `HookAliases` so old-name subscribers
	 * keep resolving in installs that do not have this package — the same
	 * defensive cross-package declaration it already makes for
	 * `ap.icons.register-icon-sets`. `deprecateHook()` is *intended* to be
	 * idempotent per (old, new) pair so both declarations are safe together
	 * (issue #245); until artisanpack-ui/hooks#16 ships, the underlying
	 * `HookDeprecations::alias()` does not dedup, so a subscriber registered
	 * on the old name *before* the aliases are declared can fire twice when
	 * both packages declare this alias.
	 *
	 * @since 2.5.0
	 *
	 * @return array<string, string>
	 */
	private static function wave4bLifecycle(): array
	{
		$pluginActions = [
			'installing',
			'installed',
			'activating',
			'activated',
			'deactivating',
			'deactivated',
			'deleting',
			'deleted',
			'updating',
			'updated',
		];

		$themeActions = [
			'installing',
			'installed',
			'activating',
			'activated',
		];

		$map = [];

		foreach ( $pluginActions as $action ) {
			$map[ "plugin.{$action}" ] = "ap.cmsFramework.plugin.{$action}";
		}

		foreach ( $themeActions as $action ) {
			$map[ "theme.{$action}" ] = "ap.cmsFramework.theme.{$action}";
		}

		$map['comment.editLink']                  = 'ap.cmsFramework.comment.editLink';
		$map['comments.store.defaultStatus']      = 'ap.cmsFramework.comments.store.defaultStatus';
		$map['comments.rate-limit.guest']         = 'ap.cmsFramework.comments.rateLimit.guest';
		$map['comments.rate-limit.authenticated'] = 'ap.cmsFramework.comments.rateLimit.authenticated';
		$map['comments.form.action']              = 'ap.cmsFramework.comments.form.action';

		return $map;
	}

	/**
	 * Wave 4c — policy ability filters (issue #195).
	 *
	 * Every `{resource}.{action}` filter used by policies to remap Gate
	 * ability names is namespaced under `ap.cmsFramework.abilities.*`.
	 *
	 * @since 2.5.0
	 *
	 * @return array<string, string>
	 */
	private static function wave4cAbilities(): array
	{
		$abilities = [
			'posts'          => [
				'viewAny',
				'view',
				'create',
				'update',
				'updateOwn',
				'delete',
				'deleteOwn',
				'publish',
			],
			'pages'          => [
				'viewAny',
				'view',
				'create',
				'update',
				'updateOwn',
				'delete',
				'deleteOwn',
				'publish',
			],
			'comments'       => [
				'viewAny',
				'view',
				'create',
				'update',
				'updateOwn',
				'delete',
				'deleteOwn',
				'moderate',
			],
			'postTags'       => self::crudActions(),
			'postCategories' => self::crudActions(),
			'pageTags'       => self::crudActions(),
			'pageCategories' => self::crudActions(),
			'contentTypes'   => self::crudActions(),
			'taxonomies'     => self::crudActions(),
			'customFields'   => self::crudActions(),
			'dynamicContent' => self::crudActions(),
			'permissions'    => [
				'viewAny',
				'view',
				'create',
				'update',
				'delete',
				'restore',
				'forceDelete',
			],
			'settings'       => [
				'viewAny',
				'view',
				'create',
				'update',
				'delete',
				'restore',
				'forceDelete',
			],
			'role'           => [
				'viewAny',
				'view',
				'create',
				'update',
				'delete',
				'restore',
				'forceDelete',
			],
		];

		$map = [];

		foreach ( $abilities as $resource => $actions ) {
			foreach ( $actions as $action ) {
				$old         = "{$resource}.{$action}";
				$map[ $old ] = "ap.cmsFramework.abilities.{$resource}.{$action}";
			}
		}

		// DynamicContent record-scoped abilities — sibling of the
		// per-type filters registered above under `dynamicContent.*`.
		foreach ( self::crudActions() as $action ) {
			$map[ "dynamicContent.record.{$action}" ] = "ap.cmsFramework.abilities.dynamicContent.record.{$action}";
		}

		// Guest/anonymous variants of the comments read/create abilities
		// (evaluated by `CommentPolicy` for unauthenticated visitors).
		foreach ( [ 'viewAny', 'view', 'create' ] as $action ) {
			$map[ "comments.{$action}.public" ] = "ap.cmsFramework.abilities.comments.{$action}.public";
		}

		return $map;
	}

	/**
	 * Standard CRUD ability actions shared by the resource-tag and
	 * resource-category style policies.
	 *
	 * @since 2.5.0
	 *
	 * @return array<int, string>
	 */
	private static function crudActions(): array
	{
		return [
			'viewAny',
			'view',
			'create',
			'update',
			'delete',
		];
	}
}
