<?php

/**
 * StylePreviewToolbar Component Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Components
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\StylePreviewToolbar;

test( 'style preview toolbar can be instantiated', function (): void {
	$component = new StylePreviewToolbar();

	expect( $component->uuid )->toBeString()
		->and( $component->uuid )->toStartWith( 've-' );
} );

test( 'style preview toolbar accepts custom id', function (): void {
	$component = new StylePreviewToolbar( id: 'my-toolbar' );

	expect( $component->uuid )->toContain( 'my-toolbar' );
} );

test( 'style preview toolbar renders', function (): void {
	$view = $this->blade(
		'<div x-data="{ viewport: \'desktop\', previewMode: \'live\', switchToLivePreview() {}, switchToSavedPreview() {} }"><x-ve-style-preview-toolbar /></div>',
	);

	expect( $view )->not->toBeNull();
} );

test( 'style preview toolbar contains viewport buttons', function (): void {
	$this->blade(
		'<div x-data="{ viewport: \'desktop\', previewMode: \'live\', switchToLivePreview() {}, switchToSavedPreview() {} }"><x-ve-style-preview-toolbar /></div>',
	)
		->assertSee( 'radiogroup', false );
} );

test( 'style preview toolbar contains before after toggle', function (): void {
	$this->blade(
		'<div x-data="{ viewport: \'desktop\', previewMode: \'live\', switchToLivePreview() {}, switchToSavedPreview() {} }"><x-ve-style-preview-toolbar /></div>',
	)
		->assertSee( 'previewMode', false );
} );
