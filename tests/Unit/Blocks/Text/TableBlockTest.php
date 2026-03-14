<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Table\TableBlock;

test( 'table block has correct type and category', function (): void {
	$block = new TableBlock();

	expect( $block->getType() )->toBe( 'table' );
	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'table block content schema has header and footer toggle fields', function (): void {
	$block  = new TableBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'hasHeaderRow' );
	expect( $schema['hasHeaderRow']['type'] )->toBe( 'toggle' );

	expect( $schema )->toHaveKey( 'hasHeaderColumn' );
	expect( $schema['hasHeaderColumn']['type'] )->toBe( 'toggle' );

	expect( $schema )->toHaveKey( 'hasFooterRow' );
	expect( $schema['hasFooterRow']['type'] )->toBe( 'toggle' );
} );

test( 'table block content schema has caption field', function (): void {
	$block  = new TableBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'caption' );
	expect( $schema['caption']['type'] )->toBe( 'text' );
	expect( $schema['caption']['default'] )->toBe( '' );
} );

test( 'table block style schema has striped and bordered fields', function (): void {
	$block  = new TableBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'striped' );
	expect( $schema['striped']['type'] )->toBe( 'toggle' );
	expect( $schema['striped']['default'] )->toBeFalse();

	expect( $schema )->toHaveKey( 'bordered' );
	expect( $schema['bordered']['type'] )->toBe( 'toggle' );
	expect( $schema['bordered']['default'] )->toBeTrue();
} );

test( 'table block style schema has fixed layout field', function (): void {
	$block  = new TableBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'fixedLayout' );
	expect( $schema['fixedLayout']['type'] )->toBe( 'toggle' );
	expect( $schema['fixedLayout']['default'] )->toBeFalse();
} );

test( 'table block style schema has color fields', function (): void {
	$block  = new TableBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'headerBackgroundColor' );
	expect( $schema['headerBackgroundColor']['type'] )->toBe( 'color' );

	expect( $schema )->toHaveKey( 'stripeColor' );
	expect( $schema['stripeColor']['type'] )->toBe( 'color' );

	expect( $schema )->toHaveKey( 'borderColor' );
	expect( $schema['borderColor']['type'] )->toBe( 'color' );
} );

test( 'table block defaults to no header row and bordered', function (): void {
	$block           = new TableBlock();
	$contentDefaults = $block->getDefaultContent();
	$styleDefaults   = $block->getDefaultStyles();

	expect( $contentDefaults['hasHeaderRow'] )->toBeFalse();
	expect( $contentDefaults['hasFooterRow'] )->toBeFalse();
	expect( $contentDefaults['hasHeaderColumn'] )->toBeFalse();
	expect( $styleDefaults['bordered'] )->toBeTrue();
	expect( $styleDefaults['striped'] )->toBeFalse();
} );

