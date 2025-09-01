<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use Illuminate\Console\Command;

/**
 * List CMS users command
 * 
 * This command displays a list of users with filtering options.
 * It supports filtering by role, status, and search terms.
 */
class UserListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:user:list 
                           {--role= : Filter by role slug}
                           {--status= : Filter by status (active, inactive, suspended)}
                           {--search= : Search users by username or email}
                           {--limit=20 : Limit number of results}
                           {--format=table : Output format (table, json, csv)}';

    /**
     * The console command description.
     */
    protected $description = 'List CMS users with filtering options';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Loading CMS users...');
            
            // Build query with filters
            $query = $this->buildQuery();
            
            // Get total count before limiting
            $totalCount = $query->count();
            
            // Apply limit
            $limit = (int) $this->option('limit');
            $users = $query->limit($limit)->get();
            
            if ($users->isEmpty()) {
                $this->warn('No users found matching the criteria.');
                return Command::SUCCESS;
            }
            
            // Display results
            $this->displayUsers($users, $totalCount, $limit);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error listing users: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Build the query with filters
     */
    private function buildQuery()
    {
        $query = User::with('role');
        
        // Filter by role
        if ($roleSlug = $this->option('role')) {
            $role = Role::where('slug', $roleSlug)->first();
            if (!$role) {
                $this->error("Role '{$roleSlug}' not found.");
                $this->info('Available roles: ' . Role::pluck('slug')->join(', '));
                throw new \Exception('Invalid role');
            }
            $query->where('role_id', $role->id);
        }
        
        // Filter by status
        if ($status = $this->option('status')) {
            $validStatuses = ['active', 'inactive', 'suspended'];
            if (!in_array($status, $validStatuses)) {
                $this->error("Invalid status '{$status}'.");
                $this->info('Valid statuses: ' . implode(', ', $validStatuses));
                throw new \Exception('Invalid status');
            }
            $query->where('status', $status);
        }
        
        // Search by username or email
        if ($search = $this->option('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }
        
        return $query->orderBy('created_at', 'desc');
    }
    
    /**
     * Display users in the requested format
     */
    private function displayUsers($users, int $totalCount, int $limit): void
    {
        $format = $this->option('format');
        
        // Show summary
        $this->info("Showing {$users->count()} of {$totalCount} users" . ($limit < $totalCount ? " (limited to {$limit})" : ""));
        $this->line('');
        
        switch ($format) {
            case 'json':
                $this->displayJson($users);
                break;
            case 'csv':
                $this->displayCsv($users);
                break;
            case 'table':
            default:
                $this->displayTable($users);
                break;
        }
    }
    
    /**
     * Display users as a table
     */
    private function displayTable($users): void
    {
        $headers = ['ID', 'Username', 'Email', 'Name', 'Role', 'Status', 'Created'];
        
        $rows = $users->map(function ($user) {
            return [
                $user->id,
                $user->username,
                $user->email,
                trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'N/A',
                $user->role ? $user->role->name : 'No Role',
                ucfirst($user->status ?? 'Unknown'),
                $user->created_at ? $user->created_at->format('Y-m-d H:i') : 'N/A',
            ];
        })->toArray();
        
        $this->table($headers, $rows);
    }
    
    /**
     * Display users as JSON
     */
    private function displayJson($users): void
    {
        $data = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'slug' => $user->role->slug,
                ] : null,
                'status' => $user->status,
                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
            ];
        });
        
        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }
    
    /**
     * Display users as CSV
     */
    private function displayCsv($users): void
    {
        // Headers
        $this->line('ID,Username,Email,First Name,Last Name,Role,Status,Created At');
        
        // Data rows
        foreach ($users as $user) {
            $row = [
                $user->id,
                $user->username,
                $user->email,
                $user->first_name ?? '',
                $user->last_name ?? '',
                $user->role ? $user->role->name : '',
                $user->status ?? '',
                $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '',
            ];
            
            // Escape fields that might contain commas
            $escapedRow = array_map(function ($field) {
                return strpos($field, ',') !== false ? '"' . str_replace('"', '""', $field) . '"' : $field;
            }, $row);
            
            $this->line(implode(',', $escapedRow));
        }
    }
}