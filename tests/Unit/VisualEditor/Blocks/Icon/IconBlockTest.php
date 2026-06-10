<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Icon\IconBlock;
use ArtisanPackUI\VisualEditor\Services\Icon\SvgSanitizer;

beforeEach( function (): void {
	test()->block = new IconBlock( new SvgSanitizer() );
} );

it( 'reports the artisanpack/icon block name', function () {
	expect( test()->block->name() )->toBe( 'artisanpack/icon' );
} );

it( 'renders a placeholder span when no iconRef or customSvg is set', function () {
	$html = test()->block->render( [] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' )
		->and( $html )->toContain( 'aria-hidden="true"' )
		->and( $html )->toContain( 'wp-block-artisanpack-icon' );
} );

it( 'emits data attributes for an iconRef', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
	] );

	expect( $html )->toContain( 'data-icon-set="fab"' )
		->and( $html )->toContain( 'data-icon-name="github"' );
} );

it( 'rejects iconRef sets with disallowed characters', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => '../etc', 'name' => 'github' ],
	] );

	expect( $html )->toContain( 'wp-block-artisanpack-icon__placeholder' )
		->and( $html )->not->toContain( '../etc' );
} );

it( 'inlines a sanitized customSvg', function () {
	$html = test()->block->render( [
		'customSvg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>',
	] );

	expect( $html )->toContain( '<path' )
		->and( $html )->not->toContain( '<script' )
		->and( $html )->not->toContain( 'alert(1)' );
} );

it( 'wraps the icon in an anchor when link is set', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'https://example.com',
	] );

	expect( $html )->toContain( '<a href="https://example.com"' );
} );

it( 'forces noopener noreferrer when target is _blank', function () {
	$html = test()->block->render( [
		'iconRef'    => [ 'set' => 'fab', 'name' => 'github' ],
		'link'       => 'https://example.com',
		'linkTarget' => '_blank',
		'linkRel'    => 'nofollow',
	] );

	expect( $html )->toContain( 'target="_blank"' )
		->and( $html )->toContain( 'rel=' )
		->and( $html )->toContain( 'noopener' )
		->and( $html )->toContain( 'noreferrer' )
		->and( $html )->toContain( 'nofollow' );
} );

it( 'drops link wrapping when no icon is selected', function () {
	$html = test()->block->render( [
		'link' => 'https://example.com',
	] );

	expect( $html )->not->toContain( '<a ' );
} );

it( 'emits aria-hidden when decorative', function () {
	$html = test()->block->render( [
		'iconRef'      => [ 'set' => 'fab', 'name' => 'github' ],
		'isDecorative' => true,
		'ariaLabel'    => 'GitHub Profile',
	] );

	// Decorative wins — aria-label is suppressed by aria-hidden.
	expect( $html )->toContain( 'aria-hidden="true"' )
		->and( $html )->not->toContain( 'aria-label="GitHub Profile"' );
} );

it( 'emits aria-label for accessible non-decorative icons', function () {
	$html = test()->block->render( [
		'iconRef'   => [ 'set' => 'fab', 'name' => 'github' ],
		'ariaLabel' => 'GitHub Profile',
	] );

	expect( $html )->toContain( 'aria-label="GitHub Profile"' );
} );

it( 'composes a transform from rotation + flip', function () {
	$html = test()->block->render( [
		'iconRef'  => [ 'set' => 'fab', 'name' => 'github' ],
		'rotation' => 90,
		'flipH'    => true,
	] );

	expect( $html )->toContain( 'rotate(90deg)' )
		->and( $html )->toContain( 'scaleX(-1)' );
} );

it( 'rejects out-of-range rotations', function () {
	$normalized = test()->block->validateAttrs( [ 'rotation' => 45 ] );

	expect( $normalized['rotation'] )->toBe( 0 );
} );

it( 'rejects invalid size units', function () {
	$normalized = test()->block->validateAttrs( [ 'sizeUnit' => 'vh' ] );

	expect( $normalized['sizeUnit'] )->toBe( 'px' );
} );

it( 'clamps size to a reasonable range', function () {
	expect( test()->block->validateAttrs( [ 'size' => 0 ] )['size'] )->toBe( 1.0 )
		->and( test()->block->validateAttrs( [ 'size' => 99999 ] )['size'] )->toBe( 1024.0 );
} );

it( 'rejects invalid link targets', function () {
	$normalized = test()->block->validateAttrs( [ 'linkTarget' => '_evil' ] );

	expect( $normalized['linkTarget'] )->toBe( '' );
} );

it( 'rejects malformed colors', function () {
	$normalized = test()->block->validateAttrs( [ 'color' => 'javascript:alert(1)' ] );

	expect( $normalized['color'] )->toBeNull();
} );

it( 'accepts hex, rgb, hsl, var, and named colors', function () {
	expect( test()->block->validateAttrs( [ 'color' => '#fff' ] )['color'] )->toBe( '#fff' )
		->and( test()->block->validateAttrs( [ 'color' => 'rgb(0,0,0)' ] )['color'] )->toBe( 'rgb(0,0,0)' )
		->and( test()->block->validateAttrs( [ 'color' => 'hsl(0,100%,50%)' ] )['color'] )->toBe( 'hsl(0,100%,50%)' )
		->and( test()->block->validateAttrs( [ 'color' => 'var(--brand-primary)' ] )['color'] )->toBe( 'var(--brand-primary)' )
		->and( test()->block->validateAttrs( [ 'color' => 'red' ] )['color'] )->toBe( 'red' );
} );

it( 'rejects javascript: links', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'javascript:alert(1)',
	] );

	expect( $html )->not->toContain( '<a ' )
		->and( $html )->not->toContain( 'javascript:' );
} );

it( 'rejects data: links', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'data:text/html,<script>alert(1)</script>',
	] );

	expect( $html )->not->toContain( '<a ' )
		->and( $html )->not->toContain( 'data:' );
} );

it( 'allows relative links', function () {
	$html = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => '/about',
	] );

	expect( $html )->toContain( '<a href="/about"' );
} );

it( 'allows mailto and tel links', function () {
	$mail = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'mailto:hello@example.com',
	] );
	$tel  = test()->block->render( [
		'iconRef' => [ 'set' => 'fab', 'name' => 'github' ],
		'link'    => 'tel:+15551234567',
	] );

	expect( $mail )->toContain( 'href="mailto:hello@example.com"' )
		->and( $tel )->toContain( 'href="tel:+15551234567"' );
} );

it( 'exposes ariaLabel + title via searchableText', function () {
	$text = test()->block->searchableText( [
		'ariaLabel' => 'GitHub Profile',
		'titleAttr' => 'Visit on GitHub',
	] );

	expect( $text )->toContain( 'GitHub Profile' )
		->and( $text )->toContain( 'Visit on GitHub' );
} );
