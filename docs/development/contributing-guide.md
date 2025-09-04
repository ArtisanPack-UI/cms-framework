---
title: Contributing Guide
---

# Contributing Guide

Thank you for considering contributing to the ArtisanPack UI CMS Framework! This guide will help you get started with contributing to the project.

## Code of Conduct

This project and everyone participating in it is governed by our Code of Conduct. By participating, you are expected to uphold this code. Please report unacceptable behavior to security@artisanpack.com.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, please include as many details as possible:

#### Bug Report Template

**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. Scroll down to '....'
4. See error

**Expected behavior**
A clear and concise description of what you expected to happen.

**Screenshots**
If applicable, add screenshots to help explain your problem.

**Environment:**
 - OS: [e.g. macOS, Windows, Ubuntu]
 - PHP Version: [e.g. 8.2.0]
 - Laravel Version: [e.g. 12.0]
 - CMS Framework Version: [e.g. 1.0.0]
 - Database: [e.g. MySQL 8.0, PostgreSQL 13]

**Additional context**
Add any other context about the problem here.

### Suggesting Enhancements

Enhancement suggestions are welcome! Please provide:

- **Clear description** of the enhancement
- **Use case** explaining why this would be useful
- **Implementation ideas** if you have any
- **Breaking changes** if any

### Pull Requests

