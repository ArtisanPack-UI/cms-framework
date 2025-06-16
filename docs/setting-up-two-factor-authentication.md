---
title: Enabling Two-Factor Authentication (Email-Based)
---

# Enabling Two-Factor Authentication (Email-Based)

This guide explains how to enable and integrate the email-based Two-Factor Authentication (2FA) feature provided by the ArtisanPack UI CMS Framework into your content management system. This feature enhances security by requiring users to verify their login with a temporary code sent to their email address.

## Overview

The ArtisanPack UI CMS Framework includes a robust, yet simple, email-based 2FA system. It provides the core logic for generating, storing, and verifying 2FA codes.

Key components involved in the 2FA process:

- **TwoFactorAuthManager**: Manages the generation, sending, storage, and verification of email-based 2FA codes.
- **User Model** (within the Framework): Contains the necessary database fields (`two_factor_code`, `two_factor_expires_at`, `two_factor_enabled_at`) and helper methods to manage a user's 2FA status.
- **TwoFactorCodeNotification**: A Laravel Notification class responsible for sending the 2FA code to the user's email.
- **Database Migration**: A migration is included in the framework to add the necessary 2FA columns to your users table.
- **TwoFactorAuthServiceProvider**: Registers the TwoFactorAuthManager with the application's service container.

## Requirements

Before proceeding, ensure the following are in place:

- **ArtisanPack UI CMS Framework Installed**: Your Laravel application must have the CMS Framework installed via Composer.
- **Database Migrations Run**: Ensure you have run all migrations for your CMS Framework, as this will create the necessary 2FA columns on your users table. If you have previously migrated, you may need to run `php artisan migrate` after updating the framework to get the new 2FA columns.
- **Email Configuration**: Your Laravel application must be configured to send emails. This is typically done in your `.env` file (e.g., `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`).
- **Notifiable Trait on User Model**: Ensure your User model (if it's not the one directly from the framework's `ArtisanPackUI\CMSFramework\Models`) uses the `Illuminate\Notifications\Notifiable` trait. The framework's built-in User model already includes this.

## Implementation Steps

To enable and integrate 2FA, you will primarily work with your application's authentication flow, adding middleware, routes, and views.

### 1. Adjust Login Flow to Trigger 2FA

After a user successfully authenticates with their username and password, you need to check if 2FA is enabled for them and, if so, redirect them to a 2FA challenge page.

#### A. Create a Two-Factor Authentication Middleware

This middleware will intercept authenticated users who have 2FA enabled and redirect them to the 2FA challenge.

Create `app/Http/Middleware/TwoFactorAuthenticate.php` with the following content:

```php
<?php
/**
 * Two-Factor Authentication Middleware
 *
 * Intercepts authenticated users to check if two-factor authentication is required
 * and redirects them to the 2FA challenge page if necessary.
 *
 * @package    YourAppNamespace
 * @subpackage YourAppNamespace\Http\Middleware
 * @since      1.0.0
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use ArtisanPackUI\CMSFramework\Features\Auth\TwoFactorAuthManager;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Http\Request  $request The incoming HTTP request.
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next The next middleware or request handler.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle( Request $request, Closure $next ): Response
    {
        $user = Auth::user();

        // Check if user exists, has 2FA enabled (meaning a code has been sent),
        // and hasn't passed the 2FA challenge for this session.
        if ( $user && $user->hasTwoFactorEnabled() && ! Session::has( 'two_factor_passed' ) ) {
            // Check if the code has expired.
            if ( $user->twoFactorCodeHasExpired() ) {
                // Code expired, clear it and redirect to login or show error.
                // You might want to automatically re-send a new code here, or redirect to a page that allows re-sending.
                app( TwoFactorAuthManager::class )->disableTwoFactor( $user ); // Clear expired code.
                Auth::logout(); // Log out the user if the code is expired.
                return redirect()->route( 'login' )->withErrors( 'Your 2FA code has expired. Please log in again.' );
            }

            // If 2FA is enabled and code is valid, redirect to the challenge page.
            return redirect()->route( 'two-factor.challenge' );
        }

        return $next( $request );
    }
}
```

#### B. Register the Middleware

Add your new TwoFactorAuthenticate middleware to your `app/Http/Kernel.php` file. It should be added to the web middleware group, typically after the AuthenticateSession middleware.

