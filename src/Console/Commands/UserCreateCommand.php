<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Create a new CMS user command
 * 
 * This command allows creating new users for the CMS with role assignment.
 * It supports interactive mode for user input and validation.
 */
class UserCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:user:create 
                           {username? : The username for the user}
                           {email? : The email address for the user}
                           {--role=user : The role to assign to the user}
                           {--password= : The password for the user (if not provided, will be prompted)}
                           {--first-name= : The first name of the user}
                           {--last-name= : The last name of the user}
                           {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new CMS user with role assignment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Creating a new CMS user...');
            
            // Get user input
            $userData = $this->getUserData();
            
            // Validate the data
            $validator = $this->validateUserData($userData);
            
            if ($validator->fails()) {
                $this->error('Validation failed:');
                foreach ($validator->errors()->all() as $error) {
                    $this->error('  ' . $error);
                }
                return Command::FAILURE;
            }
            
            // Check if role exists
            $role = Role::where('slug', $userData['role'])->first();
            if (!$role) {
                $this->error("Role '{$userData['role']}' does not exist.");
                $this->info('Available roles: ' . Role::pluck('slug')->join(', '));
                return Command::FAILURE;
            }
            
            // Show summary and confirm
            if (!$this->option('force')) {
                $this->displayUserSummary($userData, $role);
                if (!$this->confirm('Create this user?', true)) {
                    $this->info('User creation cancelled.');
                    return Command::SUCCESS;
                }
            }
            
            // Create the user
            $user = User::create([
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'role_id' => $role->id,
                'status' => 'active',
            ]);
            
            $this->info("✅ User '{$user->username}' created successfully!");
            $this->info("   ID: {$user->id}");
            $this->info("   Email: {$user->email}");
            $this->info("   Role: {$role->name}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error creating user: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Get user data from arguments, options, or interactive prompts
     */
    private function getUserData(): array
    {
        return [
            'username' => $this->argument('username') ?: $this->ask('Username'),
            'email' => $this->argument('email') ?: $this->ask('Email address'),
            'password' => $this->option('password') ?: $this->secret('Password'),
            'first_name' => $this->option('first-name') ?: $this->ask('First name (optional)'),
            'last_name' => $this->option('last-name') ?: $this->ask('Last name (optional)'),
            'role' => $this->option('role') ?: $this->askForRole(),
        ];
    }
    
    /**
     * Ask user to select a role
     */
    private function askForRole(): string
    {
        $roles = Role::pluck('name', 'slug')->toArray();
        
        if (empty($roles)) {
            $this->warn('No roles found. Using default "user" role.');
            return 'user';
        }
        
        $roleChoice = $this->choice(
            'Select a role',
            array_keys($roles),
            'user'
        );
        
        // Find the slug for the selected role name
        return array_search($roleChoice, $roles) ?: 'user';
    }
    
    /**
     * Validate user data
     */
    private function validateUserData(array $data): \Illuminate\Validation\Validator
    {
        return Validator::make($data, [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'role' => 'required|string|exists:roles,slug',
        ]);
    }
    
    /**
     * Display user summary before creation
     */
    private function displayUserSummary(array $userData, Role $role): void
    {
        $this->info('User Summary:');
        $this->table(
            ['Field', 'Value'],
            [
                ['Username', $userData['username']],
                ['Email', $userData['email']],
                ['First Name', $userData['first_name'] ?: 'Not provided'],
                ['Last Name', $userData['last_name'] ?: 'Not provided'],
                ['Role', $role->name . ' (' . $role->slug . ')'],
                ['Password', str_repeat('*', strlen($userData['password']))],
            ]
        );
    }
}