<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock;

test( 'co-located views are registered for blocks', function (): void {
	$block = new HeadingBlock();

	$output = $block->render(
		[ 'text' => 'Override Test', 'level' => 'h2' ],
		[ 'alignment' => 'left' ],
	);

	expect( $output )->toContain( 'Override Test' );
	expect( $output )->toContain( '<h2' );
} );

test( 'published view overrides co-located view when present', function (): void {
	$type         = 'heading';
	$publishedDir = resource_path( 'views/vendor/visual-editor/blocks/' . $type );

	if ( ! is_dir( $publishedDir ) ) {
		mkdir( $publishedDir, 0755, true );
	}

	file_put_contents(
		$publishedDir . '/save.blade.php',
		'<div class="published-override">{{ $content["text"] }}</div>',
	);

	$this->app['view']->prependNamespace(
		'visual-editor-block-' . $type,
		$publishedDir,
	);

	$block  = new HeadingBlock();
	$output = $block->render(
		[ 'text' => 'Published Override', 'level' => 'h2' ],
		[ 'alignment' => 'left' ],
	);

	expect( $output )->toContain( 'published-override' );
	expect( $output )->toContain( 'Published Override' );

	// Clean up
	unlink( $publishedDir . '/save.blade.php' );
	rmdir( $publishedDir );

	$parentDir = dirname( $publishedDir );
	if ( is_dir( $parentDir ) && 2 === count( (array) scandir( $parentDir ) ) ) {
		rmdir( $parentDir );
	}
} );

test( 'co-located views work when no published override exists', function (): void {
	$block  = new HeadingBlock();
	$output = $block->renderEditor(
		[ 'text' => 'Editor Test', 'level' => 'h3' ],
		[ 'alignment' => 'center' ],
	);

	expect( $output )->toContain( 'Editor Test' );
} );
