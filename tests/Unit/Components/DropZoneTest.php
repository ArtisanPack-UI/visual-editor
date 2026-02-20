<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DropZone;

test( 'drop zone can be instantiated with defaults', function (): void {
	$component = new DropZone();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->label )->toBeNull();
	expect( $component->acceptTypes )->toBe( [] );
	expect( $component->allowFiles )->toBeTrue();
	expect( $component->allowBlocks )->toBeTrue();
	expect( $component->allowHtml )->toBeTrue();
	expect( $component->maxFileSize )->toBeNull();
	expect( $component->emptyMessage )->toBeNull();
	expect( $component->showInsertionLine )->toBeTrue();
	expect( $component->disabled )->toBeFalse();
} );

test( 'drop zone accepts custom props', function (): void {
	$component = new DropZone(
		id: 'image-upload',
		label: 'Upload images',
		acceptTypes: [ 'image/*' ],
		allowFiles: true,
		allowBlocks: false,
		allowHtml: false,
		maxFileSize: 5120,
		emptyMessage: 'Drop images here',
		showInsertionLine: false,
		disabled: true,
	);

	expect( $component->uuid )->toContain( 'image-upload' );
	expect( $component->label )->toBe( 'Upload images' );
	expect( $component->acceptTypes )->toBe( [ 'image/*' ] );
	expect( $component->allowBlocks )->toBeFalse();
	expect( $component->maxFileSize )->toBe( 5120 );
	expect( $component->emptyMessage )->toBe( 'Drop images here' );
	expect( $component->disabled )->toBeTrue();
} );

test( 'drop zone renders', function (): void {
	$view = $this->blade( '<x-ve-drop-zone>Content</x-ve-drop-zone>' );
	expect( $view )->not->toBeNull();
} );

test( 'drop zone renders with empty message', function (): void {
	$this->blade( '<x-ve-drop-zone emptyMessage="Drop files here" />' )
		->assertSee( 'Drop files here' );
} );

test( 'drop zone renders with slot content', function (): void {
	$this->blade( '<x-ve-drop-zone>Zone Content</x-ve-drop-zone>' )
		->assertSee( 'Zone Content' );
} );
