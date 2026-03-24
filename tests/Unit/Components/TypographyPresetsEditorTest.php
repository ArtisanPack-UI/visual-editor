<?php

/**
 * TypographyPresetsEditor Component Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Components
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\TypographyPresetsEditor;

test( 'typography presets editor can be instantiated', function (): void {
	$component = new TypographyPresetsEditor();

	expect( $component->uuid )->toStartWith( 've-' )
		->and( $component->typographyData )->toBeArray()
		->and( $component->typographyData )->not->toBeEmpty();
} );

test( 'typography presets editor loads default data', function (): void {
	$component = new TypographyPresetsEditor();

	expect( $component->typographyData )->toHaveKeys( [ 'fontFamilies', 'elements' ] )
		->and( $component->typographyData['fontFamilies'] )->toHaveKeys( [ 'heading', 'body', 'mono' ] )
		->and( $component->typographyData['elements'] )->toHaveKey( 'h1' )
		->and( $component->typographyData['elements'] )->toHaveKey( 'body' );
} );

test( 'typography presets editor accepts custom data', function (): void {
	$custom = [
		'fontFamilies' => [ 'heading' => '"Georgia", serif' ],
		'elements'     => [
			'h1' => [
				'fontSize'   => '3rem',
				'fontWeight' => '800',
				'lineHeight' => '1.1',
			],
		],
	];

	$component = new TypographyPresetsEditor( typography: $custom );

	expect( $component->typographyData['fontFamilies']['heading'] )->toBe( '"Georgia", serif' )
		->and( $component->typographyData['elements']['h1']['fontSize'] )->toBe( '3rem' );
} );

test( 'typography presets editor has default data for reset', function (): void {
	$component = new TypographyPresetsEditor();

	expect( $component->defaultData )->toHaveKeys( [ 'fontFamilies', 'elements' ] )
		->and( $component->defaultData['fontFamilies'] )->toHaveKeys( [ 'heading', 'body', 'mono' ] );
} );

test( 'typography presets editor renders', function (): void {
	$view = $this->blade( '<x-ve-typography-presets-editor />' );

	expect( $view )->not->toBeNull();
} );

test( 'typography presets editor renders title', function (): void {
	$this->blade( '<x-ve-typography-presets-editor />' )
		->assertSee( 'Typography' );
} );

test( 'typography presets editor renders section tabs', function (): void {
	$this->blade( '<x-ve-typography-presets-editor />' )
		->assertSee( 'Fonts' )
		->assertSee( 'Elements' )
		->assertSee( 'Scale' );
} );

test( 'typography presets editor renders reset button', function (): void {
	$this->blade( '<x-ve-typography-presets-editor />' )
		->assertSee( 'Reset to default' );
} );

test( 'typography presets editor renders css preview toggle', function (): void {
	$this->blade( '<x-ve-typography-presets-editor />' )
		->assertSee( 'CSS' );
} );

test( 'typography presets editor renders element labels', function (): void {
	$this->blade( '<x-ve-typography-presets-editor />' )
		->assertSee( 'Heading 1' )
		->assertSee( 'Body' )
		->assertSee( 'Code' );
} );

test( 'typography presets editor renders type scale controls', function (): void {
	$this->blade( '<x-ve-typography-presets-editor />' )
		->assertSee( 'Apply type scale' )
		->assertSee( 'Major Third (1.25)' );
} );

test( 'typography presets editor accepts base values for override mode', function (): void {
	$baseValues = [
		'fontFamilies' => [ 'heading' => '"Georgia", serif', 'body' => 'system-ui', 'mono' => 'monospace' ],
		'elements'     => [
			'h1' => [ 'fontSize' => '2rem', 'fontWeight' => '700' ],
		],
	];

	$component = new TypographyPresetsEditor( baseValues: $baseValues );

	expect( $component->baseValues )->not->toBeNull()
		->and( $component->baseValues['fontFamilies']['heading'] )->toBe( '"Georgia", serif' );
} );

test( 'typography presets editor defaults to null base values', function (): void {
	$component = new TypographyPresetsEditor();

	expect( $component->baseValues )->toBeNull();
} );

test( 'typography presets editor renders getStore dual detection', function (): void {
	$this->blade( '<x-ve-typography-presets-editor />' )
		->assertSee( '_getStore()', false );
} );
