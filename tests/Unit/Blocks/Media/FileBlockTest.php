<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\FileBlock;

test( 'file block has correct type and category', function (): void {
	$block = new FileBlock();

	expect( $block->getType() )->toBe( 'file' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'file block content schema has url and downloadButtonText', function (): void {
	$block  = new FileBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'downloadButtonText' );
} );

test( 'file block content schema url type is media_picker', function (): void {
	$block  = new FileBlock();
	$schema = $block->getContentSchema();

	expect( $schema['url']['type'] )->toBe( 'media_picker' );
} );

test( 'file block content schema has settings panel toggles and range', function (): void {
	$block  = new FileBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'showDownloadButton' );
	expect( $schema )->toHaveKey( 'openInNewTab' );
	expect( $schema )->toHaveKey( 'displayPreview' );
	expect( $schema )->toHaveKey( 'previewHeight' );

	expect( $schema['showDownloadButton']['type'] )->toBe( 'toggle' );
	expect( $schema['openInNewTab']['type'] )->toBe( 'toggle' );
	expect( $schema['displayPreview']['type'] )->toBe( 'toggle' );
	expect( $schema['previewHeight']['type'] )->toBe( 'range' );
} );

test( 'file block settings panel fields have panel key', function (): void {
	$block  = new FileBlock();
	$schema = $block->getContentSchema();

	expect( $schema['showDownloadButton'] )->toHaveKey( 'panel' );
	expect( $schema['openInNewTab'] )->toHaveKey( 'panel' );
	expect( $schema['displayPreview'] )->toHaveKey( 'panel' );
	expect( $schema['previewHeight'] )->toHaveKey( 'panel' );
} );

test( 'file block content schema downloadButtonText defaults to Download', function (): void {
	$block    = new FileBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['downloadButtonText'] )->toBe( 'Download' );
} );

test( 'file block content defaults for toggle and range fields', function (): void {
	$block    = new FileBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['showDownloadButton'] )->toBeTrue();
	expect( $defaults['openInNewTab'] )->toBeFalse();
	expect( $defaults['displayPreview'] )->toBeFalse();
	expect( $defaults['previewHeight'] )->toBe( 600 );
} );

test( 'file block style schema is empty', function (): void {
	$block  = new FileBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toBeEmpty();
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

test( 'file block renders pdf preview when displayPreview is true and url is pdf', function (): void {
	$block  = new FileBlock();
	$output = $block->render(
		[ 'url' => 'https://example.com/report.pdf', 'filename' => 'Report.pdf', 'fileSize' => '2 MB' ],
		[ 'showDownloadButton' => true, 'displayPreview' => true, 'previewHeight' => 800 ],
	);

	expect( $output )->toContain( 'application/pdf' );
	expect( $output )->toContain( 'height: 800px' );
	expect( $output )->toContain( 'report.pdf' );
} );

test( 'file block does not render pdf preview for non-pdf files', function (): void {
	$block  = new FileBlock();
	$output = $block->render(
		[ 'url' => 'https://example.com/file.docx', 'filename' => 'File.docx', 'fileSize' => '500 KB' ],
		[ 'showDownloadButton' => true, 'displayPreview' => true ],
	);

	expect( $output )->not->toContain( 'application/pdf' );
} );

test( 'file block renders target blank when openInNewTab is true', function (): void {
	$block  = new FileBlock();
	$output = $block->render(
		[ 'url' => 'doc.pdf', 'filename' => 'Document.pdf', 'fileSize' => '1.5 MB' ],
		[ 'showDownloadButton' => true, 'openInNewTab' => true ],
	);

	expect( $output )->toContain( 'target="_blank"' );
	expect( $output )->toContain( 'rel="noopener noreferrer"' );
} );

test( 'file block does not render target blank when openInNewTab is false', function (): void {
	$block  = new FileBlock();
	$output = $block->render(
		[ 'url' => 'doc.pdf', 'filename' => 'Document.pdf', 'fileSize' => '1.5 MB' ],
		[ 'showDownloadButton' => true, 'openInNewTab' => false ],
	);

	expect( $output )->not->toContain( 'target="_blank"' );
} );

test( 'file block renders custom download button text', function (): void {
	$block  = new FileBlock();
	$output = $block->render(
		[ 'url' => 'doc.pdf', 'filename' => 'Document.pdf', 'fileSize' => '1.5 MB', 'downloadButtonText' => 'Get PDF' ],
		[ 'showDownloadButton' => true ],
	);

	expect( $output )->toContain( 'Get PDF' );
} );

test( 'file block renders filename as link in save view', function (): void {
	$block  = new FileBlock();
	$output = $block->render(
		[ 'url' => 'https://example.com/doc.pdf', 'filename' => 'Document.pdf', 'fileSize' => '1 MB' ],
		[ 'showDownloadButton' => true ],
	);

	expect( $output )->toContain( '<a href="https://example.com/doc.pdf"' );
	expect( $output )->toContain( 'Document.pdf</a>' );
} );

test( 'file block edit view shows placeholder when url is empty', function (): void {
	$block  = new FileBlock();
	$output = $block->renderEditor(
		[ 'url' => '', 'filename' => '' ],
		[ 'showDownloadButton' => true ],
	);

	expect( $output )->toContain( 'placeholder' );
} );
