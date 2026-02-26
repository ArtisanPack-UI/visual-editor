<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock;
use ArtisanPackUI\VisualEditor\Inspector\SupportsPanelRegistry;
use Tests\Unit\Blocks\Stubs\StubBlock;

test( 'returns color panel for blocks with color support', function (): void {
	$registry = new SupportsPanelRegistry();
	$block    = new HeadingBlock();

	$panels = $registry->getPanelsForBlock( $block );

	$colorPanel = collect( $panels )->firstWhere( 'key', 'color' );

	expect( $colorPanel )->not->toBeNull();
	expect( $colorPanel['controls'] )->toHaveCount( 2 );
} );

test( 'returns typography panel for blocks with typography support', function (): void {
	$registry = new SupportsPanelRegistry();
	$block    = new HeadingBlock();

	$panels = $registry->getPanelsForBlock( $block );

	$typographyPanel = collect( $panels )->firstWhere( 'key', 'typography' );

	expect( $typographyPanel )->not->toBeNull();
	expect( $typographyPanel['controls'] )->not->toBeEmpty();
} );

test( 'does not return spacing panel for blocks without spacing support', function (): void {
	$registry = new SupportsPanelRegistry();
	$block    = new HeadingBlock();

	$panels = $registry->getPanelsForBlock( $block );

	$spacingPanel = collect( $panels )->firstWhere( 'key', 'spacing' );

	expect( $spacingPanel )->toBeNull();
} );

test( 'does not return shadow panel when not supported', function (): void {
	$registry = new SupportsPanelRegistry();
	$block    = new HeadingBlock();

	$panels = $registry->getPanelsForBlock( $block );

	$shadowPanel = collect( $panels )->firstWhere( 'key', 'shadow' );

	expect( $shadowPanel )->toBeNull();
} );

test( 'returns panels in correct order', function (): void {
	$registry = new SupportsPanelRegistry();
	$block    = new HeadingBlock();

	$panels = $registry->getPanelsForBlock( $block );
	$keys   = array_column( $panels, 'key' );

	expect( $keys )->toBe( [ 'color', 'typography' ] );
} );

test( 'stub block returns color panel with text only', function (): void {
	$registry = new SupportsPanelRegistry();
	$block    = new StubBlock();

	$panels = $registry->getPanelsForBlock( $block );

	$colorPanel = collect( $panels )->firstWhere( 'key', 'color' );

	expect( $colorPanel )->not->toBeNull();
	expect( $colorPanel['controls'] )->toHaveCount( 1 );
	expect( $colorPanel['controls'][0]['field'] )->toBe( 'textColor' );
} );
