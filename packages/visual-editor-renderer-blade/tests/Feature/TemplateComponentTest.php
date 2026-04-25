<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;
use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses( RefreshDatabase::class );

function bladeTemplateFixture( string $slug, array $blocks, string $theme = 'artisanpack-base' ): VisualEditorTemplate
{
	return VisualEditorTemplate::create( [
		'slug'    => $slug,
		'title'   => ucfirst( $slug ),
		'content' => [ 'raw' => '', 'blocks' => $blocks ],
		'theme'   => $theme,
		'source'  => 'theme',
		'origin'  => 'theme',
	] );
}

function bladeTemplatePartFixture( string $slug, array $blocks, string $theme = 'artisanpack-base' ): VisualEditorTemplatePart
{
	return VisualEditorTemplatePart::create( [
		'slug'    => $slug,
		'title'   => ucfirst( $slug ),
		'content' => [ 'raw' => '', 'blocks' => $blocks ],
		'area'    => 'uncategorized',
		'theme'   => $theme,
	] );
}

function bladeTplParagraph( string $text, string $clientId = 'p-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => $text ],
		'innerBlocks' => [],
	];
}

function bladeTplPartRef( string $slug, string $theme = 'artisanpack-base', string $clientId = 'tp-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/template-part',
		'attributes'  => [ 'slug' => $slug, 'theme' => $theme ],
		'innerBlocks' => [],
	];
}

it( 'renders the matched template with template-parts inlined', function () {
	bladeTemplatePartFixture( 'header', [ bladeTplParagraph( 'Header content', 'p-h' ) ] );
	bladeTemplateFixture( 'page-about', [ bladeTplPartRef( 'header' ), bladeTplParagraph( 'About body', 'p-b' ) ] );

	$rendered = Blade::render( '<x-ve-template slug="page-about" theme="artisanpack-base" />' );
	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( 'data-ve-template="page-about"' );
	expect( $normalized )->toContain( 'wp-block-template-part--header' );
	expect( $normalized )->toContain( '<p class="wp-block-paragraph">Header content</p>' );
	expect( $normalized )->toContain( '<p class="wp-block-paragraph">About body</p>' );
} );

it( 'walks the fallback chain (page-about → page → index)', function () {
	bladeTemplateFixture( 'page', [ bladeTplParagraph( 'Default page' ) ] );
	bladeTemplateFixture( 'index', [ bladeTplParagraph( 'Catch-all index' ) ] );

	$rendered = Blade::render( '<x-ve-template slug="page-about" theme="artisanpack-base" />' );
	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( 'data-ve-matched-template="page"' );
	expect( $normalized )->toContain( '<p class="wp-block-paragraph">Default page</p>' );
	expect( $normalized )->not()->toContain( 'Catch-all index' );
} );

it( 'falls back to index when nothing more specific exists', function () {
	bladeTemplateFixture( 'index', [ bladeTplParagraph( 'Catch-all index' ) ] );

	$rendered = Blade::render( '<x-ve-template slug="archive" theme="artisanpack-base" />' );

	expect( $this->normalizeHtml( $rendered ) )->toContain( '<p class="wp-block-paragraph">Catch-all index</p>' );
} );

it( 'renders an empty wrapper in production when nothing resolves', function () {
	$this->app['env'] = 'production';

	$rendered = Blade::render( '<x-ve-template slug="single-post" theme="artisanpack-base" />' );
	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( 'data-ve-template="single-post"' );
	expect( $normalized )->not()->toContain( 'failed to resolve' );
} );

it( 'surfaces the failure as an HTML comment in development', function () {
	$this->app['env'] = 'local';

	$rendered = Blade::render( '<x-ve-template slug="single-post" theme="artisanpack-base" />' );

	expect( $rendered )->toContain( 'failed to resolve (no-matching-template)' );
	expect( $rendered )->toContain( 'fallback chain tried: single-post, single, index' );
} );

it( 'matches templates by slug only when no theme is provided', function () {
	bladeTemplateFixture( 'index', [ bladeTplParagraph( 'Single-theme index' ) ], 'theme-x' );

	$rendered = Blade::render( '<x-ve-template slug="archive" />' );

	expect( $this->normalizeHtml( $rendered ) )->toContain( 'Single-theme index' );
} );
