<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Requests\Fonts\UploadFontRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Validate an upload payload against the request's own rules and messages.
 *
 * @param  array<string, mixed>  $payload
 */
function validateUpload( array $payload ): Illuminate\Contracts\Validation\Validator
{
	$request   = new UploadFontRequest();
	$validator = Validator::make( $payload, $request->rules(), $request->messages() );

	$request->withValidator( $validator );

	return $validator;
}

it( 'passes with a family and a valid font file', function (): void {
	$validator = validateUpload( [
		'family' => 'My Font',
		'faces'  => [
			[ 'file' => UploadedFile::fake()->create( 'my-font.woff2', 40 ), 'weight' => 400, 'style' => 'normal' ],
		],
	] );

	expect( $validator->passes() )->toBeTrue();
} );

it( 'accepts every configured web-font extension', function ( string $extension ): void {
	$validator = validateUpload( [
		'family' => 'My Font',
		'faces'  => [ [ 'file' => UploadedFile::fake()->create( 'face.' . $extension, 40 ) ] ],
	] );

	expect( $validator->passes() )->toBeTrue();
} )->with( [ 'woff2', 'woff', 'ttf', 'otf' ] );

it( 'requires a family name', function (): void {
	$validator = validateUpload( [
		'faces' => [ [ 'file' => UploadedFile::fake()->create( 'face.ttf', 10 ) ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'family' ) )->toBeTrue();
} );

it( 'requires at least one face', function (): void {
	$validator = validateUpload( [ 'family' => 'My Font', 'faces' => [] ] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces' ) )->toBeTrue();
} );

it( 'rejects a disallowed file extension', function (): void {
	$validator = validateUpload( [
		'family' => 'My Font',
		'faces'  => [ [ 'file' => UploadedFile::fake()->create( 'malware.zip', 10 ) ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces.0.file' ) )->toBeTrue();
} );

it( 'rejects a file larger than the configured maximum', function (): void {
	config()->set( 'artisanpack.visual-editor.fonts.upload.max_kilobytes', 100 );

	$validator = validateUpload( [
		'family' => 'My Font',
		'faces'  => [ [ 'file' => UploadedFile::fake()->create( 'huge.ttf', 200 ) ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces.0.file' ) )->toBeTrue();
} );

it( 'rejects uploads whose combined size exceeds the aggregate maximum', function (): void {
	config()->set( 'artisanpack.visual-editor.fonts.upload.max_total_kilobytes', 100 );

	// Each file is under the per-file cap, but together they exceed the
	// aggregate ceiling.
	$validator = validateUpload( [
		'family' => 'My Font',
		'faces'  => [
			[ 'file' => UploadedFile::fake()->create( 'a.woff2', 60 ) ],
			[ 'file' => UploadedFile::fake()->create( 'b.woff2', 60 ) ],
		],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces' ) )->toBeTrue();
} );

it( 'allows uploads within the aggregate maximum', function (): void {
	config()->set( 'artisanpack.visual-editor.fonts.upload.max_total_kilobytes', 100 );

	$validator = validateUpload( [
		'family' => 'My Font',
		'faces'  => [
			[ 'file' => UploadedFile::fake()->create( 'a.woff2', 30 ) ],
			[ 'file' => UploadedFile::fake()->create( 'b.woff2', 30 ) ],
		],
	] );

	expect( $validator->passes() )->toBeTrue();
} );

it( 'rejects an unknown style', function (): void {
	$validator = validateUpload( [
		'family' => 'My Font',
		'faces'  => [ [ 'file' => UploadedFile::fake()->create( 'face.ttf', 10 ), 'style' => 'oblique' ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces.0.style' ) )->toBeTrue();
} );
