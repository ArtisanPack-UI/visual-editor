<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\VisualEditor\Support\BlockMarkupHydrator;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

/**
 * #688 — the end-to-end server-side path a block theme depends on:
 * raw `templates/*.html` markup in, rendered HTML out. Before the
 * hydrator existed these same inputs produced structurally-correct but
 * textless output (or, for the verbatim `parse_blocks()` shape, nothing
 * at all).
 */

it( 'renders paragraph text that the markup carries only in its saved HTML', function () {
	$markup = <<<'HTML'
	<!-- wp:artisanpack/paragraph {"textColor":"text-muted"} -->
	<p class="has-text-muted-color has-text-color">Supporting subheading.</p>
	<!-- /wp:artisanpack/paragraph -->
	HTML;

	$html = app( BlockRenderer::class )->renderMarkup( $markup );

	expect( $html )->toContain( 'Supporting subheading.' );
	expect( $html )->toContain( 'wp-block-paragraph' );
} )->skip( fn () => ! BlockMarkupHydrator::canParseMarkup(), 'requires cms-framework 2.5+ (PHP 8.3+) for BlockMarkupParser' );

it( 'renders a whole theme-style template including nested blocks', function () {
	$markup = <<<'HTML'
	<!-- wp:artisanpack/group -->
	<div class="wp-block-group">
	<!-- wp:artisanpack/heading {"level":1} --><h1 class="wp-block-heading">Welcome</h1><!-- /wp:artisanpack/heading -->
	<!-- wp:artisanpack/paragraph --><p>Body copy.</p><!-- /wp:artisanpack/paragraph -->
	</div>
	<!-- /wp:artisanpack/group -->
	HTML;

	$html = app( BlockRenderer::class )->renderMarkup( $markup );

	expect( $html )->toContain( 'Welcome' );
	expect( $html )->toContain( 'Body copy.' );
	expect( $html )->toContain( '<h1' );
} )->skip( fn () => ! BlockMarkupHydrator::canParseMarkup(), 'requires cms-framework 2.5+ (PHP 8.3+) for BlockMarkupParser' );

it( 'renders core-namespaced theme markup', function () {
	$html = app( BlockRenderer::class )->renderMarkup(
		'<!-- wp:paragraph --><p>HELLO</p><!-- /wp:paragraph -->'
	);

	expect( $html )->toContain( 'HELLO' );
} )->skip( fn () => ! BlockMarkupHydrator::canParseMarkup(), 'requires cms-framework 2.5+ (PHP 8.3+) for BlockMarkupParser' );

it( 'renders the saved markup of a core/html block verbatim', function () {
	// #690 — `core/html` keeps everything in its saved HTML and declares
	// no wrapper supports, so the manifest's `raw` source has to recover
	// `content` and the partial has to emit it untouched.
	$html = app( BlockRenderer::class )->renderMarkup(
		'<!-- wp:html --><div class="x">Raw HTML</div><!-- /wp:html -->'
	);

	expect( $html )->toContain( '<div class="x">Raw HTML</div>' );
	expect( $html )->not->toContain( 'data-ve-unknown-block' );
} )->skip( fn () => ! BlockMarkupHydrator::canParseMarkup(), 'requires cms-framework 2.5+ (PHP 8.3+) for BlockMarkupParser' );

it( 'renders an artisanpack/html block the same way as its core alias', function () {
	$html = app( BlockRenderer::class )->renderMarkup(
		'<!-- wp:artisanpack/html --><p><em>Markup</em> kept as written.</p><!-- /wp:artisanpack/html -->'
	);

	expect( $html )->toContain( '<p><em>Markup</em> kept as written.</p>' );
} )->skip( fn () => ! BlockMarkupHydrator::canParseMarkup(), 'requires cms-framework 2.5+ (PHP 8.3+) for BlockMarkupParser' );

it( 'renders a core/html block from an editor-shape tree', function () {
	$html = app( BlockRenderer::class )->render( [
		[
			'name'        => 'core/html',
			'attributes'  => [ 'content' => '<span data-x="1">Inline</span>' ],
			'innerBlocks' => [],
		],
	] );

	expect( $html )->toContain( '<span data-x="1">Inline</span>' );
} );

it( 'renders nothing for a core/html block with no saved content', function () {
	$html = app( BlockRenderer::class )->render( [
		[ 'name' => 'core/html', 'attributes' => [], 'innerBlocks' => [] ],
	] );

	expect( trim( $html ) )->toBe( '' );
} );

it( 'returns an empty string for blank markup', function () {
	expect( app( BlockRenderer::class )->renderMarkup( '' ) )->toBe( '' );
	expect( app( BlockRenderer::class )->renderMarkup( "  \n " ) )->toBe( '' );
} );

it( 'inlines template-part references instead of emitting an empty wrapper', function () {
	// The `.html`-sourced part is the #688 case: it resolves with
	// `blocks` empty and only its raw markup populated, so without
	// both halves of the fix wired together this renders as
	// `<header></header>`.
	$resolver = new class extends TemplatePartResolver
	{
		public function __construct()
		{
		}

		public function resolve( string $slug ): ?ResolvedEntity
		{
			return new ResolvedEntity(
				slug: $slug,
				theme: 'dev-sample',
				source: 'theme',
				raw: '<!-- wp:artisanpack/paragraph --><p>Header content</p><!-- /wp:artisanpack/paragraph -->',
				blocks: [],
				title: 'Header',
				description: '',
				status: 'publish',
				hasThemeFile: true,
				isCustom: false,
				area: 'header',
				model: null,
			);
		}
	};

	app()->instance( TemplatePartResolver::class, $resolver );

	$html = app( BlockRenderer::class )->renderMarkup(
		'<!-- wp:template-part {"slug":"header","tagName":"header"} /-->',
		'dev-sample',
	);

	expect( $html )->toContain( 'Header content' );
} )->skip( fn () => ! BlockMarkupHydrator::canParseMarkup(), 'requires cms-framework 2.5+ (PHP 8.3+) for BlockMarkupParser' );

it( 'still renders an editor-shape tree passed straight to render()', function () {
	$html = app( BlockRenderer::class )->render( [
		[ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => 'HELLO' ], 'innerBlocks' => [] ],
	] );

	expect( $html )->toContain( 'HELLO' );
} );
