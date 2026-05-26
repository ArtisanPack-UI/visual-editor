<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Forms\FormBlock;

/*
 * Tests for {@see FormBlock} focused on the database-free surface:
 * `validateAttrs()` (strict `formId` parsing + supports-attrs passthrough)
 * and `placeholder()` (which goes through the same
 * `BlockSupports::wrapperAttrs()` pipeline `render()` uses for live
 * forms). The render() path with a real `formId` resolves an Eloquent
 * `Form` and is covered by the feature/HTTP layer.
 */

beforeEach( function (): void {
	test()->block = new FormBlock();
} );

/* ---- validateAttrs: strict-int parsing for formId (CodeRabbit PR #467) ---- */

it( 'normalizes a positive integer formId verbatim', function (): void {
	expect( test()->block->validateAttrs( [ 'formId' => 42 ] )['formId'] )->toBe( 42 );
} );

it( 'normalizes a positive integer string formId', function (): void {
	expect( test()->block->validateAttrs( [ 'formId' => '42' ] )['formId'] )->toBe( 42 );
} );

it( 'rejects float strings (CodeRabbit PR #467)', function (): void {
	// `is_numeric('12.9')` returns true and `(int) '12.9'` truncates to 12,
	// which would silently coerce a non-ID float into a real lookup attempt.
	// Strict parsing returns 0 and funnels into the placeholder branch.
	expect( test()->block->validateAttrs( [ 'formId' => '12.9' ] )['formId'] )->toBe( 0 );
} );

it( 'rejects scientific notation (CodeRabbit PR #467)', function (): void {
	// Same story as floats — `is_numeric('1e2')` is true and `(int) '1e2'`
	// produces 100, which would address an unrelated form record.
	expect( test()->block->validateAttrs( [ 'formId' => '1e2' ] )['formId'] )->toBe( 0 );
} );

it( 'rejects negative integers and zero', function (): void {
	expect( test()->block->validateAttrs( [ 'formId' => 0 ] )['formId'] )->toBe( 0 )
		->and( test()->block->validateAttrs( [ 'formId' => -3 ] )['formId'] )->toBe( 0 )
		->and( test()->block->validateAttrs( [ 'formId' => '-3' ] )['formId'] )->toBe( 0 );
} );

it( 'rejects non-numeric and non-scalar formId values', function (): void {
	expect( test()->block->validateAttrs( [ 'formId' => 'abc' ] )['formId'] )->toBe( 0 )
		->and( test()->block->validateAttrs( [ 'formId' => [ 1, 2 ] ] )['formId'] )->toBe( 0 )
		->and( test()->block->validateAttrs( [ 'formId' => null ] )['formId'] )->toBe( 0 )
		->and( test()->block->validateAttrs( [] )['formId'] )->toBe( 0 );
} );

it( 'preserves a string className verbatim and coerces non-strings to empty', function (): void {
	expect( test()->block->validateAttrs( [ 'formId' => 1, 'className' => 'extra-class' ] )['className'] )->toBe( 'extra-class' )
		->and( test()->block->validateAttrs( [ 'formId' => 1, 'className' => 123 ] ) )->not->toHaveKey( 'className' )
		->and( test()->block->validateAttrs( [ 'formId' => 1 ] ) )->not->toHaveKey( 'className' );
} );

/* ---- placeholder() wrapper-attrs compilation through BlockSupports ---- */

it( 'wraps the placeholder with the base form class when no supports are set', function (): void {
	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( 'class="wp-block-artisanpack-form wp-block-artisanpack-form--placeholder"' )
		->and( $html )->not->toContain( 'style=' )
		->and( $html )->not->toContain( 'id=' );
} );

it( 'compiles typography, color, border, and spacing into class + style on the wrapper', function (): void {
	$attrs = test()->block->validateAttrs( [
		'formId'          => 0,
		'backgroundColor' => 'primary',
		'textColor'       => 'base-content',
		'fontSize'        => 'large',
		'fontFamily'      => 'sans',
		'borderColor'     => 'primary',
		'style'           => [
			'color'      => [ 'background' => 'var:preset|color|secondary' ],
			'border'     => [ 'radius' => '8px', 'width' => '2px' ],
			'spacing'    => [ 'padding' => '1.5rem', 'margin' => [ 'top' => '2rem' ] ],
			'typography' => [ 'lineHeight' => '1.4', 'letterSpacing' => '0.02em' ],
		],
	] );

	$html = test()->block->render( $attrs );

	expect( $html )->toContain( 'has-background' )
		->and( $html )->toContain( 'has-primary-background-color' )
		->and( $html )->toContain( 'has-text-color' )
		->and( $html )->toContain( 'has-base-content-color' )
		->and( $html )->toContain( 'has-large-font-size' )
		->and( $html )->toContain( 'has-sans-font-family' )
		->and( $html )->toContain( 'has-border-color' )
		->and( $html )->toContain( 'has-primary-border-color' )
		->and( $html )->toContain( 'border-radius: 8px' )
		->and( $html )->toContain( 'border-width: 2px' )
		->and( $html )->toContain( 'border-style: solid' )
		->and( $html )->toContain( 'padding: 1.5rem' )
		->and( $html )->toContain( 'margin-top: 2rem' )
		->and( $html )->toContain( 'line-height: 1.4' )
		->and( $html )->toContain( 'letter-spacing: 0.02em' );
} );

it( 'lifts the `anchor` attribute into the wrapper id', function (): void {
	$html = test()->block->render( test()->block->validateAttrs( [
		'anchor' => 'contact-form',
	] ) );

	expect( $html )->toContain( 'id="contact-form"' );
} );

it( 'appends the user-supplied className alongside the base class', function (): void {
	$html = test()->block->render( test()->block->validateAttrs( [
		'className' => 'is-style-bordered max-w-lg',
	] ) );

	expect( $html )->toContain( 'wp-block-artisanpack-form' )
		->and( $html )->toContain( 'is-style-bordered' )
		->and( $html )->toContain( 'max-w-lg' );
} );

it( 'drops non-array `style` and non-string supports attrs through validateAttrs', function (): void {
	$attrs = test()->block->validateAttrs( [
		'formId'          => '42',
		'style'           => 'default',
		'backgroundColor' => [ 'not', 'a', 'string' ],
		'className'       => 'valid',
	] );

	expect( $attrs )->toMatchArray( [
		'formId'    => 42,
		'className' => 'valid',
	] )
		->and( $attrs )->not->toHaveKey( 'style' )
		->and( $attrs )->not->toHaveKey( 'backgroundColor' );
} );
