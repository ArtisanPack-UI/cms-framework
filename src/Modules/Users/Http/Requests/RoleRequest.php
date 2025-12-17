<?php

/**
 * Role Request for the CMS Framework Users Module.
 *
 * This form request handles validation and authorization for role-related
 * HTTP requests, ensuring data integrity and security.
 *
 * @since   1.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Users\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for role validation and authorization.
 *
 * Provides validation rules and authorization logic for role creation
 * and update operations with proper field validation.
 *
 * @since 1.0.0
 */
class RoleRequest extends FormRequest
{
    /**
     * The role instance.
     */
    protected ?Role $role = null;

    /**
     * Sets the role for the request.
     *
     * This method allows the role model to be passed in from contexts
     * like a Livewire component where route model binding isn't automatic.
     *
     * @since 1.0.0
     *
     * @param  Role  $role  The role instance.
     */
    public function setRole(Role $role): self
    {
        $this->role = $role;

        return $this;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @since 1.0.0
     *
     * @return bool True if the user is authorized, false otherwise.
     */
    public function authorize(): bool
    {
        // Authorization is handled by policies, so we return true here
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The validation rules.
     */
    public function rules(): array
    {
        $roleId = $this->role ? $this->role->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('roles', 'slug')->ignore($roleId),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The role name is required.',
            'name.unique' => 'A role with this name already exists.',
            'slug.required' => 'The role slug is required.',
            'slug.regex' => 'The role slug must be lowercase letters, numbers, and hyphens only.',
            'slug.unique' => 'A role with this slug already exists.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @since 1.0.0
     *
     * @return array<string, string> The custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'name' => 'role name',
            'slug' => 'role slug',
        ];
    }
}
