<?php

/**
 * Page Factory for the CMS Framework Pages Module.
 *
 * This factory generates fake page data for testing purposes,
 * including published, draft, and hierarchical states.
 *
 * @since   2.0.0
 */

namespace ArtisanPackUI\CMSFramework\Modules\Pages\Database\Factories;

use App\Models\User;
use ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for generating page test data.
 *
 * Provides states for different page statuses, templates, and hierarchical structures.
 *
 * @since 2.0.0
 *
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @since 2.0.0
     *
     * @var class-string<Page>
     */
    protected $model = Page::class;

    /**
     * Define the model's default state.
     *
     * Generates a published page by default with random content and metadata.
     *
     * @since 2.0.0
     *
     * @return array<string, mixed> The default page attributes.
     */
    public function definition(): array
    {
        $title = fake()->sentence(4, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => fake()->paragraphs(3, true),
            'excerpt' => fake()->paragraph(),
            'author_id' => User::factory(),
            'parent_id' => null,
            'order' => fake()->numberBetween(0, 100),
            'template' => 'default',
            'status' => 'published',
            'published_at' => now(),
            'metadata' => [
                'seo_title' => $title,
                'seo_description' => fake()->sentence(),
            ],
        ];
    }

    /**
     * Indicate that the page is a draft.
     *
     * Sets the status to 'draft' and clears the published_at timestamp.
     *
     * @since 2.0.0
     *
     * @return static The factory instance for method chaining.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the page is published.
     *
     * Sets the status to 'published' with a past published_at timestamp.
     *
     * @since 2.0.0
     *
     * @return static The factory instance for method chaining.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now()->subDays(rand(0, 365)),
        ]);
    }

    /**
     * Indicate that the page has a specific parent.
     *
     * @since 2.0.0
     *
     * @param  int  $parentId  The parent page's ID.
     * @return static The factory instance for method chaining.
     */
    public function withParent(int $parentId): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parentId,
        ]);
    }

    /**
     * Indicate that the page is a top-level page (no parent).
     *
     * @since 2.0.0
     *
     * @return static The factory instance for method chaining.
     */
    public function topLevel(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
        ]);
    }

    /**
     * Indicate that the page uses a specific template.
     *
     * @since 2.0.0
     *
     * @param  string  $template  The template name.
     * @return static The factory instance for method chaining.
     */
    public function withTemplate(string $template): static
    {
        return $this->state(fn (array $attributes) => [
            'template' => $template,
        ]);
    }

    /**
     * Indicate that the page has a specific author.
     *
     * @since 2.0.0
     *
     * @param  int  $authorId  The author's user ID.
     * @return static The factory instance for method chaining.
     */
    public function byAuthor(int $authorId): static
    {
        return $this->state(fn (array $attributes) => [
            'author_id' => $authorId,
        ]);
    }

    /**
     * Indicate that the page has a specific order.
     *
     * @since 2.0.0
     *
     * @param  int  $order  The order value.
     * @return static The factory instance for method chaining.
     */
    public function withOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }
}
