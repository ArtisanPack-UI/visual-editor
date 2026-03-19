<?php

/**
 * BlockRenderer Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Rendering
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;
use ArtisanPackUI\VisualEditor\Rendering\BlockRenderer;

test( 'render returns empty string for empty blocks array', function (): void {
	$registry = new BlockRegistry();
	$renderer = new BlockRenderer( $registry );

	expect( $renderer->render( [] ) )->toBe( '' );
} );

test( 'render delegates to block render method', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->andReturn( '<p>Hello</p>' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockBlock );

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->render( [
		[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'Hello' ] ],
	] );

	expect( $html )->toContain( '<p>Hello</p>' );
} );

test( 'render handles multiple blocks', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->twice()
		->andReturnUsing( function ( array $content ): string {
			return '<p>' . ( $content['text'] ?? '' ) . '</p>';
		} );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockBlock );

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->render( [
		[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'First' ] ],
		[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'Second' ] ],
	] );

	expect( $html )->toContain( '<p>First</p>' )
		->and( $html )->toContain( '<p>Second</p>' );
} );

test( 'renderBlock returns empty string for empty type', function (): void {
	$registry = new BlockRegistry();
	$renderer = new BlockRenderer( $registry );

	expect( $renderer->renderBlock( [ 'type' => '' ] ) )->toBe( '' );
} );

test( 'renderBlock returns empty string for missing type', function (): void {
	$registry = new BlockRegistry();
	$renderer = new BlockRenderer( $registry );

	expect( $renderer->renderBlock( [] ) )->toBe( '' );
} );

test( 'renderBlock returns empty string for unregistered block type', function (): void {
	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'nonexistent' )
		->andReturn( null );

	$renderer = new BlockRenderer( $mockRegistry );

	expect( $renderer->renderBlock( [ 'type' => 'nonexistent' ] ) )->toBe( '' );
} );

test( 'render handles nested inner blocks recursively', function (): void {
	$mockColumn = Mockery::mock( BlockInterface::class );
	$mockColumn->shouldReceive( 'render' )
		->once()
		->andReturnUsing( function ( array $content, array $styles, array $context, array $innerBlocks ): string {
			return '<div class="column">' . implode( '', $innerBlocks ) . '</div>';
		} );

	$mockParagraph = Mockery::mock( BlockInterface::class );
	$mockParagraph->shouldReceive( 'render' )
		->once()
		->andReturn( '<p>Nested</p>' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'column' )
		->andReturn( $mockColumn );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockParagraph );

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->render( [
		[
			'type'        => 'column',
			'attributes'  => [],
			'innerBlocks' => [
				[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'Nested' ] ],
			],
		],
	] );

	expect( $html )->toContain( '<div class="column"><p>Nested</p></div>' );
} );

test( 'render passes styles to block render method', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->withArgs( function ( array $content, array $styles ) {
			return isset( $styles['alignment'] ) && 'center' === $styles['alignment']
				&& isset( $styles['textColor'] ) && '#ff0000' === $styles['textColor'];
		} )
		->andReturn( '<p style="text-align: center; color: #ff0000;">Styled</p>' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockBlock );

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->render( [
		[
			'type'       => 'paragraph',
			'attributes' => [ 'text' => 'Styled' ],
			'styles'     => [ 'alignment' => 'center', 'textColor' => '#ff0000' ],
		],
	] );

	expect( $html )->toContain( 'Styled' );
} );

test( 'render skips blocks with empty type among valid blocks', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->andReturn( '<p>Valid</p>' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockBlock );

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->render( [
		[ 'type' => '', 'attributes' => [] ],
		[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'Valid' ] ],
	] );

	expect( $html )->toBe( '<p>Valid</p>' );
} );

test( 'render applies ap.visualEditor.renderBlock filter', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->andReturn( '<p>Original</p>' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockBlock );

	addFilter( 'ap.visualEditor.renderBlock', function ( string $html, array $blockData, $block ): string {
		return str_replace( '<p>', '<p class="filtered">', $html );
	} );

	try {
		$renderer = new BlockRenderer( $mockRegistry );

		$html = $renderer->render( [
			[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'Original' ] ],
		] );

		expect( $html )->toContain( '<p class="filtered">Original</p>' );
	} finally {
		removeAllFilters( 'ap.visualEditor.renderBlock' );
	}
} );

test( 'render applies ap.visualEditor.renderedContent filter', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->andReturn( '<p>Hello</p>' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockBlock );

	addFilter( 'ap.visualEditor.renderedContent', function ( string $html, array $blocks ): string {
		return '<div class="post-content">' . $html . '</div>';
	} );

	try {
		$renderer = new BlockRenderer( $mockRegistry );

		$html = $renderer->render( [
			[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'Hello' ] ],
		] );

		expect( $html )->toBe( '<div class="post-content"><p>Hello</p></div>' );
	} finally {
		removeAllFilters( 'ap.visualEditor.renderedContent' );
	}
} );

test( 'render handles deeply nested inner blocks', function (): void {
	$mockGroup = Mockery::mock( BlockInterface::class );
	$mockGroup->shouldReceive( 'render' )
		->andReturnUsing( function ( array $content, array $styles, array $context, array $innerBlocks ): string {
			return '<div class="group">' . implode( '', $innerBlocks ) . '</div>';
		} );

	$mockParagraph = Mockery::mock( BlockInterface::class );
	$mockParagraph->shouldReceive( 'render' )
		->once()
		->andReturn( '<p>Deep</p>' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'group' )
		->andReturn( $mockGroup );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockParagraph );

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->render( [
		[
			'type'        => 'group',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'type'        => 'group',
					'attributes'  => [],
					'innerBlocks' => [
						[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'Deep' ] ],
					],
				],
			],
		],
	] );

	expect( $html )->toBe( '<div class="group"><div class="group"><p>Deep</p></div></div>' );
} );

test( 'render defaults attributes to empty array when missing', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->andReturn( '<hr />' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'divider' )
		->andReturn( $mockBlock );

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->render( [
		[ 'type' => 'divider' ],
	] );

	expect( $html )->toBe( '<hr />' );
} );

test( 'renderBlock returns empty string and logs warning when block render throws', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->andThrow( new RuntimeException( 'kses failed' ) );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'custom-html' )
		->andReturn( $mockBlock );

	Illuminate\Support\Facades\Log::shouldReceive( 'warning' )
		->once()
		->withArgs( function ( string $message, array $context ): bool {
			return str_contains( $message, 'custom-html' )
				&& 'kses failed' === $context['error'];
		} );

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->renderBlock( [ 'type' => 'custom-html', 'attributes' => [ 'content' => '<p>test</p>' ] ] );

	expect( $html )->toBe( '' );
} );

test( 'render continues rendering remaining blocks when one throws', function (): void {
	$mockBrokenBlock = Mockery::mock( BlockInterface::class );
	$mockBrokenBlock->shouldReceive( 'render' )
		->once()
		->andThrow( new RuntimeException( 'render error' ) );

	$mockGoodBlock = Mockery::mock( BlockInterface::class );
	$mockGoodBlock->shouldReceive( 'render' )
		->once()
		->andReturn( '<p>OK</p>' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'custom-html' )
		->andReturn( $mockBrokenBlock );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockGoodBlock );

	Illuminate\Support\Facades\Log::shouldReceive( 'warning' )->once();

	$renderer = new BlockRenderer( $mockRegistry );

	$html = $renderer->render( [
		[ 'type' => 'custom-html', 'attributes' => [ 'content' => '<script>bad</script>' ] ],
		[ 'type' => 'paragraph', 'attributes' => [ 'text' => 'OK' ] ],
	] );

	expect( $html )->toBe( '<p>OK</p>' );
} );

test( 'renderBlock passes classPrefix in context to block render', function (): void {
	$receivedContext = null;

	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->andReturnUsing( function ( array $content, array $styles, array $context ) use ( &$receivedContext ): string {
			$receivedContext = $context;

			return '<p>test</p>';
		} );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockBlock );

	$renderer = new BlockRenderer( $mockRegistry, 've-block-' );

	$renderer->renderBlock( [ 'type' => 'paragraph', 'attributes' => [] ] );

	expect( $receivedContext )->toHaveKey( 'classPrefix' )
		->and( $receivedContext['classPrefix'] )->toBe( 've-block-' );
} );

test( 'renderBlock uses custom classPrefix from constructor', function (): void {
	$receivedContext = null;

	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldReceive( 'render' )
		->once()
		->andReturnUsing( function ( array $content, array $styles, array $context ) use ( &$receivedContext ): string {
			$receivedContext = $context;

			return '<p>test</p>';
		} );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'paragraph' )
		->andReturn( $mockBlock );

	$renderer = new BlockRenderer( $mockRegistry, 'wp-block-' );

	$renderer->renderBlock( [ 'type' => 'paragraph', 'attributes' => [] ] );

	expect( $receivedContext['classPrefix'] )->toBe( 'wp-block-' );
} );

test( 'renderBlock returns empty string when max depth is exceeded', function (): void {
	$mockBlock = Mockery::mock( BlockInterface::class );
	$mockBlock->shouldNotReceive( 'render' );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'group' )
		->andReturn( $mockBlock );

	Illuminate\Support\Facades\Log::shouldReceive( 'warning' )
		->once()
		->withArgs( function ( string $message ): bool {
			return str_contains( $message, 'max depth' )
				&& str_contains( $message, 'group' );
		} );

	$renderer = new BlockRenderer( $mockRegistry, 've-block-', 3 );

	$html = $renderer->renderBlock( [ 'type' => 'group', 'attributes' => [] ], 3 );

	expect( $html )->toBe( '' );
} );

test( 'render stops recursion at configured max depth', function (): void {
	$mockGroup = Mockery::mock( BlockInterface::class );
	$mockGroup->shouldReceive( 'render' )
		->once()
		->andReturnUsing( function ( array $content, array $styles, array $context, array $innerBlocks ): string {
			return '<div>' . implode( '', $innerBlocks ) . '</div>';
		} );

	$mockRegistry = Mockery::mock( BlockRegistry::class );
	$mockRegistry->shouldReceive( 'get' )
		->with( 'group' )
		->andReturn( $mockGroup );

	Illuminate\Support\Facades\Log::shouldReceive( 'warning' )
		->once()
		->withArgs( function ( string $message ): bool {
			return str_contains( $message, 'max depth' );
		} );

	$renderer = new BlockRenderer( $mockRegistry, 've-block-', 1 );

	$html = $renderer->render( [
		[
			'type'        => 'group',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'type'        => 'group',
					'attributes'  => [],
					'innerBlocks' => [],
				],
			],
		],
	] );

	expect( $html )->toBe( '<div></div>' );
} );

test( 'BlockRenderer is resolvable from the container', function (): void {
	$renderer = app( BlockRenderer::class );

	expect( $renderer )->toBeInstanceOf( BlockRenderer::class );
} );
