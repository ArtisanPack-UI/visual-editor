<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\VisualEditor\Resources\TemplatePartInliner;
use ArtisanPackUI\VisualEditor\Support\BlockMarkupHydrator;

/**
 * #688 — a block theme's `parts/header.html` resolves with `blocks`
 * empty and only its raw markup populated, because cms-framework's
 * resolver doesn't parse theme files. The inliner used to splice that
 * in as an empty wrapper; it now hydrates the raw markup instead.
 */

/**
 * Bind a stand-in part resolver that returns `$entity` for any slug.
 */
function bindPartResolver( ?ResolvedEntity $entity ): void
{
	app()->instance( TemplatePartResolver::class, new class( $entity ) extends TemplatePartResolver
	{
		public function __construct( private readonly ?ResolvedEntity $entity )
		{
		}

		public function resolve( string $slug ): ?ResolvedEntity
		{
			return $this->entity;
		}
	} );
}

/**
 * @param  array<int, array<string, mixed>>  $blocks
 */
function themeFilePart( string $raw, array $blocks = [] ): ResolvedEntity
{
	return new ResolvedEntity(
		slug: 'header',
		theme: 'artisanpack-ui',
		source: 'theme',
		raw: $raw,
		blocks: $blocks,
		title: 'Header',
		description: '',
		status: 'publish',
		hasThemeFile: true,
		isCustom: false,
		area: 'header',
		model: null,
	);
}

it( 'hydrates a theme-file part whose resolved blocks are empty', function () {
	bindPartResolver( themeFilePart(
		'<!-- wp:artisanpack/paragraph --><p>Site header</p><!-- /wp:artisanpack/paragraph -->'
	) );

	$tree = ( new TemplatePartInliner() )->inline( [
		[ 'name' => 'core/template-part', 'attributes' => [ 'slug' => 'header' ], 'innerBlocks' => [] ],
	] );

	expect( $tree[0]['innerBlocks'] )->toHaveCount( 1 );
	expect( $tree[0]['innerBlocks'][0]['name'] )->toBe( 'artisanpack/paragraph' );
	expect( $tree[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Site header' );
} )->skip( fn () => ! BlockMarkupHydrator::canParseMarkup(), 'requires cms-framework 2.5+ (PHP 8.3+) for BlockMarkupParser' );

it( 'prefers already-parsed blocks over the raw fallback', function () {
	bindPartResolver( themeFilePart(
		'<!-- wp:artisanpack/paragraph --><p>From raw</p><!-- /wp:artisanpack/paragraph -->',
		[ [ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => 'From blocks' ], 'innerBlocks' => [] ] ],
	) );

	$tree = ( new TemplatePartInliner() )->inline( [
		[ 'name' => 'core/template-part', 'attributes' => [ 'slug' => 'header' ], 'innerBlocks' => [] ],
	] );

	expect( $tree[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'From blocks' );
} );

it( 'still marks the part unresolved when nothing resolves at all', function () {
	bindPartResolver( null );

	$tree = ( new TemplatePartInliner() )->inline( [
		[ 'name' => 'core/template-part', 'attributes' => [ 'slug' => 'header' ], 'innerBlocks' => [] ],
	] );

	expect( $tree[0]['attributes']['_resolutionError'] )->toBe( TemplatePartInliner::ERROR_NOT_FOUND );
} );

it( 'marks the part unresolved when the raw markup hydrates to nothing', function () {
	bindPartResolver( themeFilePart( '   ' ) );

	$tree = ( new TemplatePartInliner() )->inline( [
		[ 'name' => 'core/template-part', 'attributes' => [ 'slug' => 'header' ], 'innerBlocks' => [] ],
	] );

	expect( $tree[0]['innerBlocks'] )->toBe( [] );
} );
