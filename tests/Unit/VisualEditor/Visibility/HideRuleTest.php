<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Visibility\Rules\HideRule;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;

it( 'is visible by default', function () {
	$rule = new HideRule();

	expect( $rule->evaluate( [], new VisibilityContext() )->isVisible() )->toBeTrue();
} );

it( 'hides when hidden === true', function () {
	$rule = new HideRule();

	expect( $rule->evaluate( [ 'hidden' => true ], new VisibilityContext() )->isHidden() )->toBeTrue();
} );

it( 'is visible when hidden is false', function () {
	$rule = new HideRule();

	expect( $rule->evaluate( [ 'hidden' => false ], new VisibilityContext() )->isVisible() )->toBeTrue();
} );
