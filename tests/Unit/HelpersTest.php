<?php

declare( strict_types=1 );

use Tests\Unit\Blocks\Stubs\StubBlock;

test( 'veRegisterBlock registers a block', function (): void {
	$block = new StubBlock();

	veRegisterBlock( $block );

	expect( veBlockExists( 'stub' ) )->toBeTrue();
} );

test( 'veBlockExists returns false for unregistered block', function (): void {
	expect( veBlockExists( 'nonexistent' ) )->toBeFalse();
} );

test( 'veGetBlock returns registered block', function (): void {
	$block = new StubBlock();

	veRegisterBlock( $block );

	expect( veGetBlock( 'stub' ) )->toBe( $block );
} );

test( 'veGetBlock returns null for unregistered block', function (): void {
	expect( veGetBlock( 'nonexistent' ) )->toBeNull();
} );

test( 'visualEditor helper returns VisualEditor instance', function (): void {
	expect( visualEditor() )->toBeInstanceOf( ArtisanPackUI\VisualEditor\VisualEditor::class );
} );

// --- CSS Sanitization Helpers ---

test( 'veSanitizeCssColor accepts valid hex colors', function (): void {
	expect( veSanitizeCssColor( '#fff' ) )->toBe( '#fff' );
	expect( veSanitizeCssColor( '#ffffff' ) )->toBe( '#ffffff' );
	expect( veSanitizeCssColor( '#FF6600' ) )->toBe( '#FF6600' );
	expect( veSanitizeCssColor( '#ff660088' ) )->toBe( '#ff660088' );
} );

test( 'veSanitizeCssColor accepts named colors and keywords', function (): void {
	expect( veSanitizeCssColor( 'red' ) )->toBe( 'red' );
	expect( veSanitizeCssColor( 'currentColor' ) )->toBe( 'currentColor' );
	expect( veSanitizeCssColor( 'transparent' ) )->toBe( 'transparent' );
	expect( veSanitizeCssColor( 'inherit' ) )->toBe( 'inherit' );
} );

test( 'veSanitizeCssColor accepts rgb/rgba/hsl/hsla', function (): void {
	expect( veSanitizeCssColor( 'rgb(255, 0, 0)' ) )->toBe( 'rgb(255, 0, 0)' );
	expect( veSanitizeCssColor( 'rgba(0, 0, 0, 0.5)' ) )->toBe( 'rgba(0, 0, 0, 0.5)' );
	expect( veSanitizeCssColor( 'hsl(120, 100%, 50%)' ) )->toBe( 'hsl(120, 100%, 50%)' );
} );

test( 'veSanitizeCssColor rejects injection attempts', function (): void {
	expect( veSanitizeCssColor( 'red; background: url(evil)' ) )->toBeNull();
	expect( veSanitizeCssColor( '#fff} body {color: red' ) )->toBeNull();
	expect( veSanitizeCssColor( '' ) )->toBeNull();
	expect( veSanitizeCssColor( null ) )->toBeNull();
} );

test( 'veSanitizeCssColor returns default on invalid input', function (): void {
	expect( veSanitizeCssColor( 'invalid;stuff', 'black' ) )->toBe( 'black' );
	expect( veSanitizeCssColor( null, 'currentColor' ) )->toBe( 'currentColor' );
} );

test( 'veSanitizeCssDimension accepts valid dimensions', function (): void {
	expect( veSanitizeCssDimension( '10px' ) )->toBe( '10px' );
	expect( veSanitizeCssDimension( '1.5rem' ) )->toBe( '1.5rem' );
	expect( veSanitizeCssDimension( '50%' ) )->toBe( '50%' );
	expect( veSanitizeCssDimension( '0' ) )->toBe( '0' );
	expect( veSanitizeCssDimension( 'auto' ) )->toBe( 'auto' );
	expect( veSanitizeCssDimension( '-5px' ) )->toBe( '-5px' );
} );

test( 'veSanitizeCssDimension rejects injection attempts', function (): void {
	expect( veSanitizeCssDimension( '10px; color: red' ) )->toBe( '0' );
	expect( veSanitizeCssDimension( 'calc(100%)' ) )->toBe( '0' );
	expect( veSanitizeCssDimension( '' ) )->toBe( '0' );
	expect( veSanitizeCssDimension( null ) )->toBe( '0' );
} );

test( 'veSanitizeCssUnit validates allowed units', function (): void {
	expect( veSanitizeCssUnit( 'px' ) )->toBe( 'px' );
	expect( veSanitizeCssUnit( 'rem' ) )->toBe( 'rem' );
	expect( veSanitizeCssUnit( '%' ) )->toBe( '%' );
	expect( veSanitizeCssUnit( '' ) )->toBe( 'px' );
	expect( veSanitizeCssUnit( null ) )->toBe( 'px' );
	expect( veSanitizeCssUnit( 'invalid' ) )->toBe( 'px' );
} );

test( 'veSanitizeBorderStyle validates allowed styles', function (): void {
	expect( veSanitizeBorderStyle( 'solid' ) )->toBe( 'solid' );
	expect( veSanitizeBorderStyle( 'dashed' ) )->toBe( 'dashed' );
	expect( veSanitizeBorderStyle( 'none' ) )->toBe( 'none' );
	expect( veSanitizeBorderStyle( 'invalid' ) )->toBe( 'solid' );
	expect( veSanitizeBorderStyle( '' ) )->toBe( 'solid' );
	expect( veSanitizeBorderStyle( null ) )->toBe( 'solid' );
} );

test( 'veSanitizeHtmlId strips invalid characters', function (): void {
	expect( veSanitizeHtmlId( 'valid-id' ) )->toBe( 'valid-id' );
	expect( veSanitizeHtmlId( 'my_id_123' ) )->toBe( 'my_id_123' );
	expect( veSanitizeHtmlId( 'has spaces' ) )->toBe( 'hasspaces' );
	expect( veSanitizeHtmlId( 'has<html>tags' ) )->toBe( 'hashtmltags' );
} );

test( 'veSanitizeHtmlId prefixes IDs starting with digits', function (): void {
	expect( veSanitizeHtmlId( '123abc' ) )->toBe( 'id-123abc' );
	expect( veSanitizeHtmlId( '0' ) )->toBe( 'id-0' );
} );

test( 'veSanitizeHtmlId returns null for empty input', function (): void {
	expect( veSanitizeHtmlId( null ) )->toBeNull();
	expect( veSanitizeHtmlId( '' ) )->toBeNull();
	expect( veSanitizeHtmlId( '<<<>>>' ) )->toBeNull();
} );
