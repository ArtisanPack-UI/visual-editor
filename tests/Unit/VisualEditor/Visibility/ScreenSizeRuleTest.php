<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Visibility\Rules\ScreenSizeRule;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;

function screenRule(): ScreenSizeRule
{
	return new ScreenSizeRule( BreakpointRegistry::fromLayers( [] ) );
}

it( 'is visible with no breakpoints configured', function () {
	expect( screenRule()->evaluate( [], new VisibilityContext() )->isVisible() )->toBeTrue();
} );

it( 'emits CSS-hidden decision with the configured hide list', function () {
	$decision = screenRule()->evaluate(
		[ 'direction' => 'hide', 'breakpoints' => [ 'sm', 'md' ] ],
		new VisibilityContext(),
	);

	expect( $decision->isCssHidden() )->toBeTrue();
	expect( $decision->hiddenBreakpoints )->toEqualCanonicalizing( [ 'sm', 'md' ] );
} );

it( 'inverts to hidden everywhere else when direction=show', function () {
	// direction=show + [sm, md] means "visible at sm and md, hidden elsewhere".
	// The default registry has sm, md, lg, xl, 2xl — so lg, xl, 2xl should hide.
	$decision = screenRule()->evaluate(
		[ 'direction' => 'show', 'breakpoints' => [ 'sm', 'md' ] ],
		new VisibilityContext(),
	);

	expect( $decision->isCssHidden() )->toBeTrue();
	// Order isn't guaranteed, but the set is: registry keys minus {sm, md}.
	expect( $decision->hiddenBreakpoints )->toEqualCanonicalizing( [ '2xl', 'lg', 'xl' ] );
} );

it( 'is visible when direction=show covers every registered breakpoint', function () {
	$decision = screenRule()->evaluate(
		[ 'direction' => 'show', 'breakpoints' => [ 'sm', 'md', 'lg', 'xl', '2xl' ] ],
		new VisibilityContext(),
	);

	expect( $decision->isVisible() )->toBeTrue();
} );
