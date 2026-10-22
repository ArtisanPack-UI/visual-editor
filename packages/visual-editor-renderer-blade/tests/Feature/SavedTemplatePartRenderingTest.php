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
		public function __construct( private array $parts )
		{
		}

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

it( 'renders fork-named template parts from a saved template through x-ve-blocks', function (): void {
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

it( 'still renders core/template-part references from a theme-file template', function (): void {
	$tree = [
		[ 'name' => 'core/template-part', 'attributes' => [ 'slug' => 'header', 'tagName' => 'header' ], 'innerBlocks' => [] ],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="artisanpack-ui" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( '<p class="wp-block-paragraph">Live header</p>' );
} );

it( 'emits the dev-mode warning for a fork-named part with an unknown slug', function (): void {
	$tree = [
		[ 'name' => 'artisanpack/template-part', 'attributes' => [ 'slug' => 'sidebar', 'tagName' => 'aside' ], 'innerBlocks' => [ savedPartParagraph( 'Stale sidebar' ) ] ],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="artisanpack-ui" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toContain( '<!-- visual-editor: template part "sidebar" failed to resolve (not-found) -->' )
		->not->toContain( 'Stale sidebar' );
} );

it( 'renders fork-named template parts from a saved template through x-ve-template', function (): void {
	$template = new class( savedTemplateTree() ) {
		/**
		 * @param  array<int, array<string, mixed>>  $blocks
		 */
		public function __construct( private array $blocks )
		{
		}

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

/**
 * #976 — a sticky site header has to be sticky on the template-part
 * wrapper itself: a sticky block inside the part is held by a wrapper
 * exactly its own height, so it never sticks. Theme-file templates name
 * the block `core/template-part`, templates saved in the site editor the
 * `artisanpack/template-part` fork; both must keep the attributes through
 * inlining and print them on the wrapper.
 */
it( 'prints a sticky position with offsets and z-index on the template-part wrapper (#976)', function ( string $name ): void {
	$tree = [
		[
			'name'        => $name,
			'attributes'  => [
				'slug'    => 'header',
				'tagName' => 'header',
				'style'   => [
					'position' => [
						'value'   => 'sticky',
						'offsets' => [ 'top' => [ 'value' => 0, 'unit' => 'px' ] ],
						'zIndex'  => 50,
					],
				],
			],
			'innerBlocks' => [ savedPartParagraph( 'Stale header snapshot' ) ],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="artisanpack-ui" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toMatch( '/<header class="wp-block-template-part wp-block-template-part--header ve-pos-[a-f0-9]+" style="position: sticky !important; top: 0px !important; z-index: 50 !important;" data-ve-template-part="header"[^>]*>\s*<p class="wp-block-paragraph">Live header<\/p>\s*<\/header>/' )
		->toMatch( '/<style data-ve-position>\.ve-pos-[a-f0-9]+\{position:sticky !important;top:0px !important;z-index:50 !important\}<\/style>/' )
		->not->toContain( 'Stale header snapshot' );
} )->with( [ 'core/template-part', 'artisanpack/template-part' ] );

it( 'prints an Effects z-index on the template-part wrapper (#976)', function (): void {
	$tree = [
		[
			'name'        => 'core/template-part',
			'attributes'  => [
				'slug'               => 'header',
				'tagName'            => 'header',
				'style'              => [ 'position' => [ 'value' => 'sticky', 'offsets' => [ 'top' => [ 'value' => 0, 'unit' => 'px' ] ] ] ],
				'artisanpackEffects' => [ 'zIndex' => 40 ],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="artisanpack-ui" />', [ 'tree' => $tree ] );

	expect( $rendered )
		->toMatch( '/<header class="wp-block-template-part wp-block-template-part--header ve-pos-[a-f0-9]+ ve-fx-[a-f0-9]+" style="position: sticky !important; top: 0px !important;"/' )
		->toMatch( '/\.ve-fx-[a-f0-9]+\{z-index:40\}/' );
} );

it( 'keeps the position on a template part that fails to resolve (#976)', function (): void {
	$tree = [
		[
			'name'        => 'core/template-part',
			'attributes'  => [
				'slug'    => 'sidebar',
				'tagName' => 'aside',
				'style'   => [ 'position' => [ 'value' => 'sticky', 'offsets' => [ 'top' => [ 'value' => 2, 'unit' => 'rem' ] ] ] ],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" default-theme="artisanpack-ui" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'style="position: sticky !important; top: 2rem !important;"' );
} );
