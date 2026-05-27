<?php

/**
 * Menu Item Form Request
 *
 * Validates store/update payloads for `/api/v1/menu-items`. Enforces:
 *
 * - `type` ∈ {link, submenu, page-list}
 * - `kind` ∈ {post-type, taxonomy, custom} when present (allow-list)
 * - `(object_type, object_id)` paired (both present or both absent)
 * - `parent_id` references a `MenuItem` belonging to the same `Menu`
 *
 * Normalizes WP REST sentinel values (`parent: 0`, `object: ""` paired
 * with `object_id: 0`) to nulls in {@see prepareForValidation()} so
 * upstream WP-shape clients pass without rewriting their payloads.
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Resources\MenuItemResource;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Menu;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @since 2.0.0
 */
class MenuItemRequest extends FormRequest
{
    /**
     * @since 2.0.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @since 2.0.0
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPost   = $this->isMethod( 'post' );
        $required = $isPost ? 'required' : 'sometimes';

        return [
            'menus'       => [$isPost ? 'required' : 'prohibited', 'integer', $this->menuExistsRule()],
            'parent'      => ['nullable', 'integer', $this->parentExistsRule()],
            'menu_order'  => ['nullable', 'integer', 'min:0'],
            'type'        => [$required, 'string', Rule::in( MenuItem::TYPES )],
            'title'       => [$required, 'string', 'max:255'],
            'url'         => ['nullable', 'string', 'max:2048'],
            'target'      => ['nullable', 'string', Rule::in( ['_self', '_blank'] )],
            'xfn'         => ['nullable', 'string', 'max:255'],
            'classes'     => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'object'      => ['nullable', 'string', 'max:64', $this->objectPairing()],
            'object_id'   => ['nullable', 'integer', $this->objectPairing()],
            'kind'        => ['nullable', 'string', 'max:32', Rule::in( array_keys( MenuItemResource::KIND_TO_TYPE ) )],
        ];
    }

    /**
     * @since 2.0.0
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'menus.required'    => __( 'A parent menu id is required.' ),
            'menus.prohibited'  => __( 'The parent menu cannot be reassigned via update.' ),
            'type.in'           => __( 'Type must be one of link, submenu, or page-list.' ),
            'target.in'         => __( 'Target must be _self or _blank.' ),
        ];
    }

    /**
     * Normalize WP REST sentinel values before validation runs:
     *
     * - `parent: 0` → `parent: null` (WP marks root items with id `0`,
     *   which fails `exists:menu_items,id` since no item has id 0).
     * - `object: ""` paired with `object_id: 0` → both null (WP marks
     *   non-typed items this way; the pairing rule treats `""` as empty
     *   on one side but `0` as filled on the other, raising a spurious
     *   "must be provided together" error).
     *
     * @since 2.0.0
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ( $this->has( 'parent' ) && self::isZeroSentinel( $this->input( 'parent' ) ) ) {
            $merge['parent'] = null;
        }

        $object   = $this->input( 'object' );
        $objectId = $this->input( 'object_id' );

        if (
            ( '' === $object || null === $object )
            && ( null === $objectId || self::isZeroSentinel( $objectId ) )
        ) {
            $merge['object']    = null;
            $merge['object_id'] = null;
        }

        if ( ! empty( $merge ) ) {
            $this->merge( $merge );
        }
    }

    /**
     * Identify the WP sentinel zero (sent as `0` or `"0"`) without
     * matching the literal string `"0"` other code paths might intend
     * (e.g. an `object` whose value is the string `"0"`, which is not
     * a sentinel — `object` is a non-numeric type vocabulary). Used by
     * {@see prepareForValidation()} on integer-typed fields only.
     *
     * @since 2.0.0
     */
    protected static function isZeroSentinel( mixed $value ): bool
    {
        return 0 === $value || '0' === $value;
    }

