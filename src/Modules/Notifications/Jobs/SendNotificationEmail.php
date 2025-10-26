<?php
/**
 * Send Notification Email Job
 *
 * Queued job for sending notification emails to users.
 *
 * @since 2.0.0
 * @package ArtisanPackUI\CMSFramework\Modules\Notifications\Jobs
 */

namespace ArtisanPackUI\CMSFramework\Modules\Notifications\Jobs;

use ArtisanPackUI\CMSFramework\Modules\Notifications\Mail\NotificationMail;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Job to send notification emails.
 *
 * @since 2.0.0
 */
class SendNotificationEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The notification instance.
     *
     * @since 2.0.0
     * @var Notification
     */
    protected Notification $notification;

    /**
     * Array of user IDs to send email to.
     *
     * @since 2.0.0
     * @var array
     */
    protected array $userIds;

    /**
     * Create a new job instance.
     *
     * @since 2.0.0
     *
     * @param Notification $notification The notification to send.
     * @param array $userIds Array of user IDs to send to.
     */
    public function __construct(Notification $notification, array $userIds)
    {
        $this->notification = $notification;
        $this->userIds = $userIds;
    }

    /**
     * Execute the job.
     *
     * @since 2.0.0
     *
     * @return void
     */
    public function handle(): void
    {
        $userModel = config('auth.providers.users.model');
        $users = $userModel::whereIn('id', $this->userIds)->get();

        foreach ($users as $user) {
            Mail::to($user->email)->send(
                new NotificationMail($this->notification, $user)
            );
        }
    }
}
