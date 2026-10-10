<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Ai\Support\BlockPayloadLimiter;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\HeadingHierarchyRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Ai\SuggestNextBlockRequest;
use Illuminate\Support\Facades\Validator;

it( 'falls back to the defaults for missing or invalid config values', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits', [ 'max_blocks' => 0, 'max_depth' => 'x' ] );

	expect( BlockPayloadLimiter::limits() )->toBe( BlockPayloadLimiter::DEFAULTS );
} );

it( 'reads configured limits', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits', [ 'max_blocks' => 5, 'max_depth' => 2, 'max_bytes' => 100, 'max_text_chars' => 50 ] );

	expect( BlockPayloadLimiter::limits() )->toBe( [ 'max_blocks' => 5, 'max_depth' => 2, 'max_bytes' => 100, 'max_text_chars' => 50 ] );
} );

it( 'reports no violation for an empty tree', function (): void {
	expect( BlockPayloadLimiter::treeViolation( [] ) )->toBeNull();
} );

it( 'rejects an oversized heading-hierarchy request at validation', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_blocks', 2 );

	$rules = ( new HeadingHierarchyRequest() )->rules();

	$ok  = Validator::make( [ 'blocks' => [ [ 'type' => 'core/heading' ] ] ], $rules );
	$bad = Validator::make( [ 'blocks' => array_fill( 0, 3, [ 'type' => 'core/heading' ] ) ], $rules );

	expect( $ok->passes() )->toBeTrue();
	expect( $bad->fails() )->toBeTrue();
	expect( $bad->errors()->first( 'blocks' ) )->toContain( 'more than 2 blocks' );
} );

it( 'rejects an over-deep heading-hierarchy request at validation', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_depth', 1 );

	$validator = Validator::make(
		[ 'blocks' => [ [ 'type' => 'core/group', 'innerBlocks' => [ [ 'type' => 'core/heading' ] ] ] ] ],
		( new HeadingHierarchyRequest() )->rules(),
	);

	expect( $validator->fails() )->toBeTrue();
} );

it( 'applies the same limits to next-block suggestion requests', function (): void {
	config()->set( 'artisanpack.visual-editor.ai.payload_limits.max_blocks', 1 );

	$validator = Validator::make(
		[ 'existing_blocks' => [ [ 'type' => 'a' ], [ 'type' => 'b' ] ], 'cursor_position' => 0 ],
		( new SuggestNextBlockRequest() )->rules(),
	);

	expect( $validator->fails() )->toBeTrue();
} );

it( 'registers the AI endpoints when the ai package is installed', function (): void {
	// `FeatureRegistry` is an interface; a `class_exists()` guard never
	// matched it, so no `/ai/*` route was ever registered.
	expect( Illuminate\Support\Facades\Route::has( 'visual-editor.api.ai.heading-hierarchy' ) )->toBeTrue();
	expect( Illuminate\Support\Facades\Route::has( 'visual-editor.api.ai.suggest-next-block' ) )->toBeTrue();
} );
