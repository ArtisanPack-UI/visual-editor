<?php

/**
 * ThemeJsonCommand Feature Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Feature\Commands
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

beforeEach( function (): void {
	$this->themeJsonPath = resource_path( 'theme.json' );

	if ( file_exists( $this->themeJsonPath ) ) {
		unlink( $this->themeJsonPath );
	}
} );

afterEach( function (): void {
	if ( file_exists( $this->themeJsonPath ) ) {
		unlink( $this->themeJsonPath );
	}
} );

test( 've:theme-json fails without options', function (): void {
	$this->artisan( 've:theme-json' )->assertFailed();
} );

test( 've:theme-json fails with both options', function (): void {
	$this->artisan( 've:theme-json', [ '--init' => true, '--validate' => true ] )->assertFailed();
} );

test( 've:theme-json --init creates theme.json', function (): void {
	$this->artisan( 've:theme-json', [ '--init' => true ] )->assertSuccessful();

	expect( file_exists( $this->themeJsonPath ) )->toBeTrue();

	$contents = json_decode( file_get_contents( $this->themeJsonPath ), true );

	expect( $contents )->toHaveKey( 'version' )
		->and( $contents['version'] )->toBe( 1 )
		->and( $contents )->toHaveKey( 'settings' );
} );

test( 've:theme-json --init fails when file already exists', function (): void {
	file_put_contents( $this->themeJsonPath, '{}' );

	$this->artisan( 've:theme-json', [ '--init' => true ] )->assertFailed();
} );

test( 've:theme-json --validate succeeds for valid file', function (): void {
	$valid = json_encode( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ],
				],
			],
		],
	], JSON_PRETTY_PRINT );

	file_put_contents( $this->themeJsonPath, $valid );

	$this->artisan( 've:theme-json', [ '--validate' => true ] )->assertSuccessful();
} );

test( 've:theme-json --validate fails for invalid file', function (): void {
	file_put_contents( $this->themeJsonPath, '{ invalid json }' );

	$this->artisan( 've:theme-json', [ '--validate' => true ] )->assertFailed();
} );

test( 've:theme-json --validate fails when file does not exist', function (): void {
	$this->artisan( 've:theme-json', [ '--validate' => true ] )->assertFailed();
} );

test( 've:theme-json --validate fails for missing version', function (): void {
	file_put_contents( $this->themeJsonPath, json_encode( [ 'settings' => [] ] ) );

	$this->artisan( 've:theme-json', [ '--validate' => true ] )->assertFailed();
} );
