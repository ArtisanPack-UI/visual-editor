<?php

/**
 * Front-end binding resolution inside `core/query` loops (#850).
 *
 * Each iteration of a post template resolves its bindings against that
 * iteration's post, while blocks outside the loop keep resolving
 * against the host `:post`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.13.1
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\FakeQueryResolver;

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );
} );

/**
 * @param  array<string, mixed>  $attributes
 */
function queryLoopPost( array $attributes ): Model
{
	$post = new class extends Model {
		protected $table = 'posts';
	};

	$post->setRawAttributes( $attributes, true );

	return $post;
}

/**
 * @return array<string, mixed>
 */
function queryLoopParagraph( string $source, string $key, string $onEmpty = 'fallback', string $content = 'Static' ): array
{
	return [
		'name'        => 'core/paragraph',
		'attributes'  => [
			'content'  => $content,
			'bindings' => [
				'content' => [
					'source'      => $source,
					'args'        => [ 'key' => $key ],
					'onEmpty'     => $onEmpty,
					'placeholder' => 'n/a',
				],
			],
		],
		'innerBlocks' => [],
	];
}

/**
 * @param  array<int, array<string, mixed>>  $template
 *
 * @return array<int, array<string, mixed>>
 */
function queryLoopTree( array $template, array $outside = [] ): array
{
	return array_merge( $outside, [ [
		'name'        => 'core/query',
		'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 10 ] ],
		'innerBlocks' => [ [
			'name'        => 'core/post-template',
			'attributes'  => [],
			'innerBlocks' => $template,
		] ],
	] ] );
}

/**
 * @param  array<int, array<string, mixed>>  $tree
 */
function renderQueryLoopTree( array $tree, mixed $post ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" :post="$post" />', [
		'tree' => $tree,
		'post' => $post,
	] );
}

it( 'resolves post-template bindings against each iteration and outside bindings against the host', function (): void {
	$this->fake->setItems( [
		queryLoopPost( [ 'id' => 1, 'title' => 'First post', 'version' => '1.0' ] ),
		queryLoopPost( [ 'id' => 2, 'title' => 'Second post', 'version' => '2.0' ] ),
	] );

	$host = queryLoopPost( [ 'id' => 99, 'title' => 'Host page', 'version' => '9.9' ] );

	$tree = queryLoopTree(
		[
			queryLoopParagraph( 'post_core', 'title' ),
			queryLoopParagraph( 'custom_field', 'version' ),
		],
		[ queryLoopParagraph( 'post_core', 'title' ) ],
	);

	$html = renderQueryLoopTree( $tree, $host );

	expect( substr_count( $html, '<p class="wp-block-paragraph">Host page</p>' ) )->toBe( 1 )
		->and( $html )->toContain( '<p class="wp-block-paragraph">First post</p>' )
		->and( $html )->toContain( '<p class="wp-block-paragraph">Second post</p>' )
		->and( $html )->toContain( '<p class="wp-block-paragraph">1.0</p>' )
		->and( $html )->toContain( '<p class="wp-block-paragraph">2.0</p>' )
		->and( $html )->not->toContain( '9.9' )
		->and( $html )->not->toContain( 'Static' );
} );

it( 'keeps per-iteration values under the hide and placeholder policies', function ( string $policy ): void {
	$this->fake->setItems( [
		queryLoopPost( [ 'id' => 1, 'version' => '1.0' ] ),
		queryLoopPost( [ 'id' => 2, 'version' => '2.0' ] ),
	] );

	$html = renderQueryLoopTree(
		queryLoopTree( [ queryLoopParagraph( 'custom_field', 'version', $policy ) ] ),
		queryLoopPost( [ 'id' => 99, 'version' => '9.9' ] ),
	);

	expect( $html )->toContain( '<p class="wp-block-paragraph">1.0</p>' )
		->and( $html )->toContain( '<p class="wp-block-paragraph">2.0</p>' )
		->and( $html )->not->toContain( '9.9' )
		->and( $html )->not->toContain( 'n/a' );
} )->with( [ 'hide', 'placeholder' ] );

it( 'applies the onEmpty policy instead of the host value for non-model iteration posts', function (): void {
	$row          = new stdClass();
	$row->id      = 1;
	$row->title   = 'Plain row';
	$row->version = '1.0';

	$this->fake->setItems( [ $row ] );

	$html = renderQueryLoopTree(
		queryLoopTree( [ queryLoopParagraph( 'custom_field', 'version', 'placeholder' ) ] ),
		queryLoopPost( [ 'id' => 99, 'version' => '9.9' ] ),
	);

	expect( $html )->toContain( '<p class="wp-block-paragraph">n/a</p>' )
		->and( $html )->not->toContain( '9.9' );
} );
