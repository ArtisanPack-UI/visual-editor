<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use ArtisanPackUI\VisualEditorRendererBlade\View\Components\BlocksComponent;
use Tests\Fixtures\FakeQueryResolver;

/**
 * Issue #592 — variable column / row spans on post-variants in a
 * grid-layout Query Loop.
 *
 * End-to-end render coverage: the QueryInliner stamps
 * `_resolvedGridSpan` on the synthetic `core/post-template-item`
 * wrapper, and this Blade partial translates that into a flat
 * `ap-post-span-N-{bp}-{columns,row}` class list scoped via the CSS
 * bundle to grid-layout post-templates only.
 */

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );
} );

function spanRenderablePost( int $id, string $title, array $extras = [] ): object
{
	$post                    = new stdClass();
	$post->id                = $id;
	$post->title             = $title;
	$post->excerpt           = '';
	$post->content           = "<p>{$title}</p>";
	$post->permalink         = "/posts/{$id}";
	$post->author            = null;
	$post->published_at      = null;
	$post->updated_at        = null;
	$post->featured_image_id = null;

	foreach ( $extras as $key => $value ) {
		$post->{$key} = $value;
	}

	return $post;
}

function gridTreeWithVariantSpan(
	int $columnSpan,
	int $rowSpan,
	?array $responsive = null,
	string $layout = 'grid'
): array {
	$variantAttributes = [
		'matcher'        => [ 'kind' => 'position', 'value' => 'first' ],
		'priority'       => 10,
		'gridColumnSpan' => $columnSpan,
		'gridRowSpan'    => $rowSpan,
	];

	if ( null !== $responsive ) {
		$variantAttributes['responsive'] = $responsive;
	}

	return [
		[
			'name'        => 'core/query',
			'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 2 ] ],
			'innerBlocks' => [
				[
					'name'        => 'core/post-template',
					'attributes'  => [ 'layout' => $layout ],
					'innerBlocks' => [
						[
							'name'        => 'core/post-title',
							'attributes'  => [],
							'innerBlocks' => [],
						],
						[
							'name'        => 'artisanpack/post-variant',
							'attributes'  => $variantAttributes,
							'innerBlocks' => [
								[
									'name'        => 'core/post-excerpt',
									'attributes'  => [],
									'innerBlocks' => [],
								],
							],
						],
					],
				],
			],
		],
	];
}

it( 'emits ap-post-span classes on the first iteration <li> when the variant matched in a grid layout', function () {
	$this->fake->setItems( [
		spanRenderablePost( 1, 'Hero' ),
		spanRenderablePost( 2, 'Listed' ),
	] );

	$tree      = gridTreeWithVariantSpan( 2, 2 );
	$component = $this->app->make( BlocksComponent::class, [ 'tree' => $tree ] );
	$html      = $component->render()->with( $component->data() )->render();

	expect( $html )->toContain( 'ap-post-span-2-base-columns' )
		->and( $html )->toContain( 'ap-post-span-2-base-row' )
		// Non-matched item should not pick up span classes from the
		// matched item.
		->and( substr_count( $html, 'ap-post-span-2-base-columns' ) )->toBe( 1 );
} );

it( 'does not emit ap-post-span classes when the post-template layout is not grid', function () {
	$this->fake->setItems( [
		spanRenderablePost( 1, 'Hero' ),
	] );

	$tree      = gridTreeWithVariantSpan( 2, 2, null, 'list' );
	$component = $this->app->make( BlocksComponent::class, [ 'tree' => $tree ] );
	$html      = $component->render()->with( $component->data() )->render();

	expect( $html )->not->toContain( 'ap-post-span-' );
} );

it( 'emits per-breakpoint span classes when the variant carries responsive overrides', function () {
	$this->fake->setItems( [
		spanRenderablePost( 1, 'Hero' ),
	] );

	$tree = gridTreeWithVariantSpan( 2, 1, [
		'gridColumnSpan' => [ 'md' => 3, 'lg' => 4 ],
		'gridRowSpan'    => [ 'md' => 2 ],
	] );

	$component = $this->app->make( BlocksComponent::class, [ 'tree' => $tree ] );
	$html      = $component->render()->with( $component->data() )->render();

	expect( $html )->toContain( 'ap-post-span-2-base-columns' )
		->and( $html )->toContain( 'ap-post-span-3-md-columns' )
		->and( $html )->toContain( 'ap-post-span-4-lg-columns' )
		->and( $html )->toContain( 'ap-post-span-1-base-row' )
		->and( $html )->toContain( 'ap-post-span-2-md-row' );
} );
