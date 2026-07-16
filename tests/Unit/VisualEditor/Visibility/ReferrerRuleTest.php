<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Visibility\Rules\ReferrerRule;
use ArtisanPackUI\VisualEditor\Visibility\VisibilityContext;

function refCtx( string $referer ): VisibilityContext
{
	return new VisibilityContext( referrer: $referer );
}

it( 'is visible with no patterns', function () {
	$rule = new ReferrerRule();
	expect( $rule->evaluate( [], refCtx( 'https://twitter.com/' ) )->isVisible() )->toBeTrue();
} );

it( 'matches literal hostnames', function () {
	$rule = new ReferrerRule();
	$attrs = [ 'direction' => 'show', 'patterns' => [ 'twitter.com' ] ];
	expect( $rule->evaluate( $attrs, refCtx( 'https://twitter.com/foo' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, refCtx( 'https://x.com/foo' ) )->isHidden() )->toBeTrue();
} );

it( 'matches subdomain wildcards with *. prefix', function () {
	$rule = new ReferrerRule();
	$attrs = [ 'direction' => 'show', 'patterns' => [ '*.example.com' ] ];
	expect( $rule->evaluate( $attrs, refCtx( 'https://sub.example.com/foo' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, refCtx( 'https://example.com/foo' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, refCtx( 'https://other.com/foo' ) )->isHidden() )->toBeTrue();
} );

it( 'recognizes (direct) for empty referer', function () {
	$rule = new ReferrerRule();
	$attrs = [ 'direction' => 'show', 'patterns' => [ '(direct)' ] ];
	expect( $rule->evaluate( $attrs, refCtx( '' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, refCtx( 'https://twitter.com/' ) )->isHidden() )->toBeTrue();
} );

it( 'wildcard suffix is case-insensitive so brand-cased patterns match lowercase referrer hosts', function () {
	$rule = new ReferrerRule();
	$attrs = [ 'direction' => 'show', 'patterns' => [ '*.Example.com' ] ];
	expect( $rule->evaluate( $attrs, refCtx( 'https://sub.example.com/foo' ) )->isVisible() )->toBeTrue();
	expect( $rule->evaluate( $attrs, refCtx( 'https://example.com/foo' ) )->isVisible() )->toBeTrue();
} );

it( 'inverts on direction=hide', function () {
	$rule = new ReferrerRule();
	$attrs = [ 'direction' => 'hide', 'patterns' => [ 'twitter.com' ] ];
	expect( $rule->evaluate( $attrs, refCtx( 'https://twitter.com/' ) )->isHidden() )->toBeTrue();
} );
