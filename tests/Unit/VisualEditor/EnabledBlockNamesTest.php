<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\VisualEditor;

it( 'returns the frozen V1 block allow-list under the default config', function () {
	$editor = app( VisualEditor::class );

	// Snapshot of the V1 default block set. Update this array only when
	// intentionally changing the block-library audit (see
	// docs/block-library-audit.md) and keep config/visual-editor.php in
	// sync. The deny-list already removes every block that still needs a
	// real loop runtime or term/comment endpoints, so the output should
	// be the allow-list verbatim, in config order. E4 (#381) added the
	// post-, site-, navigation, and template-part blocks on the back of
	// B1's expanded core-data shim and the C-series REST surface. G4b
	// (#401) added the taxonomy/feed widgets that the cms-framework
	// term + post APIs back through the dynamic-block registry.
	expect( $editor->getEnabledBlockNames() )->toBe( [
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/quote',
		'core/code',
		'core/preformatted',
		'core/pullquote',
		'core/verse',
		'core/table',
		'core/image',
		'core/gallery',
		'core/video',
		'core/audio',
		'core/file',
		'core/embed',
		'core/cover',
		'core/media-text',
		'core/columns',
		'core/group',
		'core/row',
		'core/stack',
		'core/buttons',
		'core/separator',
		'core/spacer',
		'core/details',
		'core/search',
		'core/latest-posts',
		'core/template-part',
		'core/post-title',
		'core/post-content',
		'core/post-excerpt',
		'core/post-date',
		'core/post-author',
		'core/post-featured-image',
		'core/site-title',
		'core/site-tagline',
		'core/site-logo',
		'core/navigation',
		'core/categories',
		'core/tag-cloud',
		'core/archives',
		'core/query',
		'core/post-template',
		'artisanpack/callout',
	] );
} );

it( 'removes deny-listed names even when they appear on the allow-list', function () {
	config( [
		'artisanpack.visual-editor.enabled_blocks' => ['core/paragraph', 'core/query', 'core/heading'],
		'artisanpack.visual-editor.disabled_blocks' => ['core/query'],
	] );

	$editor = app( VisualEditor::class );

	expect( $editor->getEnabledBlockNames() )->toBe( ['core/paragraph', 'core/heading'] );
} );

it( 'falls back to the full registry when the allow-list is empty', function () {
	config( [
		'artisanpack.visual-editor.enabled_blocks'  => [],
		'artisanpack.visual-editor.disabled_blocks' => [],
	] );

	$editor = app( VisualEditor::class );

	$editor->registerBlockType( 'artisanpack/custom-a', ['title' => 'A'] );
	$editor->registerBlockType( 'artisanpack/custom-b', ['title' => 'B'] );

	$names = $editor->getEnabledBlockNames();

	expect( $names )->toContain( 'artisanpack/custom-a' )
		->and( $names )->toContain( 'artisanpack/custom-b' );
} );

it( 'de-duplicates repeated block names while preserving order', function () {
	config( [
		'artisanpack.visual-editor.enabled_blocks' => [
			'core/paragraph',
			'core/heading',
			'core/paragraph',
		],
		'artisanpack.visual-editor.disabled_blocks' => [],
	] );

	$editor = app( VisualEditor::class );

	expect( $editor->getEnabledBlockNames() )->toBe( ['core/paragraph', 'core/heading'] );
} );

it( 'ignores non-string entries in the config arrays', function () {
	config( [
		'artisanpack.visual-editor.enabled_blocks' => [
			'core/paragraph',
			42,
			null,
			'  ',
			'core/heading',
		],
		'artisanpack.visual-editor.disabled_blocks' => [false, 'core/paragraph'],
	] );

	$editor = app( VisualEditor::class );

	expect( $editor->getEnabledBlockNames() )->toBe( ['core/heading'] );
} );
