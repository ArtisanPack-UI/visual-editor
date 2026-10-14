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
		$context = new ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext(
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
