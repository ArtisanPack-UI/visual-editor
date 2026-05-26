<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Forms\FormBlock;

/*
 * Tests for {@see FormBlock} focused on attribute normalization. The
 * render-path branches (missing form, inactive form, valid form) hit
 * the Forms package's Eloquent model and live in the feature-test
 * layer; these unit tests cover the surface that does not require a
 * database — `validateAttrs()` and its `normalizeFormId()` helper.
 */

beforeEach( function (): void {
	test()->block = new FormBlock();
} );

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
		->and( test()->block->validateAttrs( [ 'formId' => 1, 'className' => 123 ] )['className'] )->toBe( '' )
		->and( test()->block->validateAttrs( [ 'formId' => 1 ] )['className'] )->toBe( '' );
} );
