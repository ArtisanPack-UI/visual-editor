<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Services\FontFileWriter;
use Illuminate\Support\Facades\Storage;

beforeEach( function (): void {
	Storage::fake( 'public' );
} );

it( 'writes a face to the configured disk and returns a deterministic path', function (): void {
	$writer = new FontFileWriter();

	$path = $writer->write( 'google', 'Inter', 400, 'normal', 'woff2', 'wOF2-bytes' );

	expect( $path )->toBe( 'visual-editor/fonts/google/inter/400-normal.woff2' );

	Storage::disk( 'public' )->assertExists( $path );
	expect( Storage::disk( 'public' )->get( $path ) )->toBe( 'wOF2-bytes' );
} );

it( 'computes the same path it writes to', function (): void {
	$writer = new FontFileWriter();

	expect( $writer->pathFor( 'google', 'Inter', 700, 'italic', 'woff2' ) )
		->toBe( 'visual-editor/fonts/google/inter/700-italic.woff2' );
} );

it( 'sanitizes provider and slug into safe path segments', function (): void {
	$writer = new FontFileWriter();

	$path = $writer->pathFor( '../evil', 'Foo/Bar', 400, 'normal', 'woff2' );

	expect( $path )->not->toContain( '..' )
		->and( $path )->not->toContain( '/../' )
		->and( $path )->toStartWith( 'visual-editor/fonts/evil/' )
		->and( $path )->toContain( 'foobar' );
} );

it( 'reports existence and deletes faces', function (): void {
	$writer = new FontFileWriter();

	$path = $writer->write( 'bunny', 'Roboto', 400, 'normal', 'woff2', 'x' );
	expect( $writer->exists( $path ) )->toBeTrue();

	$writer->delete( [ $path ] );
	expect( $writer->exists( $path ) )->toBeFalse();
} );

it( 'ignores missing paths on delete', function (): void {
	$writer = new FontFileWriter();

	$writer->delete( [ 'visual-editor/fonts/google/inter/900-normal.woff2', '' ] );

	expect( true )->toBeTrue();
} );

it( 'reads its disk and base path from config', function (): void {
	config( [
		'artisanpack.visual-editor.fonts.disk' => 'public',
		'artisanpack.visual-editor.fonts.path' => 'custom/fonts',
	] );

	$writer = new FontFileWriter();

	expect( $writer->diskName() )->toBe( 'public' )
		->and( $writer->pathFor( 'google', 'x', 400, 'normal', 'woff2' ) )
		->toStartWith( 'custom/fonts/' );
} );
