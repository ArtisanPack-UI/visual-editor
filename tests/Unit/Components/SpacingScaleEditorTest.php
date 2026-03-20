<?php

/**
 * SpacingScaleEditor Component Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Components
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\SpacingScaleEditor;

test( 'spacing scale editor can be instantiated', function (): void {
	$component = new SpacingScaleEditor();

	expect( $component->uuid )->toStartWith( 've-' )
		->and( $component->spacingData )->toBeArray()
		->and( $component->spacingData )->toHaveKeys( [ 'scale', 'blockGap', 'customSteps' ] );
} );

test( 'spacing scale editor loads default scale entries', function (): void {
	$component = new SpacingScaleEditor();

	expect( $component->spacingData['scale'] )->toHaveCount( 7 );

	foreach ( $component->spacingData['scale'] as $entry ) {
		expect( $entry )->toHaveKeys( [ 'name', 'slug', 'value' ] );
	}
} );

test( 'spacing scale editor has default block gap', function (): void {
	$component = new SpacingScaleEditor();

	expect( $component->spacingData['blockGap'] )->toBe( 'md' );
} );

test( 'spacing scale editor accepts custom spacing data', function (): void {
	$customSpacing = [
		'scale' => [
			[ 'name' => 'Small', 'slug' => 'sm', 'value' => '0.25rem' ],
			[ 'name' => 'Medium', 'slug' => 'md', 'value' => '0.5rem' ],
		],
		'blockGap'    => 'sm',
		'customSteps' => [],
	];

	$component = new SpacingScaleEditor( spacing: $customSpacing );

	expect( $component->spacingData['scale'] )->toHaveCount( 2 )
		->and( $component->spacingData['blockGap'] )->toBe( 'sm' );
} );

test( 'spacing scale editor renders', function (): void {
	$view = $this->blade( '<x-ve-spacing-scale-editor />' );

	expect( $view )->not->toBeNull();
} );

test( 'spacing scale editor renders spacing title', function (): void {
	$this->blade( '<x-ve-spacing-scale-editor />' )
		->assertSee( 'Spacing' );
} );

test( 'spacing scale editor renders reset button', function (): void {
	$this->blade( '<x-ve-spacing-scale-editor />' )
		->assertSee( 'Reset to default' );
} );

test( 'spacing scale editor renders preset buttons', function (): void {
	$view = $this->blade( '<x-ve-spacing-scale-editor />' );

	$view->assertSee( 'Compact' )
		->assertSee( 'Default' )
		->assertSee( 'Spacious' );
} );

test( 'spacing scale editor renders add step button', function (): void {
	$this->blade( '<x-ve-spacing-scale-editor />' )
		->assertSee( 'Add Step' );
} );

test( 'spacing scale editor renders block gap section', function (): void {
	$this->blade( '<x-ve-spacing-scale-editor />' )
		->assertSee( 'Block gap' );
} );

test( 'spacing scale editor renders css preview toggle', function (): void {
	$this->blade( '<x-ve-spacing-scale-editor />' )
		->assertSee( 'CSS' );
} );