    /**
     * Theme-scoped existence rule for the `menus` field. Falls back to
     * an unscoped `exists` when no theme is active so the `exists` step
     * still rejects truly nonexistent ids consistently — the controller
     * will surface the no-active-theme case as 409 once validation
     * reaches it.
     *
     * Scoped existence prevents cross-theme leakage: `menus: <id>` for
     * an id that lives in another theme now yields the same 422 as a
     * truly-nonexistent id, instead of distinguishing the two through
     * controller-level 404 vs validation-level 422.
     *
     * @since 2.0.0
     */
    protected function menuExistsRule(): mixed
    {
        $themeSlug = $this->activeThemeSlug();

        if ( null === $themeSlug ) {
            return 'exists:menus,id';
        }

        return Rule::exists( 'menus', 'id' )->where( static function ( $query ) use ( $themeSlug ): void {
            $query->where( 'theme', $themeSlug );
        } );
    }

    /**
     * Theme-scoped existence rule for the `parent` field. The parent
     * must reference a `menu_item` whose owning `Menu` belongs to the
     * active theme. The further "same menu" constraint runs in
     * {@see passedValidation()}.
     *
     * @since 2.0.0
     */
    protected function parentExistsRule(): mixed
    {
        $themeSlug = $this->activeThemeSlug();

        if ( null === $themeSlug ) {
            return 'exists:menu_items,id';
        }

        return Rule::exists( 'menu_items', 'id' )->where( static function ( $query ) use ( $themeSlug ): void {
            $query->whereIn(
                'menu_id',
                Menu::query()->where( 'theme', $themeSlug )->select( 'id' ),
            );
        } );
    }

    /**
     * @since 2.0.0
     */
    protected function activeThemeSlug(): ?string
    {
        $theme = app( ThemeManager::class )->getActiveTheme();

        return null !== $theme && ! empty( $theme['slug'] ) ? (string) $theme['slug'] : null;
    }

    /**
     * Closure rule that enforces the `(object, object_id)` pairing — both
     * fields must be present together, or both absent.
     *
     * @since 2.0.0
     */
    protected function objectPairing(): Closure
    {
        return function ( string $attribute, mixed $value, Closure $fail ): void {
            $other = 'object' === $attribute ? 'object_id' : 'object';

            $thisFilled  = null !== $value && '' !== $value;
            $otherFilled = $this->filled( $other );

            if ( $thisFilled !== $otherFilled ) {
                $fail( __( 'object and object_id must be provided together.' ) );
            }
        };
    }

    /**
     * @since 2.0.0
     */
    protected function passedValidation(): void
    {
        // After validation, enforce parent-belongs-to-same-menu. Done here
        // (rather than in `rules()`) so we have both the validated parent
        // and the menu_id together, including the existing item's menu on
        // updates where `menus` is prohibited.
        $parentId = $this->input( 'parent' );

        if ( null === $parentId ) {
            return;
        }

        $menuId = $this->resolvedMenuId();

        if ( null === $menuId ) {
            return;
        }

        $parent = MenuItem::query()->find( $parentId );

        if ( null !== $parent && (int) $parent->menu_id !== (int) $menuId ) {
            $this->failedValidation(
                tap( validator( $this->all(), [] ), function ( $validator ): void {
                    $validator->errors()->add(
                        'parent',
                        __( 'parent must reference an item belonging to the same menu.' ),
                    );
                } ),
            );
        }
    }

    /**
     * Resolve the effective menu id for the current request: the `menus`
     * input on POST, or the existing item's `menu_id` on update.
     *
     * @since 2.0.0
     */
    protected function resolvedMenuId(): ?int
    {
        if ( $this->isMethod( 'post' ) ) {
            $menuId = $this->input( 'menus' );

            return is_numeric( $menuId ) ? (int) $menuId : null;
        }

        $itemId = $this->route( 'id' );

        if ( null === $itemId ) {
            return null;
        }

        $item = MenuItem::query()->find( $itemId );

        return null !== $item ? (int) $item->menu_id : null;
    }
}
