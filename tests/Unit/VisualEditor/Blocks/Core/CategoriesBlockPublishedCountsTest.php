<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Core\CategoriesBlock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #960 — the Categories block counts only published posts.
 *
 * Builds the slice of cms-framework's blog schema the block queries
 * (posts, categories, tags and their pivots) so the real
 * `PostCategory` / `PostTag` queries run, and seeds a published post,
 * a draft and a scheduled post.
 */

beforeEach( function (): void {
	Schema::create( 'posts', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'title' );
		$table->string( 'slug' );
		$table->string( 'status' );
		$table->timestamp( 'published_at' )->nullable();
		$table->timestamps();
		$table->softDeletes();
	} );

	foreach ( [ 'post_categories', 'post_tags' ] as $terms ) {
		Schema::create( $terms, function ( Blueprint $table ): void {
			$table->id();
			$table->string( 'name' );
			$table->string( 'slug' );
			$table->unsignedBigInteger( 'parent_id' )->nullable();
			$table->timestamps();
		} );
	}

	Schema::create( 'post_category_pivots', function ( Blueprint $table ): void {
		$table->unsignedBigInteger( 'post_id' );
		$table->unsignedBigInteger( 'post_category_id' );
	} );

	Schema::create( 'post_tag_pivots', function ( Blueprint $table ): void {
		$table->unsignedBigInteger( 'post_id' );
		$table->unsignedBigInteger( 'post_tag_id' );
	} );

	DB::table( 'posts' )->insert( [
		[ 'id' => 1, 'title' => 'Live', 'slug' => 'live', 'status' => 'published', 'published_at' => now()->subDay(), 'deleted_at' => null ],
		[ 'id' => 2, 'title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'published_at' => null, 'deleted_at' => null ],
		[ 'id' => 3, 'title' => 'Scheduled', 'slug' => 'scheduled', 'status' => 'published', 'published_at' => now()->addWeek(), 'deleted_at' => null ],
		[ 'id' => 4, 'title' => 'Trashed', 'slug' => 'trashed', 'status' => 'published', 'published_at' => now()->subDay(), 'deleted_at' => now() ],
	] );

	DB::table( 'post_categories' )->insert( [
		[ 'id' => 1, 'name' => 'Engineering', 'slug' => 'engineering' ],
		[ 'id' => 2, 'name' => 'Drafts only', 'slug' => 'drafts-only' ],
	] );

	DB::table( 'post_category_pivots' )->insert( [
		[ 'post_id' => 1, 'post_category_id' => 1 ],
		[ 'post_id' => 2, 'post_category_id' => 1 ],
		[ 'post_id' => 2, 'post_category_id' => 2 ],
		[ 'post_id' => 3, 'post_category_id' => 2 ],
		[ 'post_id' => 4, 'post_category_id' => 2 ],
	] );

	DB::table( 'post_tags' )->insert( [
		[ 'id' => 1, 'name' => 'Laravel', 'slug' => 'laravel' ],
		[ 'id' => 2, 'name' => 'Unreleased', 'slug' => 'unreleased' ],
	] );

	DB::table( 'post_tag_pivots' )->insert( [
		[ 'post_id' => 1, 'post_tag_id' => 1 ],
		[ 'post_id' => 3, 'post_tag_id' => 1 ],
		[ 'post_id' => 2, 'post_tag_id' => 2 ],
	] );

	app()->instance( 'request', Illuminate\Http\Request::create( '/about' ) );
} );

it( 'counts only published posts and hides categories without one', function (): void {
	$block = new CategoriesBlock();
	$html  = $block->render( $block->validateAttrs( [ 'showPostCounts' => true ] ) );

	expect( $html )->toContain( '<a href="' . url( '/blog/category/engineering' ) . '">Engineering</a> (1)</li>' )
		->and( $html )->not->toContain( 'Drafts only' );
} );

it( 'shows a drafts-only category with a zero count when showEmpty is on', function (): void {
	$block = new CategoriesBlock();
	$html  = $block->render( $block->validateAttrs( [ 'showPostCounts' => true, 'showEmpty' => true ] ) );

	expect( $html )->toContain( 'Drafts only</a> (0)</li>' );
} );

it( 'lists post tags with published counts', function (): void {
	$block = new CategoriesBlock();
	$html  = $block->render( $block->validateAttrs( [ 'taxonomy' => 'post_tag', 'showPostCounts' => true ] ) );

	expect( $html )->toContain( '<a href="' . url( '/blog/tag/laravel' ) . '">Laravel</a> (1)</li>' )
		->and( $html )->not->toContain( 'Unreleased' );
} );

it( 'counts every published post on the All item', function (): void {
	$block = new CategoriesBlock();
	$html  = $block->render( $block->validateAttrs( [ 'showAll' => true, 'showPostCounts' => true ] ) );

	expect( $html )->toContain( '<li class="cat-item cat-item-all"><a href="' . url( '/blog' ) . '">All</a> (1)</li>' );
} );
