# Setting up API Authentication with Laravel Sanctum

This guide outlines the steps for developers to integrate and utilize Laravel Sanctum for API authentication within their applications when consuming the ArtisanPack UI CMS Framework.

## 1. Install Composer Dependencies

First, ensure your main application has the necessary Composer dependencies. Laravel Sanctum should be required in your application's composer.json or will be pulled in as a dependency of the CMS Framework package.

Run Composer update:
```bash
composer update
```

## 2. Publish Sanctum Configuration and Run Migrations

The ArtisanPack UI CMS Framework automatically registers Sanctum's migrations and allows for publishing its configuration file.

To ensure the personal_access_tokens table is created and Sanctum's configuration is available in your application, run the following Artisan commands:
```bash
php artisan migrate
php artisan vendor:publish --tag="sanctum-config"
```

The `php artisan migrate` command will create the personal_access_tokens table, which Sanctum uses to store API tokens. The `php artisan vendor:publish --tag="sanctum-config"` command will publish the sanctum.php configuration file to your application's config directory, allowing for customization of Sanctum's behavior (e.g., token expiration, guarded routes).

## 3. Configure Your User Model

Your application's User model (or the User model provided by the CMS Framework package if you are using it directly) must use the Laravel\Sanctum\HasApiTokens trait. This trait provides the necessary methods for managing API tokens.

Ensure your User model looks similar to this:
```php
// In your application's App\Models\User.php (or your package's User model)
namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Import the trait

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // Use the HasApiTokens trait

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
```

## 4. Configure Authentication Guards

Verify that your application's config/auth.php file is configured to use Sanctum as the API guard driver.

Ensure the api guard uses the sanctum driver:
```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'api' => [
        'driver' => 'sanctum', // Ensure this is set to 'sanctum'
        'provider' => 'users',
        'hash' => false,
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class, // Or point to ArtisanPackUI\CMSFramework\Models\User::class if your package provides it
    ],

    // ... other providers
],
```

## 5. Generating API Tokens

To authenticate API requests, you will need to generate API tokens for your users. These tokens can be created using the createToken method provided by the HasApiTokens trait on your User model.

Here's an example of how you might create a token, typically within a login route or a dedicated token generation endpoint:
```php
// Example in a controller or route
use App\Models\User; // Or ArtisanPackUI\CMSFramework\Models\User if your package provides it
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route; // If defining directly in routes file

Route::post('/api/token', function (Request $request) {
    $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
        'device_name' => ['required'],
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // Generate a token with optional abilities (permissions)
    $token = $user->createToken($request->device_name, ['settings:read', 'settings:update'])->plainTextToken;

    return response()->json([
        'token' => $token,
        'message' => 'Token generated successfully.'
    ]);
});
```

- `device_name`: A unique identifier for the device or application consuming the API (e.g., 'mobile-app', 'web-browser').
- `abilities` (optional): An array of strings representing the permissions associated with this token. You can check these abilities in your middleware or controllers.

## 6. Making Authenticated API Requests

Once you have an API token, you can include it in the Authorization header of your API requests using the Bearer schema.

Example using cURL:
```bash
curl -X GET \
  http://your-app.com/api/cms/user \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer YOUR_GENERATED_TOKEN'
```

Example using JavaScript (Fetch API):
```javascript
fetch('http://your-app.com/api/cms/user', {
    method: 'GET',
    headers: {
        'Accept': 'application/json',
        'Authorization': 'Bearer YOUR_GENERATED_TOKEN'
    }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

## 7. Available API Routes (CMS Framework)

The ArtisanPack UI CMS Framework package exposes the following API routes, which are protected by Sanctum authentication and prefixed with /api/cms:

- GET /api/cms/user: Returns the authenticated user's information.
- GET /api/cms/some-protected-data: Returns example protected data.
- GET /api/cms/settings: Retrieves all currently active settings. 
- GET /api/cms/settings/{key}: Retrieves a specific setting by its key.
- POST /api/cms/settings: Stores a new setting. (Note: This is intended for programmatic default registration; it will not overwrite existing settings).
- PUT /api/cms/settings/{key}: Updates an existing setting.
- DELETE /api/cms/settings/{key}: Deletes a setting by its key.

By following these steps, you can effectively leverage Laravel Sanctum for secure API communication within your application, powered by the ArtisanPack UI CMS Framework.
