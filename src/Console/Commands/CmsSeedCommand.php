<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\User;
use ArtisanPackUI\CMSFramework\Models\Role;
use ArtisanPackUI\CMSFramework\Models\Content;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * CMS Seed command
 * 
 * This command seeds the CMS with essential data including roles, users, and content.
 * It provides options for different seeding scenarios and demo data.
 */
class CmsSeedCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:seed 
                           {--type=all : Seed type (roles, users, content, demo, all)}
                           {--count=10 : Number of records to create for demo data}
                           {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     */
    protected $description = 'Seed CMS with essential data (roles, users, content)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $type = $this->option('type');
            $count = (int) $this->option('count');
            
            $this->info('Starting CMS seeding...');
            
            // Perform seeding based on type
            switch ($type) {
                case 'roles':
                    $this->seedRoles();
                    break;
                    
                case 'users':
                    $this->seedUsers();
                    break;
                    
                case 'content':
                    $this->seedContent($count);
                    break;
                    
                case 'demo':
                    $this->seedDemoData($count);
                    break;
                    
                case 'all':
                    $this->seedRoles();
                    $this->seedUsers();
                    $this->seedContent($count);
                    break;
                    
                default:
                    $this->error("Invalid seed type '{$type}'.");
                    $this->info('Valid types: roles, users, content, demo, all');
                    return Command::FAILURE;
            }
            
            $this->info("✅ CMS seeding completed successfully!");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error during CMS seeding: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Seed roles
     */
    private function seedRoles(): void
    {
        $this->info('Seeding roles...');
        
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full system access',
                'capabilities' => [
                    'manage_users', 'manage_content', 'manage_settings',
                    'manage_themes', 'manage_plugins', 'view_analytics'
                ]
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Content management access',
                'capabilities' => [
                    'manage_content', 'publish_content', 'edit_content',
                    'delete_content', 'upload_files'
                ]
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Content creation access',
                'capabilities' => [
                    'create_content', 'edit_own_content', 'publish_own_content',
                    'upload_files'
                ]
            ],
            [
                'name' => 'Contributor',
                'slug' => 'contributor',
                'description' => 'Limited content creation',
                'capabilities' => [
                    'create_content', 'edit_own_content'
                ]
            ],
            [
                'name' => 'Subscriber',
                'slug' => 'subscriber',
                'description' => 'Read-only access',
                'capabilities' => [
                    'read_content'
                ]
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'description' => 'Basic user access',
                'capabilities' => [
                    'read_content', 'comment_content'
                ]
            ]
        ];
        
        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'capabilities' => json_encode($roleData['capabilities'])
                ]
            );
            
            $this->line("  - Created role: {$role->name}");
        }
        
        $this->info('Roles seeded successfully!');
    }
    
    /**
     * Seed users
     */
    private function seedUsers(): void
    {
        $this->info('Seeding users...');
        
        // Ensure roles exist
        $adminRole = Role::where('slug', 'admin')->first();
        $editorRole = Role::where('slug', 'editor')->first();
        $userRole = Role::where('slug', 'user')->first();
        
        if (!$adminRole || !$editorRole || !$userRole) {
            $this->warn('Required roles not found. Seeding roles first...');
            $this->seedRoles();
            
            $adminRole = Role::where('slug', 'admin')->first();
            $editorRole = Role::where('slug', 'editor')->first();
            $userRole = Role::where('slug', 'user')->first();
        }
        
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@cms.local'],
            [
                'username' => 'admin',
                'first_name' => 'CMS',
                'last_name' => 'Administrator',
                'password' => Hash::make('admin123'),
                'role_id' => $adminRole->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        
        $this->line("  - Created admin user: {$admin->username} (admin@cms.local)");
        
        // Create editor user
        $editor = User::firstOrCreate(
            ['email' => 'editor@cms.local'],
            [
                'username' => 'editor',
                'first_name' => 'Content',
                'last_name' => 'Editor',
                'password' => Hash::make('editor123'),
                'role_id' => $editorRole->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        
        $this->line("  - Created editor user: {$editor->username} (editor@cms.local)");
        
        // Create regular user
        $user = User::firstOrCreate(
            ['email' => 'user@cms.local'],
            [
                'username' => 'user',
                'first_name' => 'Regular',
                'last_name' => 'User',
                'password' => Hash::make('user123'),
                'role_id' => $userRole->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        
        $this->line("  - Created regular user: {$user->username} (user@cms.local)");
        
        $this->info('Users seeded successfully!');
        $this->warn('Default passwords: admin123, editor123, user123');
    }
    
    /**
     * Seed content
     */
    private function seedContent(int $count = 10): void
    {
        $this->info("Seeding {$count} content items...");
        
        // Get a user to assign as author
        $author = User::first();
        if (!$author) {
            $this->warn('No users found. Creating admin user first...');
            $this->seedUsers();
            $author = User::first();
        }
        
        $contentTypes = ['post', 'page', 'article', 'news'];
        $statuses = ['published', 'draft', 'published', 'published']; // Bias toward published
        
        for ($i = 1; $i <= $count; $i++) {
            $type = $contentTypes[array_rand($contentTypes)];
            $status = $statuses[array_rand($statuses)];
            
            $title = $this->generateContentTitle($type, $i);
            $content = $this->generateContentBody($type);
            $excerpt = $this->generateExcerpt($content);
            
            $contentItem = Content::create([
                'title' => $title,
                'slug' => Str::slug($title) . '-' . $i,
                'content' => $content,
                'excerpt' => $excerpt,
                'type' => $type,
                'status' => $status,
                'author_id' => $author->id,
                'meta_title' => $title,
                'meta_description' => $excerpt,
                'published_at' => $status === 'published' ? now()->subDays(rand(0, 30)) : null,
                'created_at' => now()->subDays(rand(0, 60)),
            ]);
            
            $this->line("  - Created {$type}: {$title}");
        }
        
        $this->info('Content seeded successfully!');
    }
    
    /**
     * Seed demo data
     */
    private function seedDemoData(int $count = 10): void
    {
        $this->info('Seeding demo data...');
        
        if (!$this->option('force')) {
            if (!$this->confirm('This will create demo data including users, roles, and content. Continue?', true)) {
                $this->info('Demo data seeding cancelled.');
                return;
            }
        }
        
        // Seed everything for demo
        $this->seedRoles();
        $this->seedUsers();
        $this->seedContent($count * 2); // More content for demo
        
        // Create additional demo users
        $this->createDemoUsers();
        
        $this->info('Demo data seeded successfully!');
        $this->warn('Demo includes test users with default passwords. Change them in production!');
    }
    
    /**
     * Create additional demo users
     */
    private function createDemoUsers(): void
    {
        $this->info('Creating additional demo users...');
        
        $roles = Role::all();
        $demoUsers = [
            ['John', 'Doe', 'john.doe'],
            ['Jane', 'Smith', 'jane.smith'],
            ['Mike', 'Johnson', 'mike.johnson'],
            ['Sarah', 'Wilson', 'sarah.wilson'],
            ['Tom', 'Brown', 'tom.brown'],
        ];
        
        foreach ($demoUsers as $userData) {
            $role = $roles->random();
            
            $user = User::firstOrCreate(
                ['email' => $userData[2] . '@demo.local'],
                [
                    'username' => $userData[2],
                    'first_name' => $userData[0],
                    'last_name' => $userData[1],
                    'password' => Hash::make('demo123'),
                    'role_id' => $role->id,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            
            $this->line("  - Created demo user: {$user->username} ({$role->name})");
        }
    }
    
    /**
     * Generate content title
     */
    private function generateContentTitle(string $type, int $index): string
    {
        $titles = [
            'post' => [
                'Getting Started with CMS',
                'Advanced Content Management',
                'Best Practices for Content Creation',
                'Optimizing Your Content Strategy',
                'Content Marketing in 2024',
            ],
            'page' => [
                'About Us',
                'Contact Information',
                'Privacy Policy',
                'Terms of Service',
                'FAQ',
            ],
            'article' => [
                'The Future of Web Development',
                'Understanding Modern CMS Architecture',
                'Building Scalable Web Applications',
                'User Experience Design Principles',
                'Performance Optimization Techniques',
            ],
            'news' => [
                'Latest CMS Updates Released',
                'New Features Announcement',
                'Community Spotlight',
                'Developer Conference Highlights',
                'Industry Trends Report',
            ]
        ];
        
        $typeTitle = $titles[$type] ?? ['Sample Content'];
        $baseTitle = $typeTitle[array_rand($typeTitle)];
        
        return $baseTitle . ' #' . $index;
    }
    
    /**
     * Generate content body
     */
    private function generateContentBody(string $type): string
    {
        $paragraphs = [
            "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.",
            "Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
            "Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt explicabo.",
            "Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem sequi nesciunt.",
            "Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem."
        ];
        
        $numParagraphs = rand(2, 4);
        $selectedParagraphs = array_rand($paragraphs, $numParagraphs);
        
        if (!is_array($selectedParagraphs)) {
            $selectedParagraphs = [$selectedParagraphs];
        }
        
        $content = '';
        foreach ($selectedParagraphs as $index) {
            $content .= $paragraphs[$index] . "\n\n";
        }
        
        return trim($content);
    }
    
    /**
     * Generate excerpt from content
     */
    private function generateExcerpt(string $content): string
    {
        $words = explode(' ', strip_tags($content));
        $excerptWords = array_slice($words, 0, 20);
        
        return implode(' ', $excerptWords) . (count($words) > 20 ? '...' : '');
    }
}