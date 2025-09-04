---
title: Implementing Notification Support
---

# Implementing Notification Support in Your ArtisanPack UI Application

This guide will walk you through integrating notification capabilities into your ArtisanPack UI-based application, leveraging the CMS Framework's robust notification system for both email and in-app visual notifications.

## Prerequisites

Before you begin, ensure you have the following set up in your ArtisanPack UI application:

- The ArtisanPackUI\CMSFramework is installed and registered in your application.
- Your User model (app/Models/User.php or similar) extends Illuminate\Foundation\Auth\User and uses the Illuminate\Notifications\Notifiable trait.

## Step 1: Create a Custom Notification Class

For each distinct type of notification your application needs to send (e.g., "New Post Published," "Order Status Changed"), you will create a dedicated Laravel Notification class. These classes define the notification's content and how it's delivered via different channels.

**Location**: app/Notifications/ (or a more specific subdirectory like app/Notifications/Posts/ for organization).

**Command**: Use the Artisan command to generate a new notification class:

```bash
php artisan make:notification NewPostPublishedNotification
```

**Example**: app/Notifications/NewPostPublishedNotification.php

```php
<?php
/**
 * New Post Published Notification
 *
 * This notification is sent to relevant recipients when a new post is published.
 * It can be delivered via email and as an in-app visual notification.
 *
 * @link       https://gitlab.com/your-username/your-app-name
 *
 * @package    YourAppName\Notifications
 * @subpackage YourAppName\Notifications\NewPostPublishedNotification
 * @since      1.0.0 // Adjust to your application's version.
 */

namespace App\Notifications; // Adjust namespace to your application's structure.

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use ArtisanPackUI\CMSFramework\Models\User;
use App\Models\Post; // Assuming your application has a Post model.
use TorMorten\Eventy\Facades\Eventy; // Required for the ap.cms.notifications.channels filter.

class NewPostPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The post instance that was published.
     *
     * @since 1.0.0
     * @var \App\Models\Post
     */
    public Post $post;

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\Post $post The post that was published.
     * @since 1.0.0
     */
    public function __construct( Post $post )
    {
        $this->post = $post;
    }

    /**
     * Get the notification's delivery channels.
     *
     * This method determines how the notification will be sent (e.g., 'mail', 'database').
     * It uses the ArtisanPack UI Eventy filter to allow other modules or plugins
     * to customize the channels.
     *
     * @param mixed $notifiable The notifiable entity (e.g., a User instance).
     * @return array<int, string> An array of channel names.
     * @since 1.0.0
     */
    public function via( mixed $notifiable ): array
    {
        $channels = [ 'mail' ]; // Default to sending an email.

        // Check if the notifiable entity is a User and prefers in-app notifications.
        // This assumes your User model has a getSetting() method.
        if ( $notifiable instanceof User && method_exists( $notifiable, 'getSetting' ) && $notifiable->getSetting( 'receive_in_app_notifications', true ) ) {
            $channels[] = 'database'; // Add 'database' channel for in-app notifications.
        }

        /**
         * Filters the notification channels for the 'NewPostPublishedNotification'.
         *
         * Allows developers to add or remove channels for this specific notification.
         *
         * @since 1.0.0
         *
         * @param array                     $channels     The default channels for the notification.
         * @param mixed                     $notifiable   The notifiable entity.
         * @param self                      $notification The current notification instance.
         */
        return Eventy::filter( 'your_app.notifications.new_post_published.channels', $channels, $notifiable, $this );
    }

    /**
     * Get the mail representation of the notification.
     *
     * This method defines the content and appearance of the email notification.
     *
     * @param mixed $notifiable The notifiable entity.
     * @return \Illuminate\Notifications\Messages\MailMessage
     * @since 1.0.0
     */
    public function toMail( mixed $notifiable ): MailMessage
    {
        $subject = sprintf(
            /* translators: %s: Site name. */
            __( 'New Post Published on %s', 'your-app-textdomain' ),
            config( 'app.name' )
        );

        return ( new MailMessage() )
                    ->subject( $subject )
                    ->greeting( __( 'Hello!', 'your-app-textdomain' ) )
                    ->line( sprintf(
                        /* translators: %s: Post title. */
                        __( 'A new post titled "%s" has been published.', 'your-app-textdomain' ),
                        $this->post->post_title
                    ) )
                    ->action( __( 'View Post', 'your-app-textdomain' ), url( '/posts/' . $this->post->post_slug ) )
                    ->line( __( 'Thank you for using our application!', 'your-app-textdomain' ) );
    }

    /**
     * Get the array representation of the notification for database storage (in-app).
     *
     * This method defines the data that will be stored in the 'notifications' database table.
     *
     * @param mixed $notifiable The notifiable entity.
     * @return array<string, mixed> An array of notification data.
     * @since 1.0.0
     */
    public function toDatabase( mixed $notifiable ): array
    {
        return [
            'type'      => 'new_post_published',
            'post_id'   => $this->post->id,
            'post_url'  => url( '/admin/posts/' . $this->post->id . '/edit' ), // Link to admin edit page.
            'message'   => sprintf(
                /* translators: %s: Post title. */
                __( 'New post published: "%s".', 'your-app-textdomain' ),
                $this->post->post_title
            ),
            'read_at'   => null, // This will be set by the system when read.
        ];
    }

    // You can add other `to` methods here for different channels (e.g., `toBroadcast`, `toSlack`).
}
```

## Step 2: Configure Database Notifications (Migration)

For in-app notifications, Laravel stores them in a database table. You need to ensure this table exists.

**Command**: Run Laravel's built-in migration to create the notifications table if you haven't already:

```bash
php artisan notifications:table
php artisan migrate
```

This command will create a create_notifications_table.php migration file and then run it, setting up the necessary database structure.

## Step 3: Implement Visual Notifications in Your Admin Area

To display in-app notifications, you'll create a Livewire component that retrieves and manages the notifications for the authenticated user.

### 3.1. Create the Livewire Component

**Command**:

```bash
php artisan make:livewire Admin/Notifications
```

**Location**: app/Livewire/Admin/Notifications.php

**Example**: app/Livewire/Admin/Notifications.php

```php
<?php
/**
 * Livewire component for displaying admin notifications.
 *
 * Queries and displays unread notifications for the authenticated user.
 * Provides functionality to mark notifications as read.
 *
 * @link       https://gitlab.com/your-username/your-app-name
 *
 * @package    YourAppName\Http\Livewire\Admin
 * @subpackage YourAppName\Http\Livewire\Admin\Notifications
 * @since      1.0.0
 */

namespace App\Livewire\Admin; // Adjust namespace to your application's structure.

use Livewire\Component;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use ArtisanPackUI\Security\Security; // Use ArtisanPackUI's Security class for escaping.

class Notifications extends Component
{
    /**
     * The collection of unread notifications for the current user.
     *
     * @since 1.0.0
     * @var \Illuminate\Database\Eloquent\Collection<\Illuminate\Notifications\DatabaseNotification>
     */
    public $unreadNotifications;

    /**
     * Mount the component, fetching unread notifications for the authenticated user.
     *
     * @since 1.0.0
     * @return void
     */
    public function mount(): void
    {
        // Ensure a user is authenticated before attempting to fetch notifications.
        if ( Auth::check() ) {
            $this->unreadNotifications = Auth::user()->unreadNotifications;
        } else {
            $this->unreadNotifications = collect(); // Return an empty collection if no user.
        }
    }

    /**
     * Marks a specific notification as read.
     *
     * Refreshes the list of unread notifications after the action.
     *
     * @param string $notificationId The ID of the notification to mark as read.
     * @return void
     * @since 1.0.0
     */
    public function markAsRead( string $notificationId ): void
    {
        if ( Auth::check() ) {
            $notification = Auth::user()->notifications()->where( 'id', $notificationId )->first();
            if ( $notification ) {
                $notification->markAsRead();
                $this->unreadNotifications = Auth::user()->unreadNotifications; // Refresh the list.
            }
        }
    }

    /**
     * Marks all unread notifications for the current user as read.
     *
     * Refreshes the list of unread notifications after the action.
     *
     * @return void
     * @since 1.0.0
     */
    public function markAllAsRead(): void
    {
        if ( Auth::check() ) {
            Auth::user()->unreadNotifications->markAsRead();
            $this->unreadNotifications = collect(); // Clear the list as all are read.
        }
    }

    /**
     * Render the Livewire component.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application The rendered Blade view.
     * @since 1.0.0
     */
    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\Foundation\Application
    {
        // Assume 'your-app-namespace::livewire.admin.notifications' is where your Blade view is.
        // Adjust this path based on your application's view conventions.
        return view( 'your-app-namespace::livewire.admin.notifications' );
    }
}
```

### 3.2. Create the Blade View for the Livewire Component

**Location**: resources/views/livewire/admin/notifications.blade.php (or adjust according to your render() method's path).

**Example**: resources/views/livewire/admin/notifications.blade.php

```blade
{{--
/**
 * Blade view for the Livewire Admin Notifications component.
 *
 * Displays a list of unread notifications for the authenticated user,
 * with options to mark individual notifications or all of them as read.
 *
 * @link       https://gitlab.com/your-username/your-app-name
 *
 * @package    YourAppName\Views\Livewire\Admin
 * @subpackage YourAppName\Views\Livewire\Admin\Notifications
 * @since      1.0.0
 */
--}}

<div class="artisanpack-ui-notifications">
    <h2 class="text-xl font-semibold mb-4">{{ __( 'Notifications', 'your-app-textdomain' ) }}</h2>

    @if ( $unreadNotifications->isNotEmpty() )
        <div class="mb-4">
            <button wire:click="markAllAsRead" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                {{ __( 'Mark All as Read', 'your-app-textdomain' ) }}
            </button>
        </div>

        <ul class="notification-list space-y-2">
            @foreach ( $unreadNotifications as $notification )
                <li class="notification-item bg-white p-3 rounded-md shadow-sm flex items-center justify-between">
                    <p class="text-gray-800">
                        {{ ArtisanPackUI\Security\Security::escHtml( $notification->data['message'] ) }}
                    </p>
                    <div class="actions flex space-x-2">
                        @if ( isset( $notification->data['post_url'] ) )
                            <a href="{{ ArtisanPackUI\Security\Security::escUrl( $notification->data['post_url'] ) }}"
                               class="text-blue-600 hover:underline">
                                {{ __( 'View Details', 'your-app-textdomain' ) }}
                            </a>
                        @endif
                        <button wire:click="markAsRead('{{ $notification->id }}')"
                                class="text-gray-600 hover:text-gray-900">
                            <ds:icon icon="check" type="solid" category="fa" classes="h-5 w-5" /> {{-- Example icon --}}
                            {{-- Consider adding a tooltip for accessibility if using only an icon --}}
                        </button>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-gray-600">{{ __( 'No new notifications.', 'your-app-textdomain' ) }}</p>
    @endif
</div>
```

**Important Considerations for the Blade View**:

- **Styling**: The example above includes basic Tailwind CSS classes. You'll need to adapt these to your application's specific CSS framework and design system.
- **Icons**: The `<ds:icon>` component is an example, assuming you are using the artisanpack-ui/icons package. Adjust this to your icon implementation.
- **Security**: Always use `ArtisanPackUI\Security\Security::escHtml()` and `ArtisanPackUI\Security\Security::escUrl()` when displaying user-generated or dynamic content to prevent Cross-Site Scripting (XSS) vulnerabilities.

### 3.3. Integrate the Livewire Component into Your Admin Dashboard

You can embed this Livewire component directly into any Blade view in your admin area where you want to display notifications (e.g., your admin dashboard index.blade.php).

```blade
{{-- In your admin dashboard Blade view --}}
<div class="my-dashboard-widget">
    <livewire:admin.notifications />
</div>
```

## Step 4: Sending Notifications from Your Application

Now that your notification infrastructure is set up, you can send notifications using the CMSManager::notifications() methods.

### Example Usage:

#### 4.1. Sending a Notification to a Specific User:

```php
use ArtisanPackUI\CMSFramework\CMSManager;
use App\Notifications\NewPostPublishedNotification;
use App\Models\Post; // Your application's Post model.
use ArtisanPackUI\CMSFramework\Models\User; // Your application's User model.

// ... inside a controller, service, or event listener ...

$post = Post::find(1); // Get the relevant post.
$user = User::find(1); // Get the user to notify.

if ( $user ) {
    CMSManager::notifications()->sendToUser( $user, new NewPostPublishedNotification( $post ) );
}
```

#### 4.2. Sending a Notification to the Site Administrator:

```php
use ArtisanPackUI\CMSFramework\CMSManager;
use App\Notifications\NewPostPublishedNotification;
use App\Models\Post;

// ... inside a controller, service, or event listener ...

$post = Post::find(1); // Get the relevant post.

// Assuming your CMS settings have an 'admin_user_id' configured.
CMSManager::notifications()->sendToAdmin( new NewPostPublishedNotification( $post ) );
```

#### 4.3. Sending a Notification to Users in Specific Roles:

```php
use ArtisanPackUI\CMSFramework\CMSManager;
use App\Notifications\NewPostPublishedNotification;
use App\Models\Post;

// ... inside a controller, service, or event listener ...

$post = Post::find(1); // Get the relevant post.

// Send to all users with the 'editor' or 'admin' role.
CMSManager::notifications()->sendToRoles( [ 'editor', 'admin' ], new NewPostPublishedNotification( $post ) );
```

## Step 5: Integrating with Your Toast System (Optional)

If you wish to display newly arrived database notifications as a toast, you can leverage Livewire's event system.

### 5.1. Modify Your Notification to Dispatch a Livewire Event

You could, for example, create an observer for the notifications table, or modify the toDatabase method in your notification classes to dispatch a Livewire event. For simplicity here, we'll demonstrate a Livewire event in the Livewire component after fetching notifications.

**Example**: Add Event Dispatching to app/Livewire/Admin/Notifications.php

To trigger a toast when the Notifications component mounts and finds unread notifications, you can dispatch a Livewire event:

```php
<?php
// ... existing namespace and uses ...

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use ArtisanPackUI\Security\Security; // Use ArtisanPackUI's Security class for escaping.

class Notifications extends Component
{
    // ... existing properties ...

    /**
     * Mount the component, fetching unread notifications and potentially dispatching a toast.
     *
     * @since 1.0.0
     * @return void
     */
    public function mount(): void
    {
        if ( Auth::check() ) {
            $this->unreadNotifications = Auth::user()->unreadNotifications;

            // Dispatch a Livewire event for each new notification to trigger a toast.
            foreach ( $this->unreadNotifications as $notification ) {
                $this->dispatch( 'notification-received', [
                    'message' => $notification->data['message'],
                    'type'    => $notification->data['type'] ?? 'info', // Default to info type.
                ] );
            }
        } else {
            $this->unreadNotifications = collect();
        }
    }

    // ... existing markAsRead and markAllAsRead methods ...
}
```

### 5.2. Create or Modify Your Toast Livewire Component to Listen for Events

Your existing Toast component (or a new NotificationToast component) should listen for the dispatched event (notification-received in this example) and display the toast accordingly.

**Example**: Basic Toast Component Listener (app/Livewire/Toast.php or similar)

```php
<?php
/**
 * Generic Toast Notification Livewire Component.
 *
 * Displays temporary toast messages based on dispatched Livewire events.
 *
 * @link       https://gitlab.com/your-username/your-app-name
 *
 * @package    YourAppName\Http\Livewire
 * @subpackage YourAppName\Http\Livewire\Toast
 * @since      1.0.0
 */

namespace App\Livewire;

use Livewire\Component;
use ArtisanPackUI\CMSFramework\Accessibility\A11y; // To get toast duration.

class Toast extends Component
{
    /**
     * The message to display in the toast.
     *
     * @since 1.0.0
     * @var string
     */
    public string $message = '';

    /**
     * The type of the toast (e.g., 'success', 'error', 'info').
     *
     * @since 1.0.0
     * @var string
     */
    public string $type = 'info';

    /**
     * The duration the toast should be visible in milliseconds.
     *
     * @since 1.0.0
     * @var int|float
     */
    public int|float $duration = 5000; // Default to 5 seconds.

    /**
     * Listen for Livewire events to display toasts.
     *
     * @var array<string, string>
     */
    protected $listeners = [ 'notification-received' => 'showToast' ];

    /**
     * Mount the component.
     *
     * @since 1.0.0
     * @return void
     */
    public function mount(): void
    {
        $this->duration = ( new A11y() )->getToastDuration();
    }

    /**
     * Show a toast message.
     *
     * @param array<string, mixed> $data An array containing 'message' and 'type'.
     * @return void
     * @since 1.0.0
     */
    public function showToast( array $data ): void
    {
        $this->message = $data['message'] ?? '';
        $this->type    = $data['type'] ?? 'info';

        // Use Livewire's $js() method to execute Alpine.js for toast display.
        $this->dispatch( 'show-app-toast', [
            'message'  => $this->message,
            'type'     => $this->type,
            'duration' => $this->duration,
        ] )->self(); // Dispatch to self to be handled by Alpine.js on the component's root.
    }

    /**
     * Render the component.
     *
     * @return \Illuminate\Contracts\View\View The rendered Blade view.
     * @since 1.0.0
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view( 'your-app-namespace::livewire.toast' );
    }
}
```

**Blade View for the Toast Component**: resources/views/livewire/toast.blade.php

This Blade view would use Alpine.js to react to the show-app-toast event and display the toast.

```blade
{{--
/**
 * Blade view for the Livewire Toast component.
 *
 * Uses Alpine.js to display temporary toast messages.
 *
 * @link       https://gitlab.com/your-username/your-app-name
 *
 * @package    YourAppName\Views\Livewire
 * @subpackage YourAppName\Views\Livewire\Toast
 * @since      1.0.0
 */
--}}

<div x-data="{
        show: false,
        message: '',
        type: 'info',
        duration: {{ $duration }},
        init() {
            @this.on('show-app-toast', (event) => {
                this.message = event.detail.message;
                this.type = event.detail.type;
                this.show = true;
                setTimeout(() => {
                    this.show = false;
                }, this.duration);
            });
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-full"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-300"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-full"
    class="fixed bottom-4 right-4 p-4 rounded-lg shadow-lg text-white z-50"
    :class="{
        'bg-green-500': type === 'success',
        'bg-red-500': type === 'error',
        'bg-blue-500': type === 'info',
        'bg-yellow-500': type === 'warning'
    }"
    style="display: none;" {{-- Hidden by default until Alpine.js takes over --}}
>
    <p x-text="message"></p>
</div>
```

## Conclusion

By following these steps, you will successfully implement a comprehensive notification system within your ArtisanPack UI application, leveraging the CMS Framework's features and adhering to established coding and documentation standards.
