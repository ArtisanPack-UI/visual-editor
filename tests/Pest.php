<?php

declare( strict_types=1 );

/**
 * Pest Test Configuration
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests
 *
 * @since      1.0.0
 */

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend( Tests\TestCase::class )
	->in( 'Feature', 'Unit' );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend( 'toBeOne', function () {
	return $this->toBe( 1 );
} );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// Stub for kses() from artisanpack-ui/security (not a direct dependency).
if ( ! function_exists( 'kses' ) ) {
	function kses( string $content ): string
	{
		return strip_tags( $content, '<strong><em><b><i><u><s><a><br><p><span><div><ul><ol><li>' );
	}
}
