<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Console\Command;

/**
 * Assign or change user role command
 * 
 * This command allows assigning or changing roles for existing users.
 * It supports finding users by username, email, or ID.
 */
class UserRoleAssignCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:user:role 
                           {user : The user identifier (username, email, or ID)}
                           {role? : The role slug to assign}
                           {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     */
    protected $description = 'Assign or change a user\'s role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $userIdentifier = $this->argument('user');
            $roleSlug = $this->argument('role');
            
            $this->info("Looking for user: {$userIdentifier}");
            
            // Find the user
            $user = $this->findUser($userIdentifier);
            
            if (!$user) {
                $this->error('User not found.');
                return Command::FAILURE;
            }
            
            $this->info("Found user: {$user->username} ({$user->email})");
            
            // Get current role
            $currentRole = $user->role;
            $this->info("Current role: " . ($currentRole ? $currentRole->name : 'No role assigned'));
            
            // Get the role to assign
            if (!$roleSlug) {
                $roleSlug = $this->askForRole();
            }
            
            $newRole = Role::where('slug', $roleSlug)->first();
            
            if (!$newRole) {
                $this->error("Role '{$roleSlug}' does not exist.");
                $this->info('Available roles: ' . Role::pluck('slug')->join(', '));
                return Command::FAILURE;
            }
            
            // Check if it's the same role
            if ($currentRole && $currentRole->id === $newRole->id) {
                $this->warn("User already has the role '{$newRole->name}'.");
                return Command::SUCCESS;
            }
            
            // Show summary and confirm
            if (!$this->option('force')) {
                $this->displayRoleChangeSummary($user, $currentRole, $newRole);
                if (!$this->confirm('Apply role change?', true)) {
                    $this->info('Role assignment cancelled.');
                    return Command::SUCCESS;
                }
            }
            
            // Update the user's role
            $user->role_id = $newRole->id;
            $user->save();
            
            $this->info("✅ Role updated successfully!");
            $this->info("   User: {$user->username} ({$user->email})");
            $this->info("   New Role: {$newRole->name}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error updating user role: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Find user by username, email, or ID
     */
    private function findUser(string $identifier): ?User
    {
        // Try to find by ID first if numeric
        if (is_numeric($identifier)) {
            $user = User::find($identifier);
            if ($user) {
                return $user;
            }
        }
        
        // Try to find by email if it looks like an email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $identifier)->first();
            if ($user) {
                return $user;
            }
        }
        
        // Try to find by username
        return User::where('username', $identifier)->first();
    }
    
    /**
     * Ask user to select a role
     */
    private function askForRole(): string
    {
        $roles = Role::pluck('name', 'slug')->toArray();
        
        if (empty($roles)) {
            $this->error('No roles found in the system.');
            throw new \Exception('No roles available');
        }
        
        $roleChoice = $this->choice(
            'Select a role to assign',
            array_values($roles)
        );
        
        // Find the slug for the selected role name
        return array_search($roleChoice, $roles);
    }
    
    /**
     * Display role change summary
     */
    private function displayRoleChangeSummary(User $user, ?Role $currentRole, Role $newRole): void
    {
        $this->info('Role Change Summary:');
        $this->table(
            ['Field', 'Value'],
            [
                ['User ID', $user->id],
                ['Username', $user->username],
                ['Email', $user->email],
                ['Current Role', $currentRole ? $currentRole->name . ' (' . $currentRole->slug . ')' : 'No role assigned'],
                ['New Role', $newRole->name . ' (' . $newRole->slug . ')'],
            ]
        );
    }
}