<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksComponent;
use Tests\Fixtures\FakeQueryResolver;

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );
} );

function blockTreeWithQuery( array $template ): array
{
	return [
		[
			'name'        => 'core/query',
			'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 2 ] ],
			'innerBlocks' => [
				[
					'name'        => 'core/post-template',
					'attributes'  => [],
					'innerBlocks' => $template,
				],
			],
		],
	];
}

function fakeRenderablePost( int $id, string $title, string $excerpt = '' ): object
{
	$post                    = new stdClass();
	$post->id                = $id;
	$post->title             = $title;
	$post->excerpt           = $excerpt;
	$post->content           = '';
	$post->permalink         = "/posts/{$id}";
	$post->author            = null;
	$post->published_at      = null;
	$post->updated_at        = null;
	$post->featured_image_id = null;

	return $post;
}

it( 'renders core/query results through the full Blade pipeline', function () {
	$this->fake->setItems( [
		fakeRenderablePost( 1, 'First post' ),
		fakeRenderablePost( 2, 'Second post' ),
	] );

	$tree = blockTreeWithQuery( [
		[
			'name'        => 'core/post-title',
			'attributes'  => [ 'isLink' => true ],
			'innerBlocks' => [],
		],
	] );

	$component = $this->app->make( BlocksComponent::class, [ 'tree' => $tree ] );

	$html = $component->render()->with( $component->data() )->render();

	expect( $html )->toContain( 'First post' )
		->and( $html )->toContain( 'Second post' )
		->and( $html )->toContain( '/posts/1' )
		->and( $html )->toContain( '/posts/2' )
		->and( substr_count( $html, 'href="/posts/' ) )->toBe( 2 )
		// Semantic markup: a single `<ul class="wp-block-post-template">`
		// wraps N `<li class="wp-block-post-template-item">` items
		// rather than N single-item `<ul>`s.
		->and( substr_count( $html, '<ul' ) )->toBe( 1 )
		->and( substr_count( $html, '<li' ) )->toBe( 2 )
		->and( substr_count( $html, 'wp-block-post-template-item' ) )->toBe( 2 );
} );

it( 'preserves the surrounding tree when the resolver fails', function () {
	$this->app->forgetInstance( QueryResolverContract::class );
	$this->app->offsetUnset( QueryResolverContract::class );

	$tree = [
		[
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'Before query' ],
			'innerBlocks' => [],
		],
		...blockTreeWithQuery( [
			[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
		] ),
		[
			'name'        => 'core/paragraph',
			'attributes'  => [ 'content' => 'After query' ],
			'innerBlocks' => [],
		],
	];

	$component = $this->app->make( BlocksComponent::class, [ 'tree' => $tree ] );
	$html      = $component->render()->with( $component->data() )->render();

	expect( $html )->toContain( 'Before query' )
		->and( $html )->toContain( 'After query' );
} );
