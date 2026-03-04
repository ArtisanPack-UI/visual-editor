<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\BlockPlaceholder;

test( 'block placeholder can be instantiated with defaults', function (): void {
	$component = new BlockPlaceholder();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->icon )->toBe( 'photo' );
	expect( $component->blockName )->toBe( '' );
	expect( $component->description )->toBe( '' );
	expect( $component->id )->toBeNull();
} );

test( 'block placeholder accepts custom props', function (): void {
	$component = new BlockPlaceholder(
		icon: 'video',
		blockName: 'Video',
		description: 'Upload a video file.',
		id: 'my-placeholder',
	);

	expect( $component->uuid )->toContain( 'my-placeholder' );
	expect( $component->icon )->toBe( 'video' );
	expect( $component->blockName )->toBe( 'Video' );
	expect( $component->description )->toBe( 'Upload a video file.' );
} );

test( 'block placeholder icon svg returns correct svg for each icon', function (): void {
	foreach ( BlockPlaceholder::ICON_MAP as $key => $svg ) {
		$component = new BlockPlaceholder( icon: $key );
		expect( $component->iconSvg() )->toBe( $svg );
	}
} );

test( 'block placeholder icon svg falls back to photo for unknown icon', function (): void {
	$component = new BlockPlaceholder( icon: 'unknown' );
	expect( $component->iconSvg() )->toBe( BlockPlaceholder::ICON_MAP['photo'] );
} );

test( 'block placeholder icon map has photo video music images and document keys', function (): void {
	expect( BlockPlaceholder::ICON_MAP )->toHaveKey( 'photo' );
	expect( BlockPlaceholder::ICON_MAP )->toHaveKey( 'video' );
	expect( BlockPlaceholder::ICON_MAP )->toHaveKey( 'music' );
	expect( BlockPlaceholder::ICON_MAP )->toHaveKey( 'images' );
	expect( BlockPlaceholder::ICON_MAP )->toHaveKey( 'document' );
} );

test( 'block placeholder generates unique uuid', function (): void {
	$a = new BlockPlaceholder();
	$b = new BlockPlaceholder();

	expect( $a->uuid )->not->toBe( $b->uuid );
} );

test( 'block placeholder renders', function (): void {
	$view = $this->blade( '<x-ve-block-placeholder icon="photo" block-name="Image" description="Pick an image.">Actions</x-ve-block-placeholder>' );
	expect( $view )->not->toBeNull();
} );

test( 'block placeholder renders block name and description', function (): void {
	$this->blade( '<x-ve-block-placeholder icon="photo" block-name="Image" description="Pick an image." />' )
		->assertSee( 'Image' )
		->assertSee( 'Pick an image.' );
} );

test( 'block placeholder renders action slot', function (): void {
	$this->blade( '<x-ve-block-placeholder icon="photo" block-name="Image" description="Desc"><button>Upload</button></x-ve-block-placeholder>' )
		->assertSee( 'Upload' );
} );
