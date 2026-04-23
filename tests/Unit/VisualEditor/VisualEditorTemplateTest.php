<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Models\VisualEditorTemplate;

it( 'round-trips the content envelope through the model', function () {
	$template = VisualEditorTemplate::create( [
		'slug'    => 'single',
		'title'   => 'Single',
		'content' => [
			'raw'    => '<!-- wp:post-content /-->',
			'blocks' => [
				[
					'name'        => 'core/post-content',
					'attributes'  => [],
					'innerBlocks' => [],
				],
			],
		],
		'theme'   => 'artisanpack-base',
	] );

	$envelope = $template->fresh()->getContentEnvelope();

	expect( $envelope['raw'] )->toBe( '<!-- wp:post-content /-->' );
	expect( $envelope['blocks'] )->toHaveCount( 1 );
	expect( $envelope['blocks'][0]['name'] )->toBe( 'core/post-content' );
} );

it( 'returns an empty envelope when content is null', function () {
	$template = new VisualEditorTemplate( [
		'slug'  => 'empty',
		'theme' => 'artisanpack-base',
	] );

	expect( $template->getContentEnvelope() )->toBe( [
		'raw'    => '',
		'blocks' => [],
	] );

	expect( $template->getBlocks() )->toBe( [] );
	expect( $template->getRawContent() )->toBe( '' );
} );

it( 'setContentEnvelope normalizes missing keys', function () {
	$template = new VisualEditorTemplate( [
		'slug'  => 'foo',
		'theme' => 'artisanpack-base',
	] );

	$template->setContentEnvelope( [ 'blocks' => [
		[ 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ],
	] ] );

	expect( $template->getContentEnvelope() )->toBe( [
		'raw'    => '',
		'blocks' => [
			[ 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ],
		],
	] );
} );

it( 'enforces the (slug, theme) unique constraint', function () {
	VisualEditorTemplate::create( [
		'slug'  => 'index',
		'title' => 'Index',
		'theme' => 'artisanpack-base',
	] );

	expect( function () {
		VisualEditorTemplate::create( [
			'slug'  => 'index',
			'title' => 'Index Clone',
			'theme' => 'artisanpack-base',
		] );
	} )->toThrow( Illuminate\Database\QueryException::class );
} );

it( 'allows the same slug on a different theme', function () {
	VisualEditorTemplate::create( [
		'slug'  => 'index',
		'title' => 'Theme A',
		'theme' => 'theme-a',
	] );

	$themeB = VisualEditorTemplate::create( [
		'slug'  => 'index',
		'title' => 'Theme B',
		'theme' => 'theme-b',
	] );

	expect( $themeB->exists )->toBeTrue();
} );
