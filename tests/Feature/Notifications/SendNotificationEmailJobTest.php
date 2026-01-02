<?php

use ArtisanPackUI\CMSFramework\Modules\Notifications\Jobs\SendNotificationEmail;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Mail\NotificationMail;
use ArtisanPackUI\CMSFramework\Modules\Notifications\Models\Notification;
use ArtisanPackUI\CMSFramework\Tests\Support\TestUser as User;
use Illuminate\Support\Facades\Mail;

uses( Illuminate\Foundation\Testing\RefreshDatabase::class );

test( 'job sends emails to specified users', function (): void {
    Mail::fake();

    $user1        = User::factory()->create();
    $user2        = User::factory()->create();
    $notification = Notification::factory()->create();

    $job = new SendNotificationEmail( $notification, [$user1->id, $user2->id] );
    $job->handle();

    Mail::assertSent( NotificationMail::class, 2 );
    Mail::assertSent( NotificationMail::class, function ( $mail ) use ( $user1 ) {
        return $mail->hasTo( $user1->email );
    } );
    Mail::assertSent( NotificationMail::class, function ( $mail ) use ( $user2 ) {
        return $mail->hasTo( $user2->email );
    } );
} );

test( 'job handles empty user list gracefully', function (): void {
    Mail::fake();

    $notification = Notification::factory()->create();

    $job = new SendNotificationEmail( $notification, [] );
    $job->handle();

    Mail::assertNothingSent();
} );

test( 'job only sends to existing users', function (): void {
    Mail::fake();

    $user         = User::factory()->create();
    $notification = Notification::factory()->create();

    // Include a non-existent user ID
    $job = new SendNotificationEmail( $notification, [$user->id, 9999] );
    $job->handle();

    Mail::assertSent( NotificationMail::class, 1);
});
