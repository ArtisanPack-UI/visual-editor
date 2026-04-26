<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'renders the x-ve-blocks component from an array tree', function () {
	$tree = [
		[
			'clientId'    => 'p-1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Hello from Blade' ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toBe( '<p class="wp-block-paragraph">Hello from Blade</p>' );
} );

it( 'accepts a JSON string tree', function () {
	$json = json_encode( [
		[
			'clientId'    => 'p-1',
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'JSON string' ],
			'innerBlocks' => [],
		],
	], JSON_THROW_ON_ERROR );

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $json ] );

	expect( $this->normalizeHtml( $this->stripGlobalStyles( $rendered ) ) )
		->toBe( '<p class="wp-block-paragraph">JSON string</p>' );
} );

it( 'renders only the global-styles block when the tree is null', function () {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => null ] );

	expect( trim( $this->stripGlobalStyles( $rendered ) ) )->toBe( '' );
} );

it( 'publishes block views under the visual-editor-blade-views tag', function () {
	$tag = 'visual-editor-blade-views';

	$artisan = $this->artisan( 'vendor:publish', [ '--tag' => $tag, '--force' => true ] );

	$artisan->assertExitCode( 0 );
} );
