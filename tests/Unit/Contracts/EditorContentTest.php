<?php

/**
 * EditorContent Interface Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Contracts
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Contracts\EditorContent;
use Tests\Unit\Concerns\Stubs\TestPost;

it( 'TestPost implements EditorContent interface', function (): void {
	$post = new TestPost();

	expect( $post )->toBeInstanceOf( EditorContent::class );
} );

it( 'EditorContent interface defines required methods', function (): void {
	$reflection = new ReflectionClass( EditorContent::class );
	$methods    = array_map(
		fn ( ReflectionMethod $m ) => $m->getName(),
		$reflection->getMethods(),
	);

	expect( $methods )->toContain( 'getBlocks' )
		->toContain( 'setBlocks' )
		->toContain( 'saveFromEditor' )
		->toContain( 'renderBlocks' );
} );
