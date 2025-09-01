<?php

namespace ArtisanPackUI\CMSFramework\Console\Commands;

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Create content command
 * 
 * This command allows creating new content items for the CMS.
 * It supports different content types and interactive input.
 */
class ContentCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:content:create 
                           {title? : The title of the content}
                           {--type=post : The content type (post, page, etc.)}
                           {--author= : The author username or ID}
                           {--status=draft : The content status (draft, published, scheduled)}
                           {--content= : The content body}
                           {--excerpt= : The content excerpt}
                           {--slug= : Custom slug for the content}
                           {--meta-title= : SEO meta title}
                           {--meta-description= : SEO meta description}
                           {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     */
    protected $description = 'Create new content for the CMS';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Creating new content...');
            
            // Get content data
            $contentData = $this->getContentData();
            
            // Validate the data
            if (!$this->validateContentData($contentData)) {
                return Command::FAILURE;
            }
            
            // Get the author
            $author = $this->getAuthor($contentData['author']);
            if (!$author) {
                return Command::FAILURE;
            }
            
            // Show summary and confirm
            if (!$this->option('force')) {
                $this->displayContentSummary($contentData, $author);
                if (!$this->confirm('Create this content?', true)) {
                    $this->info('Content creation cancelled.');
                    return Command::SUCCESS;
                }
            }
            
            // Create the content
            $content = Content::create([
                'title' => $contentData['title'],
                'content' => $contentData['content'],
                'excerpt' => $contentData['excerpt'],
                'slug' => $contentData['slug'],
                'type' => $contentData['type'],
                'status' => $contentData['status'],
                'author_id' => $author->id,
                'meta_title' => $contentData['meta_title'],
                'meta_description' => $contentData['meta_description'],
                'published_at' => $contentData['status'] === 'published' ? now() : null,
            ]);
            
            $this->info("✅ Content created successfully!");
            $this->info("   ID: {$content->id}");
            $this->info("   Title: {$content->title}");
            $this->info("   Type: {$content->type}");
            $this->info("   Status: {$content->status}");
            $this->info("   Slug: {$content->slug}");
            $this->info("   Author: {$author->username}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error creating content: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Get content data from arguments, options, or interactive prompts
     */
    private function getContentData(): array
    {
        $title = $this->argument('title') ?: $this->ask('Content title');
        $type = $this->option('type') ?: $this->askForContentType();
        
        return [
            'title' => $title,
            'type' => $type,
            'content' => $this->option('content') ?: $this->askForContent(),
            'excerpt' => $this->option('excerpt') ?: $this->ask('Excerpt (optional)'),
            'slug' => $this->option('slug') ?: $this->generateSlug($title),
            'status' => $this->option('status') ?: $this->askForStatus(),
            'author' => $this->option('author') ?: $this->ask('Author (username or ID)', auth()->user()?->username ?? 'admin'),
            'meta_title' => $this->option('meta-title') ?: $this->ask('SEO meta title (optional)'),
            'meta_description' => $this->option('meta-description') ?: $this->ask('SEO meta description (optional)'),
        ];
    }
    
    /**
     * Ask for content type
     */
    private function askForContentType(): string
    {
        $types = ['post', 'page', 'article', 'news', 'event'];
        
        return $this->choice(
            'Select content type',
            $types,
            'post'
        );
    }
    
    /**
     * Ask for content body
     */
    private function askForContent(): string
    {
        $this->info('Enter the content body (press Enter twice when finished):');
        
        $content = '';
        $emptyLines = 0;
        
        while (true) {
            $line = $this->ask('');
            
            if (empty($line)) {
                $emptyLines++;
                if ($emptyLines >= 2) {
                    break;
                }
                $content .= "\n";
            } else {
                $emptyLines = 0;
                $content .= $line . "\n";
            }
        }
        
        return trim($content);
    }
    
    /**
     * Ask for content status
     */
    private function askForStatus(): string
    {
        $statuses = ['draft', 'published', 'scheduled', 'private'];
        
        return $this->choice(
            'Select content status',
            $statuses,
            'draft'
        );
    }
    
    /**
     * Generate slug from title
     */
    private function generateSlug(string $title): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;
        
        // Ensure slug is unique
        while (Content::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Get author user
     */
    private function getAuthor(string $identifier): ?User
    {
        // Try to find by ID first if numeric
        if (is_numeric($identifier)) {
            $user = User::find($identifier);
            if ($user) {
                return $user;
            }
        }
        
        // Try to find by username
        $user = User::where('username', $identifier)->first();
        if ($user) {
            return $user;
        }
        
        $this->error("Author '{$identifier}' not found.");
        return null;
    }
    
    /**
     * Validate content data
     */
    private function validateContentData(array $data): bool
    {
        if (empty($data['title'])) {
            $this->error('Title is required.');
            return false;
        }
        
        if (empty($data['content'])) {
            $this->error('Content body is required.');
            return false;
        }
        
        $validStatuses = ['draft', 'published', 'scheduled', 'private'];
        if (!in_array($data['status'], $validStatuses)) {
            $this->error('Invalid status. Valid options: ' . implode(', ', $validStatuses));
            return false;
        }
        
        // Check if slug already exists
        if (Content::where('slug', $data['slug'])->exists()) {
            $this->error("Slug '{$data['slug']}' already exists.");
            return false;
        }
        
        return true;
    }
    
    /**
     * Display content summary before creation
     */
    private function displayContentSummary(array $contentData, User $author): void
    {
        $this->info('Content Summary:');
        $this->table(
            ['Field', 'Value'],
            [
                ['Title', $contentData['title']],
                ['Type', $contentData['type']],
                ['Status', $contentData['status']],
                ['Slug', $contentData['slug']],
                ['Author', $author->username . ' (' . $author->email . ')'],
                ['Excerpt', $contentData['excerpt'] ? Str::limit($contentData['excerpt'], 50) : 'None'],
                ['Content Length', strlen($contentData['content']) . ' characters'],
                ['Meta Title', $contentData['meta_title'] ?: 'None'],
                ['Meta Description', $contentData['meta_description'] ? Str::limit($contentData['meta_description'], 50) : 'None'],
            ]
        );
    }
}