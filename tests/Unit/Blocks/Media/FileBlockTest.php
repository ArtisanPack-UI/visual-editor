<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\FileBlock;

test( 'file block has correct type and category', function (): void {
	$block = new FileBlock();

	expect( $block->getType() )->toBe( 'file' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'file block content schema has url filename fileSize', function (): void {
	$block  = new FileBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'filename' );
	expect( $schema )->toHaveKey( 'fileSize' );
} );

test( 'file block defaults to show download button', function (): void {
	$block    = new FileBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['showDownloadButton'] )->toBeTrue();
} );

test( 'file block renders file info and download link', function (): void {
	$block  = new FileBlock();
	$output = $block->render(
		[ 'url' => 'doc.pdf', 'filename' => 'Document.pdf', 'fileSize' => '1.5 MB' ],
		[ 'showDownloadButton' => true ],
	);

	expect( $output )->toContain( 'Document.pdf' );
	expect( $output )->toContain( '1.5 MB' );
	expect( $output )->toContain( 'download' );
} );