test( 'table block renders table element with rows', function (): void {
	$block = new TableBlock();
	$rows  = [
		[
			[ 'content' => 'Cell 1', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
			[ 'content' => 'Cell 2', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
		[
			[ 'content' => 'Cell 3', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
			[ 'content' => 'Cell 4', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
	];

	$output = $block->render(
		[ 'rows' => $rows, 'hasHeaderRow' => false, 'hasHeaderColumn' => false, 'hasFooterRow' => false, 'caption' => '' ],
		[ 'striped' => false, 'bordered' => true, 'fixedLayout' => false ],
	);

	expect( $output )->toContain( '<table' );
	expect( $output )->toContain( '<tbody' );
	expect( $output )->toContain( '<td' );
	expect( $output )->toContain( 'Cell 1' );
	expect( $output )->toContain( 'Cell 4' );
	expect( $output )->toContain( 've-block-table' );
	expect( $output )->toContain( 've-table-bordered' );
} );

test( 'table block renders thead when header row is enabled', function (): void {
	$block = new TableBlock();
	$rows  = [
		[
			[ 'content' => 'Header 1', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
			[ 'content' => 'Header 2', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
		[
			[ 'content' => 'Data 1', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
			[ 'content' => 'Data 2', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
	];

	$output = $block->render(
		[ 'rows' => $rows, 'hasHeaderRow' => true, 'hasHeaderColumn' => false, 'hasFooterRow' => false, 'caption' => '' ],
		[ 'striped' => false, 'bordered' => true, 'fixedLayout' => false ],
	);

	expect( $output )->toContain( '<thead' );
	expect( $output )->toContain( '<th' );
	expect( $output )->toContain( 'scope="col"' );
	expect( $output )->toContain( 'Header 1' );
} );

test( 'table block renders tfoot when footer row is enabled', function (): void {
	$block = new TableBlock();
	$rows  = [
		[
			[ 'content' => 'Data 1', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
		[
			[ 'content' => 'Footer 1', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
	];

	$output = $block->render(
		[ 'rows' => $rows, 'hasHeaderRow' => false, 'hasHeaderColumn' => false, 'hasFooterRow' => true, 'caption' => '' ],
		[ 'striped' => false, 'bordered' => true, 'fixedLayout' => false ],
	);

	expect( $output )->toContain( '<tfoot' );
	expect( $output )->toContain( 'Footer 1' );
} );

test( 'table block renders caption when set', function (): void {
	$block = new TableBlock();
	$rows  = [
		[
			[ 'content' => 'Cell', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
	];

	$output = $block->render(
		[ 'rows' => $rows, 'hasHeaderRow' => false, 'hasHeaderColumn' => false, 'hasFooterRow' => false, 'caption' => 'Table Caption' ],
		[ 'striped' => false, 'bordered' => true, 'fixedLayout' => false ],
	);

	expect( $output )->toContain( '<caption' );
	expect( $output )->toContain( 'Table Caption' );
} );

test( 'table block renders with header column scope row', function (): void {
	$block = new TableBlock();
	$rows  = [
		[
			[ 'content' => 'Row Header', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
			[ 'content' => 'Data', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
	];

	$output = $block->render(
		[ 'rows' => $rows, 'hasHeaderRow' => false, 'hasHeaderColumn' => true, 'hasFooterRow' => false, 'caption' => '' ],
		[ 'striped' => false, 'bordered' => true, 'fixedLayout' => false ],
	);

	expect( $output )->toContain( 'scope="row"' );
	expect( $output )->toContain( 'Row Header' );
} );

test( 'table block renders striped class', function (): void {
	$block = new TableBlock();
	$rows  = [
		[
			[ 'content' => 'Cell', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
	];

	$output = $block->render(
		[ 'rows' => $rows, 'hasHeaderRow' => false, 'hasHeaderColumn' => false, 'hasFooterRow' => false, 'caption' => '' ],
		[ 'striped' => true, 'bordered' => true, 'fixedLayout' => false ],
	);

	expect( $output )->toContain( 've-table-striped' );
} );

test( 'table block renders fixed layout class', function (): void {
	$block = new TableBlock();
	$rows  = [
		[
			[ 'content' => 'Cell', 'colSpan' => 1, 'rowSpan' => 1, 'alignment' => 'left' ],
		],
	];

	$output = $block->render(
		[ 'rows' => $rows, 'hasHeaderRow' => false, 'hasHeaderColumn' => false, 'hasFooterRow' => false, 'caption' => '' ],
		[ 'striped' => false, 'bordered' => true, 'fixedLayout' => true ],
	);

	expect( $output )->toContain( 've-table-fixed' );
} );

test( 'table block has keywords', function (): void {
	$block = new TableBlock();

	expect( $block->getKeywords() )->toContain( 'table' );
	expect( $block->getKeywords() )->toContain( 'rows' );
	expect( $block->getKeywords() )->toContain( 'columns' );
} );

test( 'table block has no transforms', function (): void {
	$block = new TableBlock();

	expect( $block->getTransforms() )->toBeEmpty();
} );
