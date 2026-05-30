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
	// term + post APIs back through the dynamic-block registry. I5 (#413)
	// forked the entity cluster, so those eleven entries now expose only
	// the artisanpack/* names.
	expect( $editor->getEnabledBlockNames() )->toBe( [
		// I7 (#415): all blocks now use the artisanpack/* namespace.
		// Core blocks are no longer registered.
		'artisanpack/paragraph',
		'artisanpack/heading',
		'artisanpack/list',
		'artisanpack/quote',
		'artisanpack/code',
		'artisanpack/preformatted',
		'artisanpack/pullquote',
		'artisanpack/verse',
		'artisanpack/table',
		'artisanpack/image',
		'artisanpack/gallery',
		'artisanpack/video',
		'artisanpack/audio',
		'artisanpack/file',
		'artisanpack/embed',
		'artisanpack/cover',
		'artisanpack/media-text',
		'artisanpack/template-part',
		'artisanpack/post-title',
		'artisanpack/post-content',
		'artisanpack/post-excerpt',
		'artisanpack/post-date',
		'artisanpack/post-author',
		'artisanpack/post-featured-image',
		'artisanpack/site-title',
		'artisanpack/site-tagline',
		'artisanpack/site-logo',
		'artisanpack/navigation',
		'artisanpack/categories',
		'artisanpack/tag-cloud',
		'artisanpack/archives',
		'artisanpack/query',
		'artisanpack/post-template',
		'artisanpack/callout',
		'artisanpack/group',
		'artisanpack/columns',
		'artisanpack/column',
		'artisanpack/buttons',
		'artisanpack/button',
		'artisanpack/separator',
		'artisanpack/spacer',
		'artisanpack/details',
		'artisanpack/search',
		'artisanpack/latest-posts',
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
