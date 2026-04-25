<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses( RefreshDatabase::class );

function bladePartFixture( string $slug, array $blocks, string $theme = 'artisanpack-base', string $area = 'uncategorized' ): VisualEditorTemplatePart
{
	return VisualEditorTemplatePart::create( [
		'slug'    => $slug,
		'title'   => ucfirst( $slug ),
		'content' => [ 'raw' => '', 'blocks' => $blocks ],
		'area'    => $area,
		'theme'   => $theme,
	] );
}

function bladePartRef( string $slug, string $theme = 'artisanpack-base' ): array
{
	return [
		'clientId'    => 'tp-' . $slug,
		'name'        => 'core/template-part',
		'attributes'  => [ 'slug' => $slug, 'theme' => $theme ],
		'innerBlocks' => [],
	];
}

function bladeParagraph( string $text, string $clientId = 'p-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => $text ],
		'innerBlocks' => [],
	];
}

it( 'inlines a core/template-part reference inside <x-ve-blocks>', function () {
	bladePartFixture( 'header', [ bladeParagraph( 'Header text' ) ] );

	$tree = [ bladePartRef( 'header' ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( '<div class="wp-block-template-part wp-block-template-part--header"' );
	expect( $normalized )->toContain( 'data-ve-template-part="header"' );
	expect( $normalized )->toContain( '<p class="wp-block-paragraph">Header text</p>' );
} );

it( 'skips inlining when :resolve-parts is false', function () {
	bladePartFixture( 'header', [ bladeParagraph( 'Header text' ) ] );

	$tree = [ bladePartRef( 'header' ) ];

	$rendered = Blade::render(
		'<x-ve-blocks :tree="$tree" :resolve-parts="false" />',
		[ 'tree' => $tree ]
	);

	expect( $this->normalizeHtml( $rendered ) )->not()->toContain( 'Header text' );
} );

it( 'falls back to default-theme when the reference omits theme', function () {
	bladePartFixture( 'header', [ bladeParagraph( 'Themed header' ) ], 'artisanpack-base' );

	$tree = [
		[
			'clientId'    => 'tp-1',
			'name'        => 'core/template-part',
			'attributes'  => [ 'slug' => 'header' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render(
		'<x-ve-blocks :tree="$tree" default-theme="artisanpack-base" />',
		[ 'tree' => $tree ]
	);

	expect( $this->normalizeHtml( $rendered ) )->toContain( 'Themed header' );
} );

it( 'renders an empty wrapper when the part is missing in production', function () {
	$this->app['env'] = 'production';

	$tree = [ bladePartRef( 'never-created' ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
	$normalized = $this->normalizeHtml( $rendered );

	expect( $normalized )->toContain( 'wp-block-template-part' );
	expect( $normalized )->not()->toContain( 'failed to resolve' );
} );

it( 'surfaces a comment warning when the part is missing in development', function () {
	$this->app['env'] = 'local';

	$tree = [ bladePartRef( 'never-created' ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'failed to resolve (not-found)' );
} );

it( 'renders a cycle marker without infinite recursion', function () {
	$this->app['env'] = 'local';

	bladePartFixture( 'a', [ bladePartRef( 'b' ) ] );
	bladePartFixture( 'b', [ bladePartRef( 'a' ) ] );

	$tree = [ bladePartRef( 'a' ) ];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	// The two wrappers prove the resolver returned (no infinite recursion);
	// the dev-mode comment proves the cycle guard was the reason the chain
	// terminated rather than a part lookup that happened to fail.
	expect( $rendered )->toContain( 'wp-block-template-part--a' );
	expect( $rendered )->toContain( 'wp-block-template-part--b' );
	expect( $rendered )->toContain( 'failed to resolve (cycle)' );
} );
