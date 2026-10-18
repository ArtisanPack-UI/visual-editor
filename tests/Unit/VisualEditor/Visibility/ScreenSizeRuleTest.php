<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Visibility\Rules\ScreenSizeRule;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;

function screenRule(): ScreenSizeRule
{
	return new ScreenSizeRule( BreakpointRegistry::fromLayers( [] ) );
}

it( 'is visible with no breakpoints configured', function (): void {
	expect( screenRule()->evaluate( [], new VisibilityContext() )->isVisible() )->toBeTrue();
} );

it( 'emits CSS-hidden decision with the configured hide list', function (): void {
	$decision = screenRule()->evaluate(
		[ 'direction' => 'hide', 'breakpoints' => [ 'sm', 'md' ] ],
		new VisibilityContext(),
	);

	expect( $decision->isCssHidden() )->toBeTrue();
	expect( $decision->hiddenBreakpoints )->toEqualCanonicalizing( [ 'sm', 'md' ] );
} );

it( 'inverts to hidden everywhere else when direction=show', function (): void {
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

it( 'is visible when direction=show covers every registered breakpoint', function (): void {
	$decision = screenRule()->evaluate(
		[ 'direction' => 'show', 'breakpoints' => [ 'sm', 'md', 'lg', 'xl', '2xl' ] ],
		new VisibilityContext(),
	);

	expect( $decision->isVisible() )->toBeTrue();
} );

it( 'hides the device keys when listed (#974)', function (): void {
	$decision = screenRule()->evaluate(
		[ 'direction' => 'hide', 'breakpoints' => [ 'mobile', 'tablet', 'xl' ] ],
		new VisibilityContext(),
	);

	expect( $decision->isCssHidden() )->toBeTrue();
	expect( $decision->hiddenBreakpoints )->toEqualCanonicalizing( [ 'mobile', 'tablet', 'xl' ] );
} );

it( 'ignores unknown keys', function (): void {
	expect( screenRule()->evaluate( [ 'direction' => 'hide', 'breakpoints' => [ 'phone' ] ], new VisibilityContext() )->isVisible() )
		->toBeTrue();
} );

it( 'shows only on mobile by hiding every legacy range from 768px up', function (): void {
	$decision = screenRule()->evaluate(
		[ 'direction' => 'show', 'breakpoints' => [ 'mobile' ] ],
		new VisibilityContext(),
	);

	expect( $decision->hiddenBreakpoints )->toEqualCanonicalizing( [ 'md', 'lg', 'xl', '2xl' ] );
} );

it( 'shows up to tablet by hiding every legacy range from 1024px up', function (): void {
	$decision = screenRule()->evaluate(
		[ 'direction' => 'show', 'breakpoints' => [ 'tablet' ] ],
		new VisibilityContext(),
	);

	expect( $decision->hiddenBreakpoints )->toEqualCanonicalizing( [ 'lg', 'xl', '2xl' ] );
} );

it( 'shows on mobile plus a listed legacy range', function (): void {
	$decision = screenRule()->evaluate(
		[ 'direction' => 'show', 'breakpoints' => [ 'mobile', 'xl' ] ],
		new VisibilityContext(),
	);

	expect( $decision->hiddenBreakpoints )->toEqualCanonicalizing( [ 'md', 'lg', '2xl' ] );
} );
