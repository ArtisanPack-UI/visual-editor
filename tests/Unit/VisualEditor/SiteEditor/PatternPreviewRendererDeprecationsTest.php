<?php

/**
 * The protected PatternPreviewRenderer API removed in 1.15 is restored as
 * deprecated forwards to ThemeStylesheets, so subclasses keep working (R-5).
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\Previews\PatternPreviewCache;
use ArtisanPackUI\VisualEditor\SiteEditor\Previews\PatternPreviewRenderer;
use ArtisanPackUI\VisualEditor\SiteEditor\ThemeStylesheets;
use ArtisanPackUI\VisualEditor\Support\BlockMarkupHydrator;

it( 'keeps the deprecated themeBaseUrl() and constants for subclasses', function (): void {
	$this->mock( ThemeStylesheets::class, function ( $mock ): void {
		$mock->shouldReceive( 'baseUrl' )->andReturn( 'https://example.com/themes/acme/' );
	} );

	$renderer = new class( app( BlockMarkupHydrator::class ), app( PatternPreviewCache::class ) ) extends PatternPreviewRenderer {
		/**
		 * @return array{0: ?string, 1: string, 2: string}
		 */
		public function legacy(): array
		{
			return [ $this->themeBaseUrl(), self::STYLESHEET_READER_FQCN, self::THEME_ASSETS_ROUTE ];
		}
	};

	expect( $renderer->legacy() )->toBe( [
		'https://example.com/themes/acme/',
		ThemeStylesheets::STYLESHEET_READER_FQCN,
		ThemeStylesheets::THEME_ASSETS_ROUTE,
	] );
} );