```php
// app/Http/Kernel.php
protected array $middlewareGroups = [
    'web' => [
        // ... other middleware
        \Illuminate\Session\Middleware\AuthenticateSession::class,
        \App\Http\Middleware\TwoFactorAuthenticate::class, // Add this line
        // ... other middleware
    ],
    // ...
];
```

### 2. Define 2FA Routes

You'll need routes for the 2FA challenge form and for processing the verification.

Add the following to your `routes/web.php` file:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use ArtisanPackUI\CMSFramework\Features\Auth\TwoFactorAuthManager; // Framework's 2FA Manager
use ArtisanPackUI\CMSFramework\Models\User; // Your framework's User model

// ... (Your existing authentication routes, e.g., from Laravel Breeze/Fortify)

Route::get( '/two-factor-challenge', function () {
    return view( 'auth.two-factor-challenge' );
} )->middleware( [ 'auth' ] )->name( 'two-factor.challenge' );

Route::post( '/two-factor-challenge', function ( Request $request ) {
    $request->validate( [
        'code' => [ 'required', 'string', 'digits:6' ],
    ] );

    $user = $request->user();
    $twoFactorManager = app( TwoFactorAuthManager::class );

    if ( $twoFactorManager->verifyCode( $user, $request->code ) ) {
        // Code verified, mark session as 2FA passed and clear stored code.
        Session::put( 'two_factor_passed', true );
        $twoFactorManager->disableTwoFactor( $user ); // Clear code after successful verification.

        return redirect()->intended( route( 'dashboard' ) ); // Or wherever you want to redirect.
    }

    return back()->withErrors( [ 'code' => 'The provided 2FA code is invalid or expired.' ] );
} )->middleware( [ 'auth' ] );

// Route to manually resend 2FA code (e.g., if the user didn't receive it)
Route::post( '/two-factor-resend', function( Request $request ) {
    $user = $request->user();
    $twoFactorManager = app( TwoFactorAuthManager::class );

    // Only resend if 2FA is "active" for the user (i.e., code sent but not yet verified/expired)
    if ( $user && $user->hasTwoFactorEnabled() && ! $user->twoFactorCodeHasExpired() ) {
        $code = $twoFactorManager->generateNumericCode();
        // Re-store with new expiration.
        $twoFactorManager->storeTwoFactorCode( $user, $code );
        $twoFactorManager->sendTwoFactorCode( $user, $code );
        return back()->with( 'status', 'A new 2FA code has been sent to your email.' );
    }

    return back()->withErrors( 'Unable to resend 2FA code.' );

} )->middleware( [ 'auth' ] )->name( 'two-factor.resend' );

// Routes for enabling/disabling 2FA from a user's profile/settings
// These will likely require additional UI in your CMS's user settings area.
Route::post( '/user/two-factor/enable', function( Request $request ) {
    $user = $request->user();
    $twoFactorManager = app( TwoFactorAuthManager::class );

    if ( ! $user->hasTwoFactorEnabled() ) {
        $code = $twoFactorManager->generateNumericCode();
        $twoFactorManager->storeTwoFactorCode( $user, $code );
        $twoFactorManager->sendTwoFactorCode( $user, $code );
        return back()->with( 'status', 'A 2FA setup code has been sent to your email. Enter it below to enable 2FA.' );
    }

    return back()->withErrors( '2FA is already enabled.' );
} )->middleware( [ 'auth' ] )->name( 'two-factor.enable' );

