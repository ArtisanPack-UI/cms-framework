<?php

declare( strict_types=1 );

namespace ArtisanPackUI\CMSFramework\Tests\Unit\Updates;

use ArtisanPackUI\CMSFramework\Modules\Core\Updates\Enums\UpdateType;
use Orchestra\Testbench\TestCase;
use ValueError;

/**
 * Update Type Enum Tests
 *
 * @since 1.1.0
 */
class UpdateTypeTest extends TestCase
{
    /**
     * Test enum has the correct cases.
     *
     * @since 1.1.0
     */
    public function test_has_correct_cases(): void
    {
        $cases = UpdateType::cases();

        $this->assertCount( 3, $cases );
        $this->assertContains( UpdateType::Application, $cases );
        $this->assertContains( UpdateType::Plugin, $cases );
        $this->assertContains( UpdateType::Theme, $cases );
    }

    /**
     * Test enum has correct string values.
     *
     * @since 1.1.0
     */
    public function test_has_correct_string_values(): void
    {
        $this->assertEquals( 'application', UpdateType::Application->value );
        $this->assertEquals( 'plugin', UpdateType::Plugin->value );
        $this->assertEquals( 'theme', UpdateType::Theme->value );
    }

    /**
     * Test enum can be created from valid string values.
     *
     * @since 1.1.0
     */
    public function test_can_be_created_from_valid_string(): void
    {
        $this->assertSame( UpdateType::Application, UpdateType::from( 'application' ) );
        $this->assertSame( UpdateType::Plugin, UpdateType::from( 'plugin' ) );
        $this->assertSame( UpdateType::Theme, UpdateType::from( 'theme' ) );
    }

    /**
     * Test enum throws exception for invalid string value.
     *
     * @since 1.1.0
     */
    public function test_throws_exception_for_invalid_string(): void
    {
        $this->expectException( ValueError::class );

        UpdateType::from( 'invalid' );
    }

    /**
     * Test tryFrom returns null for invalid string value.
     *
     * @since 1.1.0
     */
    public function test_try_from_returns_null_for_invalid_string(): void
    {
        $this->assertNull( UpdateType::tryFrom( 'invalid'));
    }
}
