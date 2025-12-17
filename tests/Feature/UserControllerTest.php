<?php

use ArtisanPackUI\CMSFramework\Modules\Users\Models\Role;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);

    // Set up configuration
    config(['cms-framework.user_model' => 'App\Models\User']);

    // Create a test user model class if it doesn't exist
    if (! class_exists('App\Models\User')) {
        eval('
            namespace App\Models {
                use Illuminate\Database\Eloquent\Model;
                use ArtisanPackUI\CMSFramework\Modules\Users\Models\Concerns\HasRolesAndPermissions;
                
                class User extends Model {
                    use HasRolesAndPermissions;
                    
                    protected $fillable = ["name", "email", "password"];
                    protected $hidden = ["password"];
                }
            }
        ');
    }
});

test('user controller index returns paginated users with roles', function () {
    $userModel = config('cms-framework.user_model');

    // Create test users
    $users = collect();
    for ($i = 1; $i <= 5; $i++) {
        $user = $userModel::create([
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'password' => Hash::make('password'),
        ]);
        $users->push($user);
    }

    // Create a role and assign to first user
    $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    $users->first()->roles()->attach($role);

    $response = $this->getJson('/api/v1/users');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at',
                'roles',
            ],
        ],
        'links',
        'meta',
    ]);

    expect($response->json('data'))->toHaveCount(5);
    expect($response->json('data.0.roles'))->toHaveCount(1);
    expect($response->json('data.0.roles.0.slug'))->toBe('admin');
});

test('user controller store creates new user with valid data', function () {
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
    ];

    $response = $this->postJson('/api/v1/users', $userData);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'name',
            'email',
            'email_verified_at',
            'created_at',
            'updated_at',
            'roles',
        ],
    ]);

    expect($response->json('data.name'))->toBe('John Doe');
    expect($response->json('data.email'))->toBe('john@example.com');

    // Verify user was created in database
    $userModel = config('cms-framework.user_model');
    $user = $userModel::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('user controller store validates required fields', function () {
    $response = $this->postJson('/api/v1/users', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('user controller store validates email uniqueness', function () {
    $userModel = config('cms-framework.user_model');

    // Create existing user
    $userModel::create([
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->postJson('/api/v1/users', [
        'name' => 'New User',
        'email' => 'existing@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('user controller store validates password length', function () {
    $response = $this->postJson('/api/v1/users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => '123', // Too short
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['password']);
});

test('user controller show returns specific user with roles', function () {
    $userModel = config('cms-framework.user_model');

    $user = $userModel::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => Hash::make('password'),
    ]);

    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $user->roles()->attach($role);

    $response = $this->getJson("/api/v1/users/{$user->id}");

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'name',
            'email',
            'email_verified_at',
            'created_at',
            'updated_at',
            'roles',
        ],
    ]);

    expect($response->json('data.id'))->toBe($user->id);
    expect($response->json('data.name'))->toBe('Jane Doe');
    expect($response->json('data.roles.0.slug'))->toBe('editor');
});

test('user controller show returns 404 for non-existent user', function () {
    $response = $this->getJson('/api/v1/users/999');

    $response->assertStatus(404);
});

test('user controller update modifies existing user', function () {
    $userModel = config('cms-framework.user_model');

    $user = $userModel::create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'password' => Hash::make('password'),
    ]);

    $updateData = [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ];

    $response = $this->putJson("/api/v1/users/{$user->id}", $updateData);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('New Name');
    expect($response->json('data.email'))->toBe('new@example.com');

    // Verify database was updated
    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
});

test('user controller update allows partial updates', function () {
    $userModel = config('cms-framework.user_model');

    $user = $userModel::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->putJson("/api/v1/users/{$user->id}", [
        'name' => 'Updated John',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Updated John');
    expect($response->json('data.email'))->toBe('john@example.com'); // Unchanged
});

test('user controller update validates email uniqueness excluding current user', function () {
    $userModel = config('cms-framework.user_model');

    $user1 = $userModel::create([
        'name' => 'User 1',
        'email' => 'user1@example.com',
        'password' => Hash::make('password'),
    ]);

    $user2 = $userModel::create([
        'name' => 'User 2',
        'email' => 'user2@example.com',
        'password' => Hash::make('password'),
    ]);

    // Try to update user2 with user1's email
    $response = $this->putJson("/api/v1/users/{$user2->id}", [
        'email' => 'user1@example.com',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('user controller update encrypts password when provided', function () {
    $userModel = config('cms-framework.user_model');

    $user = $userModel::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('oldpassword'),
    ]);

    $response = $this->putJson("/api/v1/users/{$user->id}", [
        'password' => 'newpassword123',
    ]);

    $response->assertStatus(200);

    $user->refresh();
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

test('user controller destroy deletes user', function () {
    $userModel = config('cms-framework.user_model');

    $user = $userModel::create([
        'name' => 'To Delete',
        'email' => 'delete@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->deleteJson("/api/v1/users/{$user->id}");

    $response->assertStatus(204);

    // Verify user was deleted
    expect($userModel::find($user->id))->toBeNull();
});

test('user controller destroy returns 404 for non-existent user', function () {
    $response = $this->deleteJson('/api/v1/users/999');

    $response->assertStatus(404);
});
