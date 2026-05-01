<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\Exceptions\SiteEditorRegistrationException;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\GlobalStylesResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\MenuResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\PatternResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedGlobalStyles;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedMenu;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedPattern;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplate;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;

afterEach( function (): void {
	foreach ( [
		'ap.visual-editor.templates',
		'ap.visual-editor.template-parts',
		'ap.visual-editor.patterns',
		'ap.visual-editor.global-styles',
		'ap.visual-editor.navigation',
	] as $filter ) {
		removeAllFilters( $filter );
	}

	config()->set( 'artisanpack.visual-editor.site-editor', [
		'templates'      => [],
		'template-parts' => [],
		'patterns'       => [],
		'global-styles'  => null,
		'navigation'     => [],
	] );
} );

function rebuildSiteEditorResolvers(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

describe( 'standalone install (no contributors, no static config)', function (): void {
	it( 'boots cleanly with empty resolvers for every entity type', function (): void {
		rebuildSiteEditorResolvers();

		expect( app( TemplateResolver::class )->all() )->toBe( [] )
			->and( app( TemplatePartResolver::class )->all() )->toBe( [] )
			->and( app( PatternResolver::class )->all() )->toBe( [] )
			->and( app( GlobalStylesResolver::class )->get() )->toBeNull()
			->and( app( MenuResolver::class )->all() )->toBe( [] );
	} );
} );

describe( 'single-source filter contribution', function (): void {
	it( 'registers a template via the templates filter', function (): void {
		addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
			return array_merge( [
				'page' => [
					'slug'   => 'page',
					'theme'  => 'digital-shopfront',
					'title'  => 'Page',
					'source' => 'theme',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$template = app( TemplateResolver::class )->find( 'page' );

		expect( $template )->toBeInstanceOf( ResolvedTemplate::class )
			->and( $template->slug )->toBe( 'page' )
			->and( $template->theme )->toBe( 'digital-shopfront' )
			->and( $template->source )->toBe( 'theme' );
	} );

	it( 'registers a template-part with area', function (): void {
		addFilter( 'ap.visual-editor.template-parts', function ( array $existing ): array {
			return array_merge( [
				'header' => [
					'slug'   => 'header',
					'theme'  => 'digital-shopfront',
					'title'  => 'Header',
					'area'   => 'header',
					'source' => 'theme',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$part = app( TemplatePartResolver::class )->find( 'header' );

		expect( $part )->toBeInstanceOf( ResolvedTemplatePart::class )
			->and( $part->area )->toBe( 'header' );
	} );

	it( 'registers a pattern with source flag', function (): void {
		addFilter( 'ap.visual-editor.patterns', function ( array $existing ): array {
			return array_merge( [
				'cta' => [
					'slug'   => 'cta',
					'title'  => 'Call to Action',
					'source' => 'theme',
					'synced' => false,
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$pattern = app( PatternResolver::class )->find( 'cta' );

		expect( $pattern )->toBeInstanceOf( ResolvedPattern::class )
			->and( $pattern->source )->toBe( 'theme' )
			->and( $pattern->synced )->toBeFalse();
	} );

	it( 'registers global-styles as a singleton', function (): void {
		addFilter( 'ap.visual-editor.global-styles', function ( $existing ) {
			return $existing ?? [
				'theme'    => 'digital-shopfront',
				'settings' => [ 'color' => [ 'palette' => [] ] ],
				'styles'   => [],
			];
		} );

		rebuildSiteEditorResolvers();

		$globals = app( GlobalStylesResolver::class )->get();

		expect( $globals )->toBeInstanceOf( ResolvedGlobalStyles::class )
			->and( $globals->theme )->toBe( 'digital-shopfront' );
	} );

	it( 'registers a menu under its location', function (): void {
		addFilter( 'ap.visual-editor.navigation', function ( array $existing ): array {
			return array_merge( [
				'primary' => [
					'location' => 'primary',
					'name'     => 'Primary Menu',
					'items'    => [],
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$menu = app( MenuResolver::class )->find( 'primary' );

		expect( $menu )->toBeInstanceOf( ResolvedMenu::class )
			->and( $menu->location )->toBe( 'primary' )
			->and( $menu->name )->toBe( 'Primary Menu' );
	} );
} );

describe( 'static config wins on key collision', function (): void {
	it( 'overrides a filter-contributed template when the same slug is in static config', function (): void {
		config()->set( 'artisanpack.visual-editor.site-editor.templates', [
			'page' => [
				'slug'   => 'page',
				'theme'  => 'host-theme',
				'title'  => 'Host Page',
				'source' => 'theme',
			],
		] );

		addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
			return array_merge( [
				'page' => [
					'slug'   => 'page',
					'theme'  => 'cms-framework-theme',
					'title'  => 'CMS Page',
					'source' => 'theme',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$template = app( TemplateResolver::class )->find( 'page' );

		expect( $template->theme )->toBe( 'host-theme' )
			->and( $template->title )->toBe( 'Host Page' );
	} );

	it( 'merges non-colliding static + filter entries', function (): void {
		config()->set( 'artisanpack.visual-editor.site-editor.templates', [
			'host-page' => [
				'slug'   => 'host-page',
				'theme'  => 'host-theme',
				'title'  => 'Host Page',
				'source' => 'theme',
			],
		] );

		addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
			return array_merge( [
				'cms-page' => [
					'slug'   => 'cms-page',
					'theme'  => 'cms-theme',
					'title'  => 'CMS Page',
					'source' => 'theme',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$resolver = app( TemplateResolver::class );

		expect( $resolver->find( 'host-page' )->theme )->toBe( 'host-theme' )
			->and( $resolver->find( 'cms-page' )->theme )->toBe( 'cms-theme' );
	} );

	it( 'static global-styles config wins outright over filter contribution', function (): void {
		config()->set( 'artisanpack.visual-editor.site-editor.global-styles', [
			'theme'    => 'host-theme',
			'settings' => [ 'host' => true ],
			'styles'   => [],
		] );

		addFilter( 'ap.visual-editor.global-styles', function ( $existing ) {
			return $existing ?? [
				'theme'    => 'cms-theme',
				'settings' => [ 'cms' => true ],
				'styles'   => [],
			];
		} );

		rebuildSiteEditorResolvers();

		$globals = app( GlobalStylesResolver::class )->get();

		expect( $globals->theme )->toBe( 'host-theme' )
			->and( $globals->settings )->toBe( [ 'host' => true ] );
	} );
} );

describe( 'lazy validation on first read', function (): void {
	it( 'does not throw at boot when a filter returns malformed entries', function (): void {
		addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
			return array_merge( [
				'broken' => 'this-is-not-an-array',
			], $existing );
		} );

		// Rebuild must succeed even with a malformed contribution.
		rebuildSiteEditorResolvers();

		expect( app( TemplateResolver::class ) )->toBeInstanceOf( TemplateResolver::class );
	} );

	it( 'throws SiteEditorRegistrationException on first all() with a malformed entry', function (): void {
		addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
			return array_merge( [
				'broken' => 'this-is-not-an-array',
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		expect( fn () => app( TemplateResolver::class )->all() )
			->toThrow( SiteEditorRegistrationException::class );
	} );

	it( 'throws when a template entry is missing the required theme field', function (): void {
		addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
			return array_merge( [
				'no-theme' => [
					'slug'   => 'no-theme',
					'title'  => 'No Theme',
					'source' => 'theme',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		expect( fn () => app( TemplateResolver::class )->all() )
			->toThrow( SiteEditorRegistrationException::class, 'theme' );
	} );

	it( 'throws when a template-part has an invalid area', function (): void {
		addFilter( 'ap.visual-editor.template-parts', function ( array $existing ): array {
			return array_merge( [
				'banner' => [
					'slug'   => 'banner',
					'theme'  => 'test',
					'area'   => 'invalid-area',
					'source' => 'theme',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		expect( fn () => app( TemplatePartResolver::class )->all() )
			->toThrow( SiteEditorRegistrationException::class, 'area' );
	} );

	it( 'throws when a pattern has an invalid source value', function (): void {
		addFilter( 'ap.visual-editor.patterns', function ( array $existing ): array {
			return array_merge( [
				'bad' => [
					'slug'   => 'bad',
					'source' => 'wherever',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		expect( fn () => app( PatternResolver::class )->all() )
			->toThrow( SiteEditorRegistrationException::class, 'source' );
	} );

	it( 'throws when a pattern is missing the title field', function (): void {
		addFilter( 'ap.visual-editor.patterns', function ( array $existing ): array {
			return array_merge( [
				'titleless' => [
					'slug'   => 'titleless',
					'source' => 'theme',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		expect( fn () => app( PatternResolver::class )->all() )
			->toThrow( SiteEditorRegistrationException::class, 'title' );
	} );

	it( 'normalizes pattern blocks when content.blocks is not an array', function (): void {
		// A misconfigured contributor passes a string in the nested
		// content.blocks slot — should resolve to an empty blocks array
		// instead of raising a TypeError on the typed constructor param.
		addFilter( 'ap.visual-editor.patterns', function ( array $existing ): array {
			return array_merge( [
				'malformed' => [
					'slug'    => 'malformed',
					'title'   => 'Malformed',
					'source'  => 'theme',
					'content' => [ 'blocks' => 'not-an-array' ],
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$pattern = app( PatternResolver::class )->find( 'malformed' );

		expect( $pattern->blocks )->toBe( [] );
	} );

	it( 'safely handles a scalar content envelope on patterns', function (): void {
		// content is a string instead of an array. Without the defensive
		// is_array guard, $data['content']['raw'] would index into the
		// string and silently corrupt rawContent to its first character.
		addFilter( 'ap.visual-editor.patterns', function ( array $existing ): array {
			return array_merge( [
				'scalar-content' => [
					'slug'    => 'scalar-content',
					'title'   => 'Scalar Content',
					'source'  => 'theme',
					'content' => 'this should be an array',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$pattern = app( PatternResolver::class )->find( 'scalar-content' );

		expect( $pattern->rawContent )->toBe( '' )
			->and( $pattern->blocks )->toBe( [] );
	} );

	it( 'safely handles a scalar content envelope on templates', function (): void {
		addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
			return array_merge( [
				'scalar-template' => [
					'slug'    => 'scalar-template',
					'theme'   => 'host',
					'source'  => 'theme',
					'content' => 'malformed string',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$template = app( TemplateResolver::class )->find( 'scalar-template' );

		expect( $template->rawContent )->toBe( '' )
			->and( $template->blocks )->toBe( [] );
	} );

	it( 'safely handles a scalar content envelope on template parts', function (): void {
		addFilter( 'ap.visual-editor.template-parts', function ( array $existing ): array {
			return array_merge( [
				'scalar-part' => [
					'slug'    => 'scalar-part',
					'theme'   => 'host',
					'area'    => 'header',
					'source'  => 'theme',
					'content' => 'malformed string',
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$part = app( TemplatePartResolver::class )->find( 'scalar-part' );

		expect( $part->rawContent )->toBe( '' )
			->and( $part->blocks )->toBe( [] );
	} );

	it( 'does not stringify a non-scalar raw_content to "Array" on patterns', function (): void {
		// A contributor passes an array under `raw_content` (or under a
		// nested `content.raw`). Without the is_scalar guard,
		// `(string) $array` produces the literal `"Array"` and ships
		// silently — the resolver should fall back to the empty default
		// instead.
		addFilter( 'ap.visual-editor.patterns', function ( array $existing ): array {
			return array_merge( [
				'array-raw' => [
					'slug'        => 'array-raw',
					'title'       => 'Array Raw',
					'source'      => 'theme',
					'raw_content' => [ 'unexpected' => 'array' ],
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$pattern = app( PatternResolver::class )->find( 'array-raw' );

		expect( $pattern->rawContent )->toBe( '' )
			->and( $pattern->rawContent )->not->toBe( 'Array' );
	} );

	it( 'does not stringify a non-scalar nested content.raw to "Array" on templates', function (): void {
		addFilter( 'ap.visual-editor.templates', function ( array $existing ): array {
			return array_merge( [
				'array-content-raw' => [
					'slug'    => 'array-content-raw',
					'theme'   => 'host',
					'source'  => 'theme',
					'content' => [
						'raw' => [ 'unexpected' => 'array' ],
					],
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$template = app( TemplateResolver::class )->find( 'array-content-raw' );

		expect( $template->rawContent )->toBe( '' );
	} );

	it( 'coerces scalar non-string raw_content (int/float/bool) to a string on patterns', function (): void {
		// is_scalar accepts int, float, bool — these are sensibly stringable
		// and shouldn't be rejected. Verifies the coercion path exposes the
		// stringified value rather than dropping it.
		addFilter( 'ap.visual-editor.patterns', function ( array $existing ): array {
			return array_merge( [
				'numeric-raw' => [
					'slug'        => 'numeric-raw',
					'title'       => 'Numeric Raw',
					'source'      => 'theme',
					'raw_content' => 42,
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$pattern = app( PatternResolver::class )->find( 'numeric-raw' );

		expect( $pattern->rawContent )->toBe( '42' );
	} );

	it( 'throws when global-styles is missing the theme field', function (): void {
		addFilter( 'ap.visual-editor.global-styles', function ( $existing ) {
			return $existing ?? [
				'settings' => [],
				'styles'   => [],
			];
		} );

		rebuildSiteEditorResolvers();

		expect( fn () => app( GlobalStylesResolver::class )->get() )
			->toThrow( SiteEditorRegistrationException::class, 'theme' );
	} );

	it( 'auto-stamps the map key as location when a navigation entry omits the field', function (): void {
		addFilter( 'ap.visual-editor.navigation', function ( array $existing ): array {
			return array_merge( [
				'primary' => [
					'name' => 'Primary',
					// Missing 'location' — the resolver should fill it from the map key.
				],
			], $existing );
		} );

		rebuildSiteEditorResolvers();

		$menu = app( MenuResolver::class )->find( 'primary' );

		expect( $menu->location )->toBe( 'primary' );
	} );

	it( 'throws when a non-array filter return value would corrupt the resolver', function (): void {
		addFilter( 'ap.visual-editor.templates', function ( $existing ) {
			// Pathological contributor: returns a string.
			return 'oops';
		} );

		// The boot-side guard coerces non-array filter output back to an
		// empty array, so the resolver itself never sees the bad value —
		// this also means standalone install behavior is preserved when a
		// host's filter callback misfires.
		rebuildSiteEditorResolvers();

		expect( app( TemplateResolver::class )->all() )->toBe( [] );
	} );
} );
