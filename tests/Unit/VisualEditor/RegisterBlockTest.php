<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use Tests\Fixtures\TestBlockMetadata;

beforeEach( function () {
	$this->tempDir = sys_get_temp_dir() . '/visual-editor-register-block-' . bin2hex( random_bytes( 4 ) );
	mkdir( $this->tempDir );
} );

afterEach( function () {
	if ( isset( $this->tempDir ) && is_dir( $this->tempDir ) ) {
		array_map( 'unlink', glob( $this->tempDir . '/*' ) ?: [] );
		rmdir( $this->tempDir );
	}
} );

it( 'registers a block from a block.json path', function () {
	$path = $this->tempDir . '/block.json';
	file_put_contents( $path, json_encode( [
		'name'     => 'tests/path-block',
		'title'    => 'Path Block',
		'category' => 'artisanpack',
	] ) );

	VisualEditor::registerBlock( $path );

	$block = app( BlockTypeRegistry::class )->get( 'tests/path-block' );

	expect( $block )->not->toBeNull()
		->and( $block['name'] )->toBe( 'tests/path-block' )
		->and( $block['title'] )->toBe( 'Path Block' );
} );

it( 'registers a block from a class implementing ProvidesBlockMetadata', function () {
	VisualEditor::registerBlock( TestBlockMetadata::class );

	$block = app( BlockTypeRegistry::class )->get( 'tests/metadata-block' );

	expect( $block )->not->toBeNull()
		->and( $block['title'] )->toBe( 'Metadata Block' )
		->and( $block['category'] )->toBe( 'artisanpack' )
		->and( $block['attributes'] )->toBe( [
			'label' => [ 'type' => 'string', 'default' => '' ],
		] );
} );

it( 'registers a block from a closure returning metadata', function () {
	VisualEditor::registerBlock( static fn (): array => [
		'name'     => 'tests/closure-block',
		'title'    => 'Closure Block',
		'category' => 'artisanpack',
	] );

	$block = app( BlockTypeRegistry::class )->get( 'tests/closure-block' );

	expect( $block )->not->toBeNull()
		->and( $block['title'] )->toBe( 'Closure Block' );
} );

it( 'throws when the block.json file is missing', function () {
	VisualEditor::registerBlock( $this->tempDir . '/missing.json' );
} )->throws( InvalidArgumentException::class, 'block.json not found' );

it( 'throws when the class does not implement ProvidesBlockMetadata', function () {
	VisualEditor::registerBlock( \stdClass::class );
} )->throws( InvalidArgumentException::class, 'must implement' );

it( 'throws when the closure does not return an array', function () {
	VisualEditor::registerBlock( static fn () => 'not-an-array' );
} )->throws( InvalidArgumentException::class, 'must return an array' );

it( 'throws when metadata is missing a name', function () {
	VisualEditor::registerBlock( static fn (): array => [ 'title' => 'Nameless' ] );
} )->throws( InvalidArgumentException::class, 'name' );

it( 'registers the bundled callout reference block by default', function () {
	$block = app( BlockTypeRegistry::class )->get( 'artisanpack/callout' );

	expect( $block )->not->toBeNull()
		->and( $block['category'] )->toBe( 'design' )
		->and( $block['attributes'] )->toHaveKey( 'severity' )
		->and( $block['attributes'] )->toHaveKey( 'icon' )
		->and( $block['attributes'] )->toHaveKey( 'content' );
} );

it( 'includes artisanpack/callout in the enabled blocks allow-list', function () {
	$enabled = config( 'artisanpack.visual-editor.enabled_blocks', [] );

	expect( $enabled )->toContain( 'artisanpack/callout' );
} );
