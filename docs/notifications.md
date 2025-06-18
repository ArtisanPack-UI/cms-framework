---
title: Notifications
---

# Notifications

The ArtisanPack UI CMS Framework provides a flexible notification system that allows you to send notifications to users and other notifiable entities through various channels such as email, database, SMS, and more.

## Overview

The Notifications feature is built on top of Laravel's notification system and provides a simple, consistent interface for sending notifications across various channels. The system is designed to be extensible, allowing you to create custom notification channels and notification types.

## Key Components

The notification system consists of several key components:

### NotificationManager

The `NotificationManager` class is the central component of the notification system. It provides methods for sending notifications to users and other notifiable entities.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\Notifications;
```

#### Methods

##### send(mixed $notifiable, Notification $notification): void
Sends a notification to a notifiable entity.

**@since** 1.1.0

**@param** mixed $notifiable The entity to notify (user, etc.)
**@param** Notification $notification The notification instance to send

##### sendToMany(array $notifiables, Notification $notification): void
Sends a notification to multiple notifiable entities.

**@since** 1.1.0

**@param** array $notifiables Array of entities to notify
**@param** Notification $notification The notification instance to send

##### sendLater(mixed $notifiable, Notification $notification, $delay = null): void
Sends a notification later (queued) with an optional delay.

**@since** 1.1.0

**@param** mixed $notifiable The entity to notify (user, etc.)
**@param** Notification $notification The notification instance to send
**@param** \DateTimeInterface|\DateInterval|int|null $delay Optional. When to send the notification

##### sendNow(mixed $notifiable, Notification $notification): void
Sends a notification immediately (bypassing queue).

**@since** 1.1.0

**@param** mixed $notifiable The entity to notify (user, etc.)
**@param** Notification $notification The notification instance to send

### NotificationServiceProvider

The `NotificationServiceProvider` class registers the notification services with the application.

#### Namespace
```php
namespace ArtisanPackUI\CMSFramework\Features\Notifications;
```

#### Methods

##### register(): void
Registers the NotificationManager as a singleton service in the application container.

**@since** 1.1.0

##### boot(): void
Performs any additional setup needed for the notification system.

**@since** 1.1.0

## Creating Notifications

To create a notification, you need to create a class that extends the `Illuminate\Notifications\Notification` class. This class should define the channels through which the notification will be sent and the message format for each channel.

### Example Notification

Here's an example of a notification that sends a two-factor authentication code via email:

```php
<?php

namespace ArtisanPackUI\CMSFramework\Features\Auth\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TwoFactorCodeNotification extends Notification
{
    use Queueable;

    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your Two-Factor Authentication Code')
            ->line('Please use the following code to complete your login:')
            ->line('Code: ' . $this->code)
            ->line('This code will expire in 5 minutes.')
            ->line('If you did not request this code, no further action is required.');
    }
}
```

## Sending Notifications

You can send notifications using the `NotificationManager` class or through Laravel's notification system directly.

### Using NotificationManager

```php
use ArtisanPackUI\CMSFramework\Features\Notifications\NotificationManager;
use ArtisanPackUI\CMSFramework\Features\Auth\Notifications\TwoFactorCodeNotification;

// Get the notification manager from the service container
$notificationManager = app(NotificationManager::class);

// Send a notification to a user
$notificationManager->send($user, new TwoFactorCodeNotification('123456'));

// Send a notification to multiple users
$notificationManager->sendToMany([$user1, $user2], new TwoFactorCodeNotification('123456'));

// Send a notification with a delay
$notificationManager->sendLater($user, new TwoFactorCodeNotification('123456'), now()->addMinutes(5));

// Send a notification immediately (bypassing queue)
$notificationManager->sendNow($user, new TwoFactorCodeNotification('123456'));
```

### Using Laravel's Notification Facade

```php
use Illuminate\Support\Facades\Notification;
use ArtisanPackUI\CMSFramework\Features\Auth\Notifications\TwoFactorCodeNotification;

// Send a notification to a user
Notification::send($user, new TwoFactorCodeNotification('123456'));

// Send a notification to multiple users
Notification::send([$user1, $user2], new TwoFactorCodeNotification('123456'));

// Send a notification with a delay
Notification::later(now()->addMinutes(5), $user, new TwoFactorCodeNotification('123456'));

// Send a notification immediately (bypassing queue)
Notification::sendNow($user, new TwoFactorCodeNotification('123456'));
```

## Notification Channels

Laravel provides several notification channels out of the box:

- **mail**: Sends notifications via email
- **database**: Stores notifications in the database
- **broadcast**: Broadcasts notifications to a frontend application
- **slack**: Sends notifications to Slack
- **sms**: Sends notifications via SMS (requires a third-party service)

You can specify which channels to use for a notification by implementing the `via` method in your notification class.

## Customizing Notifications

You can customize notifications in several ways:

### Custom Channels

You can create custom notification channels by implementing the `Illuminate\Notifications\ChannelInterface` interface.

### Custom Message Formats

You can customize the message format for each channel by implementing the corresponding method in your notification class:

- `toMail`: For email notifications
- `toDatabase`: For database notifications
- `toBroadcast`: For broadcast notifications
- `toSlack`: For Slack notifications
- `toArray`: For array notifications

## Integration with Laravel

The notification system integrates seamlessly with Laravel's notification system:

- Uses Laravel's notification system under the hood
- Works with Laravel's queue system for delayed notifications
- Supports all Laravel notification channels

## Best Practices

For optimal use of the notification system:

1. Use queued notifications for non-urgent notifications to improve performance
2. Implement rate limiting for notifications to prevent spam
3. Provide clear and concise notification messages
4. Allow users to customize their notification preferences
5. Test notifications thoroughly to ensure they are delivered correctly

## Conclusion

The notification system in the ArtisanPack UI CMS Framework provides a flexible and powerful way to send notifications to users and other notifiable entities. By leveraging Laravel's notification system, it offers a wide range of channels and customization options while maintaining a simple and consistent interface.

## Implementation Guide

For a detailed guide on implementing notification support in your ArtisanPack UI application, see the [Implementing Notification Support](notification-implementation.md) documentation.
