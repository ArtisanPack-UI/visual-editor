<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\DynamicContent\DynamicLoopBlock;
use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\DynamicContentSource;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\VisualEditorRendererBladeServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\FakeDynamicContentAccessor;

beforeEach( function (): void {
	// A real bindings registry with only the DC source so the resolver
	// can walk the template per iteration.
	$registry = new BlockBindingSourceRegistry();
	$registry->register( new DynamicContentSource() );

	$this->resolver = new BindingResolver( $registry );

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'team' => [
				[ 'name' => 'Alice' ],
				[ 'name' => 'Bob' ],
			],
		] ),
	);
} );

it( 'returns an empty string when no collection is set', function (): void {
	$block = new DynamicLoopBlock( $this->resolver );

	expect( (string) $block->renderWithInner( [], [] ) )->toBe( '' );
} );

it( 'renders a placeholder for a missing collection', function (): void {
	// Rebind the accessor with no `team` key to simulate an unknown source
	// still returning `[]` — that's the "zero records" branch.
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [] ),
	);

	$block = new DynamicLoopBlock( $this->resolver );

	$html = (string) $block->renderWithInner( [ 'collection' => 'team' ], [
		[ 'name' => 'artisanpack/paragraph', 'attrs' => [ 'content' => 'x' ], 'innerBlocks' => [] ],
	] );

	// Empty collection → the empty-records placeholder is rendered.
	expect( $html )->toContain( 've-dynamic-loop-empty' );
} );

it( 'passes the index scope to bindings on the DC source resolution', function (): void {
	// This test doesn't render HTML (renderer-blade isn't a hard dep for
	// this test) — it exercises the loop's per-iteration binding
	// resolution by inspecting the source's `resolve()` output through
	// the extras context the loop constructs.
	$source = new DynamicContentSource();

	foreach ( [ 0 => 'Alice', 1 => 'Bob' ] as $index => $expected ) {
		$context = new BindingContext(
			null,
			[],
			[ DynamicContentSource::EXTRAS_INDEX_KEY => [ 'team' => $index ] ],
		);

		expect( $source->resolve( $context, [ 'token' => 'team.name' ] ) )->toBe( $expected );
	}
} );

/**
 * @param  array<string, mixed>  $args
 *
 * @return array<string, mixed>
 */
function dynamicLoopParagraph( string $source, array $args, string $onEmpty = 'fallback' ): array
{
	return [
		'name'        => 'core/paragraph',
		'attributes'  => [
			'content'  => 'Static',
			'bindings' => [
				'content' => [
					'source'      => $source,
					'args'        => $args,
					'onEmpty'     => $onEmpty,
					'placeholder' => 'n/a',
				],
			],
		],
		'innerBlocks' => [],
	];
}

/**
 * Render a dynamic-loop over `team` through the outer renderer, the way
 * `<x-ve-blocks :post>` does, so the host-context binding pass runs.
 *
 * @param  array<int, array<string, mixed>>  $template
 */
function renderDynamicLoop( array $template, ?Model $host = null ): string
{
	// The root suite doesn't boot the Blade renderer; register it so
	// the row blocks render through their real partials.
	app()->register( VisualEditorRendererBladeServiceProvider::class );

	$tree = [ [
		'name'        => DynamicLoopBlock::BLOCK_NAME,
		'attributes'  => [ 'collection' => 'team' ],
		'innerBlocks' => $template,
	] ];

	return app( BlockRenderer::class )->render( $tree, new BindingContext( $host ) );
}

function dynamicLoopHost(): Model
{
	$host = new class extends Model {
		protected $table = 'posts';
	};

	$host->setRawAttributes( [ 'id' => 99, 'title' => 'Host page' ], true );

	return $host;
}

it( 'renders each row with its own record through the outer renderer (#854)', function ( string $policy ): void {
	$html = renderDynamicLoop(
		[ dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'team.name' ], $policy ) ],
		dynamicLoopHost(),
	);

	expect( $html )->toContain( '<p class="wp-block-paragraph">Alice</p>' )
		->and( $html )->toContain( '<p class="wp-block-paragraph">Bob</p>' )
		->and( substr_count( $html, '<p class="wp-block-paragraph">' ) )->toBe( 2 )
		->and( $html )->not->toContain( 'n/a' )
		->and( $html )->not->toContain( 'Static' );
} )->with( [ 'fallback', 'hide', 'placeholder' ] );

