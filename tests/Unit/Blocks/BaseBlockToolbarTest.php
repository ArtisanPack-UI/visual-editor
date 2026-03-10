<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock;
use Tests\Unit\Blocks\Stubs\StubBlock;

test( 'heading block returns toolbar controls with alignment', function (): void {
	$block    = new HeadingBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->not->toBeEmpty();
	expect( $controls[0]['group'] )->toBe( 'block' );
	expect( $controls[0]['controls'][0]['type'] )->toBe( 'block-alignment' );
	expect( $controls[0]['controls'][0]['options'] )->toContain( 'left' );
	expect( $controls[0]['controls'][0]['options'] )->toContain( 'center' );
} );

test( 'stub block returns toolbar controls with all alignments', function (): void {
	$block    = new StubBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->not->toBeEmpty();
	expect( $controls[0]['controls'][0]['options'] )->toContain( 'wide' );
	expect( $controls[0]['controls'][0]['options'] )->toContain( 'full' );
} );
