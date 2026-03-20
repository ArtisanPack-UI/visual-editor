<?php

/**
 * SpacingScaleManager Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\SpacingScaleManager;

test( 'spacing scale manager initializes with defaults', function (): void {
	$manager = new SpacingScaleManager();
	$scale   = $manager->getScale();

	expect( $scale )->toHaveKey( 'xs' )
		->and( $scale )->toHaveKey( 'sm' )
		->and( $scale )->toHaveKey( 'md' )
		->and( $scale )->toHaveKey( 'lg' )
		->and( $scale )->toHaveKey( 'xl' )
		->and( $scale )->toHaveKey( '2xl' )
		->and( $scale )->toHaveKey( '3xl' );
} );

test( 'default scale has 7 entries', function (): void {
	expect( SpacingScaleManager::DEFAULT_SCALE )->toHaveCount( 7 );
} );

test( 'default block gap is md', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->getBlockGap() )->toBe( 'md' );
} );

test( 'spacing scale manager accepts custom config', function (): void {
	$config = [
		'scale' => [
			'sm' => '0.25rem',
			'md' => '0.5rem',
			'lg' => '1rem',
		],
		'blockGap' => 'sm',
	];

	$manager = new SpacingScaleManager( $config );

	expect( $manager->getScale() )->toHaveCount( 3 )
		->and( $manager->getBlockGap() )->toBe( 'sm' );
} );

test( 'get step returns entry by slug', function (): void {
	$manager = new SpacingScaleManager();
	$step    = $manager->getStep( 'md' );

	expect( $step )->not->toBeNull()
		->and( $step['name'] )->toBe( 'Medium' )
		->and( $step['slug'] )->toBe( 'md' )
		->and( $step['value'] )->toBe( '1rem' );
} );

test( 'get step returns null for missing slug', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->getStep( 'nonexistent' ) )->toBeNull();
} );

test( 'get step value returns css value', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->getStepValue( 'md' ) )->toBe( '1rem' );
} );

test( 'get step value returns null for missing slug', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->getStepValue( 'nonexistent' ) )->toBeNull();
} );

test( 'set step updates an existing step', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'md', 'Medium', '1.25rem' );

	expect( $manager->getStepValue( 'md' ) )->toBe( '1.25rem' );
} );

test( 'set step adds a custom step for non-standard slug', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'hero', 'Hero', '6rem' );

	expect( $manager->hasStep( 'hero' ) )->toBeTrue()
		->and( $manager->getStepValue( 'hero' ) )->toBe( '6rem' );
} );

test( 'set step throws on invalid dimension', function (): void {
	$manager = new SpacingScaleManager();

	expect( fn () => $manager->setStep( 'md', 'Medium', 'not-a-dimension' ) )
		->toThrow( InvalidArgumentException::class );
} );

test( 'set step allows zero value', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'none', 'None', '0' );

	expect( $manager->getStepValue( 'none' ) )->toBe( '0' );
} );

test( 'remove step deletes a standard entry', function (): void {
	$manager = new SpacingScaleManager();
	$manager->removeStep( 'xs' );

	expect( $manager->hasStep( 'xs' ) )->toBeFalse();
} );

test( 'remove step deletes a custom entry', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'hero', 'Hero', '6rem' );
	$manager->removeStep( 'hero' );

	expect( $manager->hasStep( 'hero' ) )->toBeFalse();
} );

test( 'has step returns true for existing slug', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->hasStep( 'md' ) )->toBeTrue();
} );

test( 'has step returns false for missing slug', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->hasStep( 'nonexistent' ) )->toBeFalse();
} );

test( 'set block gap updates the gap slug', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setBlockGap( 'lg' );

	expect( $manager->getBlockGap() )->toBe( 'lg' );
} );

test( 'get block gap value returns the css value for the gap step', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->getBlockGapValue() )->toBe( '1rem' );
} );

test( 'set block gap falls back to default for unknown slug', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setBlockGap( 'nonexistent' );

	expect( $manager->getBlockGap() )->toBe( 'md' )
		->and( $manager->getBlockGapValue() )->toBe( '1rem' );
} );

test( 'reset to defaults restores default scale and block gap', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'md', 'Medium', '2rem' );
	$manager->setStep( 'hero', 'Hero', '6rem' );
	$manager->setBlockGap( 'xl' );
	$manager->resetToDefaults();

	expect( $manager->getStepValue( 'md' ) )->toBe( '1rem' )
		->and( $manager->hasStep( 'hero' ) )->toBeFalse()
		->and( $manager->getBlockGap() )->toBe( 'md' );
} );

test( 'apply preset compact sets smaller values', function (): void {
	$manager = new SpacingScaleManager();
	$manager->applyPreset( 'compact' );

	expect( $manager->getStepValue( 'md' ) )->toBe( '0.5rem' )
		->and( $manager->getBlockGap() )->toBe( 'sm' );
} );

test( 'apply preset default restores standard values', function (): void {
	$manager = new SpacingScaleManager();
	$manager->applyPreset( 'compact' );
	$manager->applyPreset( 'default' );

	expect( $manager->getStepValue( 'md' ) )->toBe( '1rem' )
		->and( $manager->getBlockGap() )->toBe( 'md' );
} );

test( 'apply preset spacious sets larger values', function (): void {
	$manager = new SpacingScaleManager();
	$manager->applyPreset( 'spacious' );

	expect( $manager->getStepValue( 'md' ) )->toBe( '1.5rem' )
		->and( $manager->getBlockGap() )->toBe( 'lg' );
} );

test( 'apply preset throws on invalid preset', function (): void {
	$manager = new SpacingScaleManager();

	expect( fn () => $manager->applyPreset( 'invalid' ) )
		->toThrow( InvalidArgumentException::class );
} );

test( 'apply preset clears custom steps', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'hero', 'Hero', '6rem' );
	$manager->applyPreset( 'compact' );

	expect( $manager->hasStep( 'hero' ) )->toBeFalse();
} );

test( 'get presets returns all preset configurations', function (): void {
	$manager = new SpacingScaleManager();
	$presets = $manager->getPresets();

	expect( $presets )->toHaveKeys( [ 'compact', 'default', 'spacious' ] );

	foreach ( $presets as $preset ) {
		expect( $preset )->toHaveKeys( [ 'scale', 'blockGap' ] );
	}
} );

test( 'resolve spacing reference returns css value', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->resolveSpacingReference( 'spacing:md' ) )->toBe( '1rem' );
} );

test( 'resolve spacing reference returns original for non-reference', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->resolveSpacingReference( '16px' ) )->toBe( '16px' );
} );

test( 'resolve spacing reference returns original for missing slug', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->resolveSpacingReference( 'spacing:nonexistent' ) )->toBe( 'spacing:nonexistent' );
} );

test( 'generate css properties returns valid css', function (): void {
	$manager = new SpacingScaleManager( [
		'scale' => [ 'md' => '1rem' ],
	] );

	$css = $manager->generateCssProperties();

	expect( $css )->toContain( '--ve-spacing-md: 1rem;' )
		->and( $css )->toContain( '--ve-block-gap: var(--ve-spacing-md);' );
} );

test( 'generate css properties includes custom steps', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'hero', 'Hero', '6rem' );

	$css = $manager->generateCssProperties();

	expect( $css )->toContain( '--ve-spacing-hero: 6rem;' );
} );

test( 'generate css block wraps properties in root selector', function (): void {
	$manager = new SpacingScaleManager( [
		'scale' => [ 'md' => '1rem' ],
	] );

	$css = $manager->generateCssBlock();

	expect( $css )->toStartWith( ':root {' )
		->and( $css )->toEndWith( '}' )
		->and( $css )->toContain( '--ve-spacing-md: 1rem;' );
} );

test( 'generate css block still emits block gap with empty scale', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setScale( [] );

	$css = $manager->generateCssBlock();

	// With empty scale, neither blockGap nor DEFAULT_BLOCK_GAP steps exist,
	// so a hardcoded fallback CSS value is used.
	expect( $css )->toContain( '--ve-block-gap: 1rem;' );
} );

test( 'to store format returns correct structure', function (): void {
	$manager = new SpacingScaleManager();
	$store   = $manager->toStoreFormat();

	expect( $store )->toHaveKeys( [ 'scale', 'blockGap', 'customSteps' ] )
		->and( $store['scale'] )->toBeArray()
		->and( array_is_list( $store['scale'] ) )->toBeTrue()
		->and( $store['scale'] )->toHaveCount( 7 )
		->and( $store['blockGap'] )->toBe( 'md' )
		->and( $store['customSteps'] )->toBeArray()
		->and( $store['customSteps'] )->toHaveCount( 0 );

	foreach ( $store['scale'] as $entry ) {
		expect( $entry )->toHaveKeys( [ 'name', 'slug', 'value' ] );
	}
} );

test( 'to store format includes custom steps', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'hero', 'Hero', '6rem' );

	$store = $manager->toStoreFormat();

	expect( $store['customSteps'] )->toHaveCount( 1 )
		->and( $store['customSteps'][0]['slug'] )->toBe( 'hero' );
} );

test( 'from store format rebuilds scale', function (): void {
	$manager = new SpacingScaleManager();
	$manager->fromStoreFormat( [
		'scale' => [
			[ 'name' => 'Small', 'slug' => 'sm', 'value' => '0.5rem' ],
			[ 'name' => 'Medium', 'slug' => 'md', 'value' => '1rem' ],
		],
		'blockGap'    => 'sm',
		'customSteps' => [
			[ 'name' => 'Hero', 'slug' => 'hero', 'value' => '6rem' ],
		],
	] );

	expect( $manager->getStepValue( 'sm' ) )->toBe( '0.5rem' )
		->and( $manager->getStepValue( 'md' ) )->toBe( '1rem' )
		->and( $manager->getBlockGap() )->toBe( 'sm' )
		->and( $manager->hasStep( 'hero' ) )->toBeTrue()
		->and( $manager->getStepValue( 'hero' ) )->toBe( '6rem' );
} );

test( 'from store format ignores invalid entries', function (): void {
	$manager = new SpacingScaleManager();
	$manager->fromStoreFormat( [
		'scale' => [
			[ 'name' => 'Valid', 'slug' => 'valid', 'value' => '1rem' ],
			[ 'name' => 'Missing Value' ],
			[ 'slug' => 'missing-name', 'value' => '2rem' ],
		],
	] );

	$scale = $manager->getScale();

	expect( $scale )->toHaveCount( 1 )
		->and( $manager->hasStep( 'valid' ) )->toBeTrue();
} );

test( 'set scale replaces all entries and clears custom steps', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'hero', 'Hero', '6rem' );
	$manager->setScale( [
		'only' => [
			'name'  => 'Only',
			'slug'  => 'only',
			'value' => '1rem',
		],
	] );

	expect( $manager->getScale() )->toHaveCount( 1 )
		->and( $manager->hasStep( 'only' ) )->toBeTrue()
		->and( $manager->hasStep( 'md' ) )->toBeFalse()
		->and( $manager->hasStep( 'hero' ) )->toBeFalse();
} );

test( 'get default scale returns the constant', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->getDefaultScale() )->toBe( SpacingScaleManager::DEFAULT_SCALE );
} );

test( 'get default block gap returns md', function (): void {
	$manager = new SpacingScaleManager();

	expect( $manager->getDefaultBlockGap() )->toBe( 'md' );
} );

test( 'spacing scale manager is resolved from container', function (): void {
	$manager = app( 'visual-editor.spacing-scale' );

	expect( $manager )->toBeInstanceOf( SpacingScaleManager::class );
} );

test( 'spacing scale manager singleton returns same instance', function (): void {
	$first  = app( 'visual-editor.spacing-scale' );
	$second = app( 'visual-editor.spacing-scale' );

	expect( $first )->toBe( $second );
} );

test( 'spacing scale manager class binding resolves to singleton', function (): void {
	$fromString = app( 'visual-editor.spacing-scale' );
	$fromClass  = app( SpacingScaleManager::class );

	expect( $fromString )->toBe( $fromClass );
} );

test( 'custom steps from config are loaded', function (): void {
	$config = [
		'customSteps' => [
			[ 'name' => 'Hero', 'slug' => 'hero', 'value' => '6rem' ],
		],
	];

	$manager = new SpacingScaleManager( $config );

	expect( $manager->hasStep( 'hero' ) )->toBeTrue()
		->and( $manager->getStepValue( 'hero' ) )->toBe( '6rem' );
} );

test( 'scale and custom steps merge in get scale', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'hero', 'Hero', '6rem' );

	$scale = $manager->getScale();

	expect( $scale )->toHaveKey( 'md' )
		->and( $scale )->toHaveKey( 'hero' );
} );

test( 'remove step rehydrates block gap when gap step is removed', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setBlockGap( 'xs' );
	$manager->removeStep( 'xs' );

	expect( $manager->getBlockGap() )->toBe( 'md' );
} );

test( 'set scale rehydrates block gap when gap step no longer exists', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setBlockGap( 'xl' );
	$manager->setScale( [
		'sm' => [ 'name' => 'Small', 'slug' => 'sm', 'value' => '0.5rem' ],
	] );

	expect( $manager->getBlockGap() )->toBe( 'md' );
} );

test( 'set step sanitizes slug', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'my<script>slug', 'Test', '1rem' );

	expect( $manager->hasStep( 'myscriptslug' ) )->toBeTrue()
		->and( $manager->hasStep( 'my<script>slug' ) )->toBeFalse();
} );

test( 'set step with all-special-char slug falls back to unnamed', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( '!!!', 'Special', '1rem' );

	expect( $manager->hasStep( 'unnamed' ) )->toBeTrue()
		->and( $manager->getStepValue( 'unnamed' ) )->toBe( '1rem' );
} );

test( 'constructor validates block gap against available steps', function (): void {
	$config = [
		'blockGap' => 'nonexistent',
	];

	$manager = new SpacingScaleManager( $config );

	expect( $manager->getBlockGap() )->toBe( 'md' );
} );

test( 'from store format validates block gap against imported scale', function (): void {
	$manager = new SpacingScaleManager();
	$manager->fromStoreFormat( [
		'scale' => [
			[ 'name' => 'Small', 'slug' => 'sm', 'value' => '0.5rem' ],
		],
		'blockGap' => 'sm',
	] );

	expect( $manager->getBlockGap() )->toBe( 'sm' );
} );

test( 'from store format sanitizes and validates block gap value', function (): void {
	$manager = new SpacingScaleManager();
	$manager->fromStoreFormat( [
		'blockGap' => 'md<script>alert(1)</script>',
	] );

	// Sanitized slug 'mdscriptalert1script' doesn't exist as a step,
	// so blockGap falls back to DEFAULT_BLOCK_GAP.
	expect( $manager->getBlockGap() )->toBe( 'md' );
} );

test( 'set step accepts negative dimension values', function (): void {
	$manager = new SpacingScaleManager();
	$manager->setStep( 'negative', 'Negative', '-1rem' );

	expect( $manager->getStepValue( 'negative' ) )->toBe( '-1rem' );
} );

test( 'constructor skips custom steps that collide with built-in scale keys', function (): void {
	$config = [
		'customSteps' => [
			[ 'name' => 'Overwrite MD', 'slug' => 'md', 'value' => '99rem' ],
			[ 'name' => 'Unique', 'slug' => 'hero', 'value' => '6rem' ],
		],
	];

	$manager = new SpacingScaleManager( $config );

	// 'md' should keep the default scale value, not the custom step
	expect( $manager->getStepValue( 'md' ) )->toBe( '1rem' )
		->and( $manager->hasStep( 'hero' ) )->toBeTrue();
} );

test( 'from store format skips custom steps that collide with scale keys', function (): void {
	$manager = new SpacingScaleManager();
	$manager->fromStoreFormat( [
		'scale' => [
			[ 'name' => 'Small', 'slug' => 'sm', 'value' => '0.5rem' ],
		],
		'customSteps' => [
			[ 'name' => 'Collides', 'slug' => 'sm', 'value' => '99rem' ],
			[ 'name' => 'Unique', 'slug' => 'hero', 'value' => '6rem' ],
		],
	] );

	expect( $manager->getStepValue( 'sm' ) )->toBe( '0.5rem' )
		->and( $manager->hasStep( 'hero' ) )->toBeTrue();
} );

test( 'from store format skips duplicate custom steps after sanitization', function (): void {
	$manager = new SpacingScaleManager();
	$manager->fromStoreFormat( [
		'customSteps' => [
			[ 'name' => 'First', 'slug' => 'hero', 'value' => '5rem' ],
			[ 'name' => 'Second', 'slug' => 'hero', 'value' => '10rem' ],
		],
	] );

	// First entry wins
	expect( $manager->getStepValue( 'hero' ) )->toBe( '5rem' );
} );

test( 'constructor skips custom steps with invalid dimensions', function (): void {
	$config = [
		'customSteps' => [
			[ 'name' => 'Valid', 'slug' => 'valid', 'value' => '2rem' ],
			[ 'name' => 'Invalid', 'slug' => 'invalid', 'value' => 'not-a-dimension' ],
		],
	];

	$manager = new SpacingScaleManager( $config );

	expect( $manager->hasStep( 'valid' ) )->toBeTrue()
		->and( $manager->hasStep( 'invalid' ) )->toBeFalse();
} );

test( 'from store format skips entries with invalid dimensions', function (): void {
	$manager = new SpacingScaleManager();
	$manager->fromStoreFormat( [
		'scale' => [
			[ 'name' => 'Good', 'slug' => 'good', 'value' => '1rem' ],
			[ 'name' => 'Bad', 'slug' => 'bad', 'value' => 'javascript:alert(1)' ],
		],
		'customSteps' => [
			[ 'name' => 'Custom Good', 'slug' => 'custom-good', 'value' => '3px' ],
			[ 'name' => 'Custom Bad', 'slug' => 'custom-bad', 'value' => '<img onerror>' ],
		],
	] );

	expect( $manager->hasStep( 'good' ) )->toBeTrue()
		->and( $manager->hasStep( 'bad' ) )->toBeFalse()
		->and( $manager->hasStep( 'custom-good' ) )->toBeTrue()
		->and( $manager->hasStep( 'custom-bad' ) )->toBeFalse();
} );
