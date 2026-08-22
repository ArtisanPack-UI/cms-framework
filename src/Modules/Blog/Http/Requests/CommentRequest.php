<?php

declare( strict_types=1 );

/**
 * Comment Request for the CMS Framework Blog Module.
 *
 * Validates incoming comment data on create and update. The shape is
 * deliberately small — content + (post_id|parent_id) + (user-bound
 * OR guest author fields). Authorization is delegated to
 * `CommentPolicy`; the controller calls `$this->authorize()` before
 * dispatching to the model.
 *
 * @since 2.1.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Blog\Http\Requests;

use ArtisanPackUI\CMSFramework\Modules\Blog\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @since 2.1.0
 */
class CommentRequest extends FormRequest
{
    /**
     * @since 2.1.0
     */
    public function authorize(): bool
    {
        // Per-operation gating happens in the controller via the
        // CommentPolicy. The form request only handles validation.
        return true;
    }

    /**
     * @since 2.1.0
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isUpdate = in_array( $this->method(), [ 'PUT', 'PATCH' ], true );

        // `parent_id` must belong to the same post — otherwise a
        // client could reply to comment X on post A but file the
        // reply against post B, corrupting the thread tree. Build
        // the constraint as a per-post `exists` rule.
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- post_id is read in rules() only to build a conditional exists constraint and is int-cast; never persisted or output.
        $postId        = $this->input( 'post_id' );
        $parentIdRule  = Rule::exists( 'post_comments', 'id' );
        if ( null !== $postId && '' !== $postId ) {
            $parentIdRule = $parentIdRule->where( 'post_id', ( int ) $postId );
        }

        $rules = [
            'post_id'      => $isUpdate
                ? [ 'sometimes', 'integer', 'exists:posts,id' ]
                : [ 'required', 'integer', 'exists:posts,id' ],
            'parent_id'    => [ 'nullable', 'integer', $parentIdRule ],
            'author_name'  => [ 'nullable', 'string', 'max:255' ],
            'author_email' => [ 'nullable', 'email', 'max:255' ],
            'author_url'   => [ 'nullable', 'url', 'max:2048' ],
            'content'      => [ $isUpdate ? 'sometimes' : 'required', 'string', 'min:1', 'max:65535' ],
        ];

        // `status` is moderation-controlled: only viewers with the
        // `comments.moderate` capability are allowed to submit it.
        // For anyone else we strip the field out before validation
        // runs (see `prepareForValidation()` below) so a client-side
        // `status=approved` payload can't self-approve.
        if ( $this->callerCanModerate() ) {
            $rules['status'] = [
                'sometimes',
                Rule::in( [
                    Comment::STATUS_APPROVED,
                    Comment::STATUS_PENDING,
                    Comment::STATUS_SPAM,
                    Comment::STATUS_TRASH,
                ] ),
            ];
        }

        return $rules;
    }

    /**
     * Strip any client-supplied `user_id` and (for non-moderators)
     * `status` before validation runs. `user_id` is always taken
     * from `auth()->id()` in the controller — accepting it from the
     * client would allow impersonation; `status` defaults to
     * `approved` (auto-approve for moderators) or `pending`
     * otherwise.
     *
     * @since 2.1.0
     */
    protected function prepareForValidation(): void
    {
        // phpcs:ignore ArtisanPackUI.Security.ValidatedSanitizedInput -- input() is read in prepareForValidation() to strip user_id/status before validation runs.
        $input = $this->input();

        unset( $input['user_id'] );

        if ( ! $this->callerCanModerate() ) {
            unset( $input['status'], $input['approved_at'] );
        }

        // Replace($input) drops untouched fields, so re-merge
        // everything back at once.
        $this->replace( $input );
    }

    /**
     * Determine whether the caller can submit moderation-level
     * fields. Gated by the same `comments.moderate` capability the
     * `CommentPolicy::moderate` ability checks.
     *
     * @since 2.1.0
     */
    protected function callerCanModerate(): bool
    {
        $user = $this->user();

        if ( null === $user ) {
            return false;
        }

        return $user->can( applyFilters( 'ap.cmsFramework.abilities.comments.moderate', 'comments.moderate' ) );
    }
}
