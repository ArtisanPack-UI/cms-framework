<?php

/**
 * Menu Item Form Request
 *
 * Validates store/update payloads for `/api/v1/menu-items`. Enforces:
 *
 * - `type` ∈ {link, submenu, page-list}
 * - `(object_type, object_id)` paired (both present or both absent)
 * - `parent_id` references a `MenuItem` belonging to the same `Menu`
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Modules\SiteEditor\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\MenuItem;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @since 1.2.0
 */
class MenuItemRequest extends FormRequest
{
    /**
     * @since 1.2.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isPost   = $this->isMethod( 'post' );
        $required = $isPost ? 'required' : 'sometimes';

        return [
            'menus'       => [ $isPost ? 'required' : 'prohibited', 'integer', 'exists:menus,id' ],
            'parent'      => [ 'nullable', 'integer', 'exists:menu_items,id' ],
            'menu_order'  => [ 'nullable', 'integer', 'min:0' ],
            'type'        => [ $required, 'string', Rule::in( MenuItem::TYPES ) ],
            'title'       => [ $required, 'string', 'max:255' ],
            'url'         => [ 'nullable', 'string', 'max:2048' ],
            'target'      => [ 'nullable', 'string', Rule::in( [ '_self', '_blank' ] ) ],
            'xfn'         => [ 'nullable', 'string', 'max:255' ],
            'classes'     => [ 'nullable', 'string', 'max:255' ],
            'description' => [ 'nullable', 'string' ],
            'object'      => [ 'nullable', 'string', 'max:64', $this->objectPairing() ],
            'object_id'   => [ 'nullable', 'integer', $this->objectPairing() ],
            'kind'        => [ 'nullable', 'string', 'max:32' ],
        ];
    }

    /**
     * @since 1.2.0
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
     * Closure rule that enforces the `(object, object_id)` pairing — both
     * fields must be present together, or both absent.
     *
     * @since 1.2.0
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
     * @since 1.2.0
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
     * @since 1.2.0
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