We actively welcome your pull requests:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for your changes
5. Ensure all tests pass
6. Update documentation as needed
7. Commit your changes (`git commit -m 'Add amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL, PostgreSQL, or SQLite
- Git

### Local Development Environment

1. **Fork and clone the repository:**
   ```bash
   git clone https://github.com/your-username/cms-framework.git
   cd cms-framework
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies:**
   ```bash
   npm install
   ```

4. **Create environment file:**
   ```bash
   cp .env.example .env
   ```

5. **Configure your database in `.env`:**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=cms_framework_test
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

6. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

7. **Run migrations:**
   ```bash
   php artisan migrate
   ```

8. **Seed the database (optional):**
   ```bash
   php artisan db:seed
   ```

9. **Build frontend assets:**
   ```bash
   npm run dev
   ```

10. **Start the development server:**
    ```bash
    php artisan serve
    ```

### Docker Development

Alternatively, you can use Docker for development:

```bash
# Clone the repository
git clone https://github.com/your-username/cms-framework.git
cd cms-framework

# Start Docker containers
docker-compose up -d

# Install dependencies
docker-compose exec app composer install
docker-compose exec app npm install

# Run migrations
docker-compose exec app php artisan migrate --seed

# Build assets
docker-compose exec app npm run dev
```

## Development Guidelines

### Code Style

This project follows the ArtisanPack UI code style standards:

- **PHP**: Follow PSR-12 coding standards
- **Use real tabs** for indentation
- **Use Yoda conditions** (e.g., `if (true === $condition)`)
- **Use single quotes** unless variable escaping is required
- **Use PascalCase** for classes, **camelCase** for functions/variables
- **Use short array syntax** (`[]` instead of `array()`)
- **Add type declarations** wherever possible

#### Running Code Style Checks

```bash
# Check code style
vendor/bin/pint --test

# Fix code style issues
vendor/bin/pint
```

### Testing

We use Pest for testing. All new features and bug fixes must include tests.

#### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ContentManagementTest.php

# Run tests with coverage
php artisan test --coverage

# Run tests in parallel
php artisan test --parallel
```

#### Writing Tests

- **Feature tests** for testing user interactions and API endpoints
- **Unit tests** for testing individual classes and methods
- Use **descriptive test names** that explain what is being tested
- **Mock external dependencies** when appropriate
- **Test both happy path and error scenarios**

Example test structure:

```php
<?php

use ArtisanPackUI\CMSFramework\Models\Content;
use ArtisanPackUI\CMSFramework\Features\Content\ContentManager;

it('can create content with taxonomies', function () {
    $contentManager = app(ContentManager::class);
    
    $content = $contentManager->create([
        'title' => 'Test Post',
        'content' => 'Test content',
        'type' => 'post',
        'status' => 'published',
        'taxonomies' => [
            'category' => ['technology'],
            'tag' => ['laravel', 'php'],
        ],
    ]);
    
    expect($content)->toBeInstanceOf(Content::class);
    expect($content->title)->toBe('Test Post');
    expect($content->taxonomies)->toHaveCount(3);
});

it('throws exception when creating content without required fields', function () {
    $contentManager = app(ContentManager::class);
    
    $contentManager->create([]);
})->throws(ValidationException::class);
```

### Database

#### Migrations

- Use descriptive migration names
- Include both `up()` and `down()` methods
- Use proper column types and constraints
- Add indexes for commonly queried columns

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_content', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('status')->default('draft');
            $table->string('type')->default('post');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_content');
    }
};
```

#### Models

- Use proper relationships and eager loading
- Add appropriate casts for JSON columns
- Include PHPDoc blocks for properties
- Use accessor and mutator methods when needed

```php
<?php

namespace ArtisanPackUI\CMSFramework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string $excerpt
 * @property string $status
 * @property string $type
 * @property int $author_id
 * @property array $meta
 */
class Content extends Model
{
    protected $table = 'cms_content';
    
    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'status',
        'type',
        'author_id',
        'meta',
    ];
    
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
    
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
    
    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(Taxonomy::class, 'cms_content_taxonomy');
    }
}
```

### API Development

- Follow RESTful conventions
- Use proper HTTP status codes
- Include comprehensive validation
- Document all endpoints
- Use API resources for response formatting

```php
<?php

namespace ArtisanPackUI\CMSFramework\Http\Controllers\Api;

use ArtisanPackUI\CMSFramework\Http\Controllers\Controller;
use ArtisanPackUI\CMSFramework\Http\Requests\CreateContentRequest;
use ArtisanPackUI\CMSFramework\Http\Resources\ContentResource;
use ArtisanPackUI\CMSFramework\Features\Content\ContentManager;

class ContentController extends Controller
{
    public function __construct(
        private ContentManager $contentManager
    ) {}
    
    public function store(CreateContentRequest $request): ContentResource
    {
        $content = $this->contentManager->create($request->validated());
        
        return new ContentResource($content);
    }
}
```

### Documentation

- Update documentation when adding new features
- Include code examples in documentation
- Use clear and concise language
- Update API documentation for new endpoints

## Performance Guidelines

### Database Optimization

- Use eager loading to prevent N+1 queries
- Add appropriate database indexes
- Use database transactions for multiple operations
- Cache frequently accessed data

```php
// Good: Eager loading
$posts = Content::with(['author', 'taxonomies'])->get();

// Bad: N+1 query problem
$posts = Content::all();
foreach ($posts as $post) {
    echo $post->author->name; // This creates a query for each post
}
```

### Caching

- Cache expensive operations
- Use appropriate cache tags
- Implement cache invalidation strategies

```php
use Illuminate\Support\Facades\Cache;

// Cache with tags
$posts = Cache::tags(['content', 'posts'])->remember('recent_posts', 3600, function () {
    return Content::with('author')->latest()->take(10)->get();
});

// Invalidate cache when content changes
Cache::tags(['content'])->flush();
```

## Security Guidelines

- **Validate all input** from users
- **Sanitize output** to prevent XSS
- **Use prepared statements** for database queries
- **Implement proper authentication** and authorization
- **Follow OWASP guidelines**

```php
// Good: Using validation and authorization
public function update(UpdateContentRequest $request, Content $content): ContentResource
{
    $this->authorize('update', $content);
    
    $content = $this->contentManager->update($content->id, $request->validated());
    
    return new ContentResource($content);
}
```

## Commit Message Guidelines

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### Examples

```
feat(content): add content search functionality

Add ability to search content by title and content body using full-text search.

Closes #123

fix(auth): resolve 2FA token validation issue

The 2FA token validation was incorrectly handling time-based tokens.

Breaking change: Updated the 2FA validation method signature.

docs(api): update content management endpoints

Add examples for content creation with taxonomies and media attachments.
```

## Release Process

### Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** version when you make incompatible API changes
- **MINOR** version when you add functionality in a backward-compatible manner
- **PATCH** version when you make backward-compatible bug fixes

### Creating a Release

1. **Update version** in `composer.json`
2. **Update CHANGELOG.md** with new version and changes
3. **Create a tag**: `git tag -a v1.2.3 -m "Release version 1.2.3"`
4. **Push the tag**: `git push origin v1.2.3`
5. **Create GitHub release** with release notes

## Getting Help

### Community

- **GitHub Discussions**: Ask questions and share ideas
- **Discord**: Join our Discord server for real-time chat
- **Stack Overflow**: Tag questions with `artisanpack-ui`

### Documentation

- **Installation Guide**: [docs/installation.md](installation.md)
- **Configuration**: [docs/configuration.md](configuration.md)
- **Usage Guide**: [docs/usage.md](usage.md)
- **API Documentation**: [docs/api.md](api.md)

### Reporting Security Issues

Please do not report security vulnerabilities through public GitHub issues. Instead, send an email to security@artisanpack.com. All security vulnerabilities will be promptly addressed.

## Recognition

Contributors will be recognized in:

- **CHANGELOG.md** for significant contributions
- **README.md** in the Contributors section
- **GitHub Contributors** page

## License

By contributing to the ArtisanPack UI CMS Framework, you agree that your contributions will be licensed under the MIT License.

## Thank You

Your contributions make this project better for everyone. Thank you for taking the time to contribute!

---

**Questions?** Feel free to open an issue or start a discussion on GitHub.