<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * #822 — a template saved from the site editor stores its template-part
 * references as `artisanpack/template-part`, often with a stale
 * `innerBlocks` snapshot of the part. Both front-end components must
 * resolve those references against the live parts instead of rendering
 * empty wrappers.
 */

/**
 * Bind a slug-keyed stub under cms-framework's TemplatePartResolver
 * FQCN so `TemplatePartInliner::findPartBlocks()` resolves from memory.
 *
 * @param  array<string, array<int, array<string, mixed>>>  $parts
 */
function bindSavedTemplatePartStub( array $parts ): void
{
	$stub = new class( $parts ) {
		/**
		 * @param  array<string, array<int, array<string, mixed>>>  $parts
		 */
		public function __construct( private array $parts ) {}

		public function resolve( string $slug ): ?object
		{
			return isset( $this->parts[ $slug ] ) ? (object) [ 'blocks' => $this->parts[ $slug ] ] : null;
		}
	};

	app()->bind(
		'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver',
		fn () => $stub,
	);
}

/**
 * @return array<string, mixed>
 */
function savedPartParagraph( string $content ): array
{
	return [ 'clientId' => 'p-' . md5( $content ), 'name' => 'core/paragraph', 'attributes' => [ 'content' => $content ], 'innerBlocks' => [] ];
}

/**
 * The shape the site editor persists into `templates.block_content`.
 *
 * @return array<int, array<string, mixed>>
 */
function savedTemplateTree(): array
{
	return [
		[
			'clientId'    => 'tp-header',
			'name'        => 'artisanpack/template-part',
			'attributes'  => [ 'slug' => 'header', 'theme' => 'artisanpack-ui', 'tagName' => 'header' ],
			'innerBlocks' => [ savedPartParagraph( 'Stale header snapshot' ) ],
		],
		savedPartParagraph( 'Post body' ),
		[
			'clientId'    => 'tp-footer',
			'name'        => 'artisanpack/template-part',
			'attributes'  => [ 'slug' => 'footer', 'theme' => 'artisanpack-ui', 'tagName' => 'footer' ],
			'innerBlocks' => [ savedPartParagraph( 'Stale footer snapshot' ) ],
		],
	];
}

beforeEach( function (): void {
	bindSavedTemplatePartStub( [
		'header' => [ savedPartParagraph( 'Live header' ) ],
		'footer' => [ savedPartParagraph( 'Live footer' ) ],
	] );
} );

it( 'renders fork-named template parts from a saved template through x-ve-blocks', function () {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="artisanpack-ui" />', [ 'tree' => savedTemplateTree() ] );

	expect( $rendered )
		->toContain( '<p class="wp-block-paragraph">Live header</p>' )
		->toContain( '<p class="wp-block-paragraph">Live footer</p>' )
		->toContain( 'data-ve-template-part="header"' )
		->toContain( 'data-ve-template-part="footer"' )
		->not->toContain( 'Stale header snapshot' )
		->not->toContain( 'Stale footer snapshot' );

	expect( $rendered )->toMatch( '/<header[^>]*data-ve-template-part="header"[^>]*>\s*<p class="wp-block-paragraph">Live header<\/p>\s*<\/header>/' );
} );

it( 'still renders core/template-part references from a theme-file template', function () {
	$tree = [
		[ 'name' => 'core/template-part', 'attributes' => [ 'slug' => 'header', 'tagName' => 'header' ], 'innerBlocks' => [] ],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="artisanpack-ui" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( '<p class="wp-block-paragraph">Live header</p>' );
} );

it( 'emits the dev-mode warning for a fork-named part with an unknown slug', function () {
	$tree = [
		[ 'name' => 'artisanpack/template-part', 'attributes' => [ 'slug' => 'sidebar', 'tagName' => 'aside' ], 'innerBlocks' => [ savedPartParagraph( 'Stale sidebar' ) ] ],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="artisanpack-ui" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( '<!-- visual-editor: template part "sidebar" failed to resolve (not-found) -->' )
		->not->toContain( 'Stale sidebar' );
} );

it( 'renders fork-named template parts from a saved template through x-ve-template', function () {
	$template = new class( savedTemplateTree() ) {
		/**
		 * @param  array<int, array<string, mixed>>  $blocks
		 */
		public function __construct( private array $blocks ) {}

		public function resolve( string $slug ): ?object
		{
			return 'single-post' === $slug ? (object) [ 'slug' => $slug, 'blocks' => $this->blocks ] : null;
		}
	};

	// TemplateComponent looks its resolver up under the leading-backslash FQCN.
	app()->bind( '\\ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplateResolver', fn () => $template );

	$rendered = Blade::render( '<x-ve-template slug="single-post" theme="artisanpack-ui" />' );

	expect( $rendered )
		->toContain( '<p class="wp-block-paragraph">Live header</p>' )
		->toContain( '<p class="wp-block-paragraph">Post body</p>' )
		->toContain( '<p class="wp-block-paragraph">Live footer</p>' )
		->not->toContain( 'Stale header snapshot' );
} );
