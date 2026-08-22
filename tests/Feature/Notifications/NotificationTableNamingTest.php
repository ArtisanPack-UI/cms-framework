<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\NotificationPreference;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Schema;

uses( Illuminate\Foundation\Testing\RefreshDatabase::class );

test( 'notification tables use the cms_ prefix', function (): void {
    expect( Schema::hasTable( 'cms_notifications' ) )->toBeTrue()
        ->and( Schema::hasTable( 'cms_notification_user' ) )->toBeTrue()
        ->and( Schema::hasTable( 'cms_notification_preferences' ) )->toBeTrue();
} );

test( 'the framework does not claim Laravel\'s notifications table names', function (): void {
    expect( Schema::hasTable( 'notifications' ) )->toBeFalse()
        ->and( Schema::hasTable( 'notification_user' ) )->toBeFalse()
        ->and( Schema::hasTable( 'notification_preferences' ) )->toBeFalse();
} );

test( 'models resolve to the prefixed tables', function (): void {
    expect( (new Notification)->getTable() )->toBe( 'cms_notifications' )
        ->and( (new NotificationPreference)->getTable() )->toBe( 'cms_notification_preferences' );
} );

test( 'a CMS notification coexists with a Laravel database notification', function (): void {
    // Recreate Laravel's own database notifications table exactly as the
    // framework's built-in migration would, so both channels are in play.
    Schema::create( 'notifications', function ( Blueprint $table ): void {
        $table->uuid( 'id' )->primary();
        $table->string( 'type' );
        $table->morphs( 'notifiable' );
        $table->text( 'data' );
        $table->timestamp( 'read_at' )->nullable();
        $table->timestamps();
    } );

    $user = User::factory()->create();

    // A CMS notification lands in cms_notifications.
    $cmsNotification = Notification::factory()->create();
    $user->systemNotifications()->attach( $cmsNotification->id );

    // A Laravel database notification lands in notifications, no collision.
    $user->notify( new class extends LaravelNotification {
        use Queueable;

        public function via( object $notifiable ): array
        {
            return ['database'];
        }

        public function toArray( object $notifiable ): array
        {
            return ['message' => 'hello'];
        }
    } );

    expect( Notification::count() )->toBe( 1 )
        ->and( $user->notifications()->count() )->toBe( 1 )
        ->and( $user->systemNotifications()->count() )->toBe( 1 );
});
