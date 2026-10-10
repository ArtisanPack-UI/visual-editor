<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\Snippet;
use ArtisanPackUI\VisualEditor\Services\DynamicContent\SnippetCycleException;
use ArtisanPackUI\VisualEditor\Services\DynamicContent\SnippetCycleGuard;

it( 'allows a snippet with no snippet references', function (): void {
	$guard = new SnippetCycleGuard();

	$guard->assertNoCycle( 'cta', [
		[ 'name' => 'artisanpack/paragraph', 'attrs' => [ 'content' => 'Hi' ], 'innerBlocks' => [] ],
	] );

	expect( true )->toBeTrue();
} );

it( 'throws when a snippet references itself directly', function (): void {
	$guard = new SnippetCycleGuard();

	expect( fn () => $guard->assertNoCycle( 'cta', [
		[ 'name' => 'artisanpack/snippet', 'attrs' => [ 'slug' => 'cta' ], 'innerBlocks' => [] ],
	] ) )->toThrow( SnippetCycleException::class );
} );

it( 'throws when a snippet references itself transitively', function (): void {
	Snippet::factory()->create( [
		'slug'   => 'inner',
		'blocks' => [
			[ 'name' => 'artisanpack/snippet', 'attrs' => [ 'slug' => 'outer' ], 'innerBlocks' => [] ],
		],
	] );

	$guard = new SnippetCycleGuard();

	expect( fn () => $guard->assertNoCycle( 'outer', [
		[ 'name' => 'artisanpack/snippet', 'attrs' => [ 'slug' => 'inner' ], 'innerBlocks' => [] ],
	] ) )->toThrow( SnippetCycleException::class );
} );

it( 'catches a self-cycle authored in the editor (Gutenberg attributes shape)', function (): void {
	$guard = new SnippetCycleGuard();

	expect( fn () => $guard->assertNoCycle( 'cta', [
		[
			'name'        => 'artisanpack/snippet',
			'attributes'  => [ 'slug' => 'cta' ],  // editor persists as `attributes`, not `attrs`
			'innerBlocks' => [],
		],
	] ) )->toThrow( SnippetCycleException::class );
} );

it( 'allows a snippet that references a different snippet without cycling back', function (): void {
	Snippet::factory()->create( [
		'slug'   => 'inner',
		'blocks' => [
			[ 'name' => 'artisanpack/paragraph', 'attrs' => [ 'content' => 'plain' ], 'innerBlocks' => [] ],
		],
	] );

	$guard = new SnippetCycleGuard();

	$guard->assertNoCycle( 'outer', [
		[ 'name' => 'artisanpack/snippet', 'attrs' => [ 'slug' => 'inner' ], 'innerBlocks' => [] ],
	] );

	expect( true )->toBeTrue();
} );

it( 'checkPlacement returns null for a visited slug', function (): void {
	Snippet::factory()->create( [ 'slug' => 'cta' ] );

	$guard = new SnippetCycleGuard();

	expect( $guard->checkPlacement( 'cta', [ 'cta' => true ], 0 ) )->toBeNull();
} );

it( 'checkPlacement returns the snippet when not visited', function (): void {
	$snippet = Snippet::factory()->create( [ 'slug' => 'cta' ] );

	$guard = new SnippetCycleGuard();

	$found = $guard->checkPlacement( 'cta', [], 0 );

	expect( $found )->not->toBeNull();
	expect( $found->id )->toBe( $snippet->id );
} );