it( 'does not leak the host model into model-backed row bindings (#854)', function (): void {
	$html = renderDynamicLoop(
		[ dynamicLoopParagraph( 'post_core', [ 'key' => 'title' ], 'placeholder' ) ],
		dynamicLoopHost(),
	);

	expect( $html )->not->toContain( 'Host page' )
		->and( substr_count( $html, '<p class="wp-block-paragraph">n/a</p>' ) )->toBe( 2 );
} );

/**
 * @param  array<int, array<string, mixed>>  $template
 *
 * @return array<string, mixed>
 */
function dynamicLoopBlock( string $collection, array $template ): array
{
	return [
		'name'        => DynamicLoopBlock::BLOCK_NAME,
		'attributes'  => [ 'collection' => $collection ],
		'innerBlocks' => $template,
	];
}

/**
 * Render a `team` loop whose rows hold an `office` loop through the
 * outer renderer with a host binding context.
 *
 * @param  array<int, array<string, mixed>>  $innerTemplate
 */
function renderNestedDynamicLoop( array $innerTemplate ): string
{
	app()->register( VisualEditorRendererBladeServiceProvider::class );

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'team'   => [
				[ 'name' => 'Alice' ],
				[ 'name' => 'Bob' ],
			],
			'office' => [
				[ 'city' => 'Paris', 'code' => 'PAR' ],
				[ 'city' => 'Oslo' ],
			],
		] ),
	);

	$tree = [ dynamicLoopBlock( 'team', [ dynamicLoopBlock( 'office', $innerTemplate ) ] ) ];

	return app( BlockRenderer::class )->render( $tree, new BindingContext( dynamicLoopHost() ) );
}

/**
 * @return list<string>
 */
function dynamicLoopParagraphTexts( string $html ): array
{
	preg_match_all( '#<p class="wp-block-paragraph">(.*?)</p>#s', $html, $matches );

	return $matches[1];
}

it( 'resolves each nested row against its own inner record and the current outer record (#861)', function ( string $policy ): void {
	$html = renderNestedDynamicLoop( [
		dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'office.city' ], $policy ),
		dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'team.name' ], $policy ),
	] );

	expect( dynamicLoopParagraphTexts( $html ) )->toBe( [
		'Paris', 'Alice', 'Oslo', 'Alice',
		'Paris', 'Bob', 'Oslo', 'Bob',
	] );
} )->with( [ 'fallback', 'hide', 'placeholder' ] );

it( 'applies hide per nested row without blanking rows that resolved (#861)', function (): void {
	$html = renderNestedDynamicLoop( [
		dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'office.code' ], 'hide' ),
	] );

	// Oslo has no `code`, so `hide` clears its content in both team rows.
	expect( dynamicLoopParagraphTexts( $html ) )->toBe( [ 'PAR', '', 'PAR', '' ] );
} );

it( 'applies placeholder per nested row without replacing rows that resolved (#861)', function (): void {
	$html = renderNestedDynamicLoop( [
		dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'office.code' ], 'placeholder' ),
	] );

	expect( dynamicLoopParagraphTexts( $html ) )->toBe( [ 'PAR', 'n/a', 'PAR', 'n/a' ] );
} );

it( 'reaches the outer index through blocks wrapping the nested loop (#861)', function (): void {
	app()->register( VisualEditorRendererBladeServiceProvider::class );

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'team'   => [ [ 'name' => 'Alice' ], [ 'name' => 'Bob' ] ],
			'office' => [ [ 'city' => 'Paris' ], [ 'city' => 'Oslo' ] ],
		] ),
	);

	$tree = [ dynamicLoopBlock( 'team', [ [
		'name'        => 'core/group',
		'attributes'  => [],
		'innerBlocks' => [ dynamicLoopBlock( 'office', [
			dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'office.city' ] ),
			dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'team.name' ] ),
		] ) ],
	] ] ) ];

	$html = app( BlockRenderer::class )->render( $tree, new BindingContext( dynamicLoopHost() ) );

	expect( dynamicLoopParagraphTexts( $html ) )->toBe( [
		'Paris', 'Alice', 'Oslo', 'Alice',
		'Paris', 'Bob', 'Oslo', 'Bob',
	] );
} );

