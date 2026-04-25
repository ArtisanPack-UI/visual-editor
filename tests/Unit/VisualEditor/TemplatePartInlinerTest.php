<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplatePart;
use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;

function inlinerMakePart( string $slug, array $blocks, string $theme = 'artisanpack-base', string $area = 'uncategorized' ): VisualEditorTemplatePart
{
	return VisualEditorTemplatePart::create( [
		'slug'    => $slug,
		'title'   => ucfirst( $slug ),
		'content' => [ 'raw' => '', 'blocks' => $blocks ],
		'area'    => $area,
		'theme'   => $theme,
	] );
}

function inlinerPartRef( string $slug, ?string $theme = null, string $clientId = 'tp-cid' ): array
{
	$attributes = [ 'slug' => $slug ];

	if ( null !== $theme ) {
		$attributes['theme'] = $theme;
	}

	return [
		'clientId'    => $clientId,
		'name'        => 'core/template-part',
		'attributes'  => $attributes,
		'innerBlocks' => [],
	];
}

function inlinerParagraph( string $text, string $clientId = 'p-cid' ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => $text ],
		'innerBlocks' => [],
	];
}

it( 'inlines a single template-part reference', function () {
	inlinerMakePart( 'header', [ inlinerParagraph( 'Header content', 'p-1' ) ] );

	$tree = [ inlinerPartRef( 'header', 'artisanpack-base' ) ];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	expect( $resolved )->toHaveCount( 1 );
	expect( $resolved[0]['name'] )->toBe( 'core/template-part' );
	expect( $resolved[0]['innerBlocks'] )->toHaveCount( 1 );
	expect( $resolved[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Header content' );
} );

it( 'falls back to defaultTheme when the reference omits its theme attribute', function () {
	inlinerMakePart( 'header', [ inlinerParagraph( 'Default theme header' ) ], 'artisanpack-base' );

	$tree = [ inlinerPartRef( 'header' ) ];

	$resolved = ( new TemplatePartInliner() )->inline( $tree, 'artisanpack-base' );

	expect( $resolved[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Default theme header' );
} );

it( 'descends into nested innerBlocks looking for template parts', function () {
	inlinerMakePart( 'header', [ inlinerParagraph( 'Inside group' ) ] );

	$tree = [
		[
			'clientId'    => 'g-1',
			'name'        => 'core/group',
			'attributes'  => [],
			'innerBlocks' => [ inlinerPartRef( 'header', 'artisanpack-base' ) ],
		],
	];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	expect( $resolved[0]['innerBlocks'][0]['name'] )->toBe( 'core/template-part' );
	expect( $resolved[0]['innerBlocks'][0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Inside group' );
} );

it( 'recurses through nested template parts', function () {
	inlinerMakePart( 'inner', [ inlinerParagraph( 'Innermost' ) ] );
	inlinerMakePart( 'outer', [ inlinerPartRef( 'inner', 'artisanpack-base' ) ] );

	$tree = [ inlinerPartRef( 'outer', 'artisanpack-base' ) ];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	expect( $resolved[0]['innerBlocks'][0]['name'] )->toBe( 'core/template-part' );
	expect( $resolved[0]['innerBlocks'][0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Innermost' );
} );

it( 'marks a missing slug attribute with the missing-slug error', function () {
	$tree = [
		[
			'clientId'    => 'tp-1',
			'name'        => 'core/template-part',
			'attributes'  => [ 'theme' => 'artisanpack-base' ],
			'innerBlocks' => [],
		],
	];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	expect( $resolved[0]['attributes']['_resolutionError'] )->toBe( TemplatePartInliner::ERROR_MISSING_SLUG );
	expect( $resolved[0]['innerBlocks'] )->toBe( [] );
} );

it( 'marks a missing record with the not-found error', function () {
	$tree = [ inlinerPartRef( 'never-created', 'artisanpack-base' ) ];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	expect( $resolved[0]['attributes']['_resolutionError'] )->toBe( TemplatePartInliner::ERROR_NOT_FOUND );
} );

it( 'detects a direct cycle (a → a)', function () {
	inlinerMakePart( 'looper', [ inlinerPartRef( 'looper', 'artisanpack-base' ) ] );

	$tree = [ inlinerPartRef( 'looper', 'artisanpack-base' ) ];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	expect( $resolved[0]['attributes']['_resolutionError'] ?? null )->toBeNull();
	expect( $resolved[0]['innerBlocks'][0]['attributes']['_resolutionError'] )
		->toBe( TemplatePartInliner::ERROR_CYCLE );
} );

it( 'detects an indirect cycle (a → b → a)', function () {
	inlinerMakePart( 'a', [ inlinerPartRef( 'b', 'artisanpack-base', 'b-ref' ) ] );
	inlinerMakePart( 'b', [ inlinerPartRef( 'a', 'artisanpack-base', 'a-ref' ) ] );

	$tree = [ inlinerPartRef( 'a', 'artisanpack-base', 'a-root' ) ];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	$cycleNode = $resolved[0]['innerBlocks'][0]['innerBlocks'][0];

	expect( $cycleNode['name'] )->toBe( 'core/template-part' );
	expect( $cycleNode['attributes']['_resolutionError'] )->toBe( TemplatePartInliner::ERROR_CYCLE );
} );

it( 'enforces the depth limit', function () {
	for ( $i = 0; $i < 5; $i++ ) {
		$nextRef = $i === 4 ? [] : [ inlinerPartRef( 'p' . ( $i + 1 ), 'artisanpack-base', 'p' . ( $i + 1 ) . '-ref' ) ];
		inlinerMakePart( 'p' . $i, $nextRef );
	}

	$tree = [ inlinerPartRef( 'p0', 'artisanpack-base', 'p0-root' ) ];

	$resolved = ( new TemplatePartInliner( 2 ) )->inline( $tree );

	$third = $resolved[0]['innerBlocks'][0]['innerBlocks'][0];

	expect( $third['name'] )->toBe( 'core/template-part' );
	expect( $third['attributes']['_resolutionError'] )->toBe( TemplatePartInliner::ERROR_DEPTH_LIMIT );
} );

it( 'preserves non-template-part blocks untouched', function () {
	$tree = [
		inlinerParagraph( 'Just text', 'p-only' ),
	];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	expect( $resolved )->toBe( $tree );
} );

it( 'returns an empty array when given non-array entries', function () {
	$tree = [ 'not-a-block', null, 42 ];

	$resolved = ( new TemplatePartInliner() )->inline( $tree );

	expect( $resolved )->toBe( [] );
} );