Route::post( '/user/two-factor/disable', function( Request $request ) {
    $user = $request->user();
    $twoFactorManager = app( TwoFactorAuthManager::class );
    $twoFactorManager->disableTwoFactor( $user );
    return back()->with( 'status', 'Two-factor authentication has been disabled.' );
} )->middleware( [ 'auth' ] )->name( 'two-factor.disable' );
```

### 3. Create 2FA Challenge View

This view will present the form where the user enters the 2FA code received via email.

Create `resources/views/auth/two-factor-challenge.blade.php`:

```blade
{{-- resources/views/auth/two-factor-challenge.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication Challenge</title>
    </head>
<body>
    <div>
        <h1>Two-Factor Authentication</h1>
        <p>Please enter the 6-digit code sent to your email address.</p>

        @if (session('status'))
            <div style="color: green;">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.challenge') }}">
            @csrf

            <div>
                <label for="code">Two-Factor Code</label>
                <input id="code" type="text" name="code" required autofocus autocomplete="off">

                @error('code')
                    <span style="color: red;">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <button type="submit">Verify Code</button>
            </div>
        </form>

        <form method="POST" action="{{ route('two-factor.resend') }}">
            @csrf
            <p>Didn't receive the code?</p>
            <button type="submit">Resend Code</button>
        </form>
    </div>
</body>
</html>
```

### 4. Triggering 2FA Code Sending on Login

For the 2FA process to initiate, you need to send a code to the user's email after a successful password login. You can achieve this by listening to Laravel's Login event and then dispatching the 2FA code.

You would typically modify your application's LoginController (or wherever your login logic resides) or simply add an event listener. Since your framework's CMSFrameworkServiceProvider already registers a general Login event listener for audit logging, you can extend that or create a new one.

#### Option A: Add Logic to LoginController (Common for consuming apps):

If your consuming app uses a LoginController, you can override the sendLoginResponse method or add logic to the authenticated method.

```php
// app/Http/Controllers/Auth/LoginController.php (Example modification)
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use ArtisanPackUI\CMSFramework\Features\Auth\TwoFactorAuthManager; // Framework's 2FA Manager

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard'; // Or your dashboard route.

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        // Check if 2FA should be enforced for this user
        // You might have a setting in the user's profile for this.
        $force2fa = false; // Example: Determine this from user settings or global CMS settings.
        // For demonstration, let's assume 2FA is forced if they don't have a code set.
        if ( ! $user->hasTwoFactorEnabled() ) {
             $force2fa = true; // Or from a user preference
        }

        if ( $force2fa ) {
            $twoFactorManager = app( TwoFactorAuthManager::class );
            $code = $twoFactorManager->generateNumericCode();
            $twoFactorManager->storeTwoFactorCode( $user, $code ); // Store the code
            $twoFactorManager->sendTwoFactorCode( $user, $code );  // Send the email

            // Laravel's built-in `RedirectIfTwoFactorAuthenticated` middleware
            // (or your custom `TwoFactorAuthenticate`) will then redirect to the challenge.
        }

        return redirect()->intended($this->redirectPath());
    }
}
```

#### Option B: Dedicated Event Listener for 2FA (Advanced):

This is cleaner for separating concerns. You can create a listener that sends the 2FA code only after a successful login. This would be defined in your consuming application's `app/Providers/EventServiceProvider.php`.

```php
// app/Providers/EventServiceProvider.php (in consuming application)
<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use ArtisanPackUI\CMSFramework\Features\Auth\TwoFactorAuthManager; // Framework's 2FA Manager

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [
            // Other listeners, e.g., for audit logging (if not already handled by framework)
            \App\Listeners\SendTwoFactorCode::class, // Your new listener
        ],
        // ...
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // Or true, if you set up discoverable events.
    }
}

// app/Listeners/SendTwoFactorCode.php
<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use ArtisanPackUI\CMSFramework\Features\Auth\TwoFactorAuthManager;
use Illuminate\Contracts\Queue\ShouldQueue; // If you want to queue email sending.
use Illuminate\Queue\InteractsWithQueue;

class SendTwoFactorCode // implements ShouldQueue // Uncomment if you want to queue
{
    // use InteractsWithQueue; // Uncomment if you want to queue

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Auth\Events\Login  $event
     * @return void
     */
    public function handle(Login $event)
    {
        $user = $event->user;

        // Implement your logic here to determine if 2FA should be sent for this user.
        // For example, if user has a preference for 2FA.
        $userPrefers2FA = true; // Replace with actual user preference or CMS setting.

        if ($userPrefers2FA && ! $user->hasTwoFactorEnabled()) {
            $twoFactorManager = app(TwoFactorAuthManager::class);
            $code = $twoFactorManager->generateNumericCode();
            $twoFactorManager->storeTwoFactorCode($user, $code);
            $twoFactorManager->sendTwoFactorCode($user, $code);
        }
    }
}
```

## Managing 2FA in User Settings

You should provide UI elements in your CMS's user profile or settings page to allow users to enable or disable 2FA.

- **Enable 2FA Button**: When a user clicks "Enable 2FA", make an AJAX request or form submission to the `/user/two-factor/enable` route. This will send an initial 2FA code to their email. You then need a follow-up form where they enter this code to confirm enablement.
- **Disable 2FA Button**: A simple button to call the `/user/two-factor/disable` route. This will clear the 2FA data from their profile. You might want to require password confirmation for this action.

By following these steps, you can successfully integrate the email-based Two-Factor Authentication provided by the ArtisanPack UI CMS Framework into your content management system.