it( 'ignores malformed parent index entries stamped on a loop (#861)', function (): void {
	app()->register( VisualEditorRendererBladeServiceProvider::class );

	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'team'   => [ [ 'name' => 'Alice' ], [ 'name' => 'Bob' ] ],
			'office' => [ [ 'city' => 'Paris' ], [ 'city' => 'Oslo' ] ],
		] ),
	);

	$block = new DynamicLoopBlock( $this->resolver );

	$html = (string) $block->renderWithInner(
		[
			'collection'                        => 'team',
			DynamicLoopBlock::PARENT_INDEX_ATTR => [ 'team' => 1, 'office' => -1, 3 => 0 ],
		],
		[
			dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'team.name' ] ),
			dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'office.city' ] ),
		],
	);

	// The loop's own index wins over a stamped one for the same
	// collection, and the negative `office` index is dropped, so
	// `office.city` reads its first record.
	expect( dynamicLoopParagraphTexts( $html ) )->toBe( [ 'Alice', 'Paris', 'Bob', 'Paris' ] );
} );

it( 'leaves the inner blocks of opaque blocks unresolved and unstripped (#861)', function (): void {
	$context = new BindingContext( null, [], [ DynamicContentSource::EXTRAS_INDEX_KEY => [ 'team' => 1 ] ] );
	$nested  = dynamicLoopBlock( 'office', [ dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'team.name' ] ) ] );

	$resolved = $this->resolver->resolveAndStrip(
		[ $nested, dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'team.name' ] ) ],
		$context,
		[ DynamicLoopBlock::BLOCK_NAME ],
	);

	expect( $resolved[0]['innerBlocks'][0] )->toBe( $nested['innerBlocks'][0] )
		->and( $resolved[1]['attributes'] )->toBe( [ 'content' => 'Bob' ] );
} );

it( 'keeps the authored fallback on a row whose field is empty (#854)', function (): void {
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [
			'team' => [
				[ 'name' => 'Alice', 'role' => 'CEO' ],
				[ 'name' => 'Bob' ],
			],
		] ),
	);

	$html = renderDynamicLoop(
		[ dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'team.role' ] ) ],
		dynamicLoopHost(),
	);

	expect( dynamicLoopParagraphTexts( $html ) )->toBe( [ 'CEO', 'Static' ] );
} );

it( 'keeps the authored fallback on a nested row whose field is empty (#861)', function (): void {
	$html = renderNestedDynamicLoop( [
		dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'office.code' ] ),
	] );

	expect( dynamicLoopParagraphTexts( $html ) )->toBe( [ 'PAR', 'Static', 'PAR', 'Static' ] );
} );

it( 'leaves a dynamic loop template alone in the renderer binding pass (#854)', function (): void {
	app()->register( VisualEditorRendererBladeServiceProvider::class );

	$paragraph = dynamicLoopParagraph( DynamicContentSource::NAME, [ 'token' => 'team.name' ] );
	$loop      = dynamicLoopBlock( 'team', [ $paragraph ] );

	$loop['attributes']['label']    = 'Static label';
	$loop['attributes']['bindings'] = [
		'label' => [ 'source' => DynamicContentSource::NAME, 'args' => [ 'token' => 'team.name' ] ],
	];

	$resolved = app( BlockRenderer::class )->resolveBindings( [ $loop ], new BindingContext( dynamicLoopHost() ) );

	expect( $resolved[0]['attributes']['label'] )->toBe( 'Alice' )
		->and( $resolved[0]['innerBlocks'][0] )->toBe( $paragraph );
} );

it( 'gives each row after the first its own anchors so ids stay unique', function (): void {
	$heading = [
		'name'        => 'core/heading',
		'attributes'  => [ 'content' => 'Member', 'level' => 3, 'anchor' => 'member' ],
		'innerBlocks' => [],
	];

	$html = renderDynamicLoop( [ $heading ] );

	preg_match_all( '/<h3[^>]*\sid="([^"]+)"/', $html, $ids );

	expect( $ids[1] )->toBe( [ 'member', 'member-team-2' ] );
} );

it( 'keeps nested-loop anchors unique across outer and inner rows', function (): void {
	$html = renderNestedDynamicLoop( [ [
		'name'        => 'core/heading',
		'attributes'  => [ 'content' => 'Office', 'level' => 3, 'anchor' => 'office' ],
		'innerBlocks' => [],
	] ] );

	preg_match_all( '/<h3[^>]*\sid="([^"]+)"/', $html, $ids );

	expect( $ids[1] )->toBe( [ 'office', 'office-office-2', 'office-team-2', 'office-team-2-office-2' ] );
} );
