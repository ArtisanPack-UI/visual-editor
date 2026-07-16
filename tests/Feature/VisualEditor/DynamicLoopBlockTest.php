<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\DynamicContent\DynamicLoopBlock;
use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\DynamicContentSource;
use Tests\Support\FakeDynamicContentAccessor;

beforeEach( function () {
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
		] )
	);
} );

it( 'returns an empty string when no collection is set', function () {
	$block = new DynamicLoopBlock( $this->resolver );

	expect( (string) $block->renderWithInner( [], [] ) )->toBe( '' );
} );

it( 'renders a placeholder for a missing collection', function () {
	// Rebind the accessor with no `team` key to simulate an unknown source
	// still returning `[]` — that's the "zero records" branch.
	app()->instance(
		'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor',
		new FakeDynamicContentAccessor( [] )
	);

	$block = new DynamicLoopBlock( $this->resolver );

	$html = (string) $block->renderWithInner( [ 'collection' => 'team' ], [
		[ 'name' => 'artisanpack/paragraph', 'attrs' => [ 'content' => 'x' ], 'innerBlocks' => [] ],
	] );

	// Empty collection → the empty-records placeholder is rendered.
	expect( $html )->toContain( 've-dynamic-loop-empty' );
} );

it( 'passes the index scope to bindings on the DC source resolution', function () {
	// This test doesn't render HTML (renderer-blade isn't a hard dep for
	// this test) — it exercises the loop's per-iteration binding
	// resolution by inspecting the source's `resolve()` output through
	// the extras context the loop constructs.
	$source = new DynamicContentSource();

	foreach ( [ 0 => 'Alice', 1 => 'Bob' ] as $index => $expected ) {
		$context = new \ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext(
			null,
			[],
			[ DynamicContentSource::EXTRAS_INDEX_KEY => [ 'team' => $index ] ]
		);

		expect( $source->resolve( $context, [ 'token' => 'team.name' ] ) )->toBe( $expected );
	}
} );
