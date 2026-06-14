<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use Tests\Fixtures\TestBlockContentModel;

it( 'defaults to a model-less, draftless context', function () {
	$context = new BindingContext();

	expect( $context->model() )->toBeNull()
		->and( $context->draft() )->toBe( [] )
		->and( $context->extras() )->toBe( [] );
} );

it( 'returns the supplied draft snapshot verbatim', function () {
	$context = new BindingContext( null, [ 'title' => 'Draft Title', 'meta' => [ 'tone' => 'casual' ] ] );

	expect( $context->draft() )->toBe( [ 'title' => 'Draft Title', 'meta' => [ 'tone' => 'casual' ] ] )
		->and( $context->draftValue( 'title' ) )->toBe( 'Draft Title' )
		->and( $context->draftValue( 'missing' ) )->toBeNull();
} );

it( 'is immutable — withModel returns a new instance', function () {
	$original = new BindingContext( null, [ 'a' => 1 ], [ 'b' => 2 ] );
	$model    = new TestBlockContentModel( [ 'title' => 'X' ] );

	$next = $original->withModel( $model );

	expect( $next )->not->toBe( $original )
		->and( $original->model() )->toBeNull()
		->and( $next->model() )->toBe( $model )
		->and( $next->draft() )->toBe( [ 'a' => 1 ] )
		->and( $next->extras() )->toBe( [ 'b' => 2 ] );
} );

it( 'withDraft replaces only the draft', function () {
	$model    = new TestBlockContentModel( [ 'title' => 'Y' ] );
	$original = new BindingContext( $model, [ 'a' => 1 ], [ 'b' => 2 ] );

	$next = $original->withDraft( [ 'c' => 3 ] );

	expect( $next->model() )->toBe( $model )
		->and( $next->draft() )->toBe( [ 'c' => 3 ] )
		->and( $next->extras() )->toBe( [ 'b' => 2 ] )
		->and( $original->draft() )->toBe( [ 'a' => 1 ] );
} );

it( 'withExtras replaces only the extras bag', function () {
	$original = new BindingContext( null, [ 'a' => 1 ], [ 'b' => 2 ] );

	$next = $original->withExtras( [ 'siteId' => 4 ] );

	expect( $next->extras() )->toBe( [ 'siteId' => 4 ] )
		->and( $next->draft() )->toBe( [ 'a' => 1 ] )
		->and( $original->extras() )->toBe( [ 'b' => 2 ] );
} );
