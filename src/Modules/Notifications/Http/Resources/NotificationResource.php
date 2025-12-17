<?php

/**
 * Notification API Resource
 *
 * Transforms notification data for API responses.
 *
 * @since 2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notification Resource
 *
 * @since 2.0.0
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @since 2.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivotData = $this->whenPivotLoaded('notification_user', function () {
            return [
                'is_read' => (bool) $this->pivot->is_read,
                'read_at' => $this->pivot->read_at,
                'is_dismissed' => (bool) $this->pivot->is_dismissed,
                'dismissed_at' => $this->pivot->dismissed_at,
            ];
        });

        return [
            'id' => $this->id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
                'icon' => $this->type->icon(),
                'colorClass' => $this->type->colorClass(),
            ],
            'title' => $this->title,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'send_email' => $this->send_email,
            'created_at' => $this->created_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->toISOString(),
            ...(is_array($pivotData) ? ['user_data' => $pivotData] : []),
        ];
    }
}
