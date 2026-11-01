<?php

declare( strict_types=1 );

/**
 * Feature tests for `GET /visual-editor/api/taxonomies/{taxonomy}/terms`,
 * the Query Loop term picker's source (#1029).
 *
 * @since 1.20.0
 */

use ArtisanPackUI\VisualEditor\Blocks\Core\CategoriesBlock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Tests\TestUser;

beforeEach( function (): void {
	removeAllFilters( CategoriesBlock::FILTER_TERMS );
} );

afterEach( function (): void {
	removeAllFilters( CategoriesBlock::FILTER_TERMS );
} );

function actingAsTermsUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Terms Tester',
		'email'    => 'terms+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'rejects unauthenticated requests', function (): void {
	$this->getJson( '/visual-editor/api/taxonomies/category/terms' )->assertUnauthorized();
} );

it( 'rejects users without content-editor access', function (): void {
	config()->set( 'artisanpack.visual-editor.content_access.capability', 'edit_content' );
	Gate::define( 'edit_content', fn (): bool => false );

	actingAsTermsUser();

	$this->getJson( '/visual-editor/api/taxonomies/category/terms' )->assertForbidden();
} );

it( 'lists a host taxonomy\'s terms from the terms filter', function (): void {
	actingAsTermsUser();

	addFilter( CategoriesBlock::FILTER_TERMS, function ( $terms, string $taxonomy ) {
		return 'package' === $taxonomy
			? [
				[ 'id' => 3, 'name' => 'Hooks', 'url' => '/blog/package/hooks' ],
				[ 'id' => 4, 'name' => 'Forms', 'url' => '/blog/package/forms' ],
			]
			: $terms;
	}, 10, 2 );

	$this->getJson( '/visual-editor/api/taxonomies/package/terms' )
		->assertOk()
		->assertExactJson( [
			'data' => [
				[ 'id' => 3, 'name' => 'Hooks' ],
				[ 'id' => 4, 'name' => 'Forms' ],
			],
		] );
} );

it( 'keeps host terms with no published posts, so they can be picked ahead of time', function (): void {
	actingAsTermsUser();

	addFilter( CategoriesBlock::FILTER_TERMS, fn (): array => [
		[ 'id' => 3, 'name' => 'Hooks', 'url' => '/blog/package/hooks', 'count' => 0 ],
	] );

	$this->getJson( '/visual-editor/api/taxonomies/package/terms' )
		->assertOk()
		->assertJsonPath( 'data.0.name', 'Hooks' );
} );

it( 'drops host terms with an unusable or repeated id', function (): void {
	actingAsTermsUser();

	addFilter( CategoriesBlock::FILTER_TERMS, fn (): array => [
		[ 'id' => 0, 'name' => 'Zero', 'url' => '/zero' ],
		[ 'id' => 3, 'name' => 'Hooks', 'url' => '/hooks' ],
		[ 'id' => 3, 'name' => 'Hooks again', 'url' => '/hooks-again' ],
	] );

	$this->getJson( '/visual-editor/api/taxonomies/package/terms' )
		->assertOk()
		->assertExactJson( [ 'data' => [ [ 'id' => 3, 'name' => 'Hooks' ] ] ] );
} );

it( 'returns an empty list for a taxonomy nothing answers for', function (): void {
	actingAsTermsUser();

	$this->getJson( '/visual-editor/api/taxonomies/unknown/terms' )
		->assertOk()
		->assertExactJson( [ 'data' => [] ] );
} );

it( 'rejects a slug outside the safe identifier set', function (): void {
	actingAsTermsUser();

	$this->getJson( '/visual-editor/api/taxonomies/bad%20slug/terms' )->assertNotFound();
} );

/**
 * Binds a block whose cms-framework term queries return one empty term
 * each, since Testbench doesn't run the blog migrations.
 */
function bindBuiltInTerms(): void
{
	app()->instance( CategoriesBlock::class, new class extends CategoriesBlock {
		protected function fetchCategories( array $attrs ): Collection
		{
			return $this->withoutEmptyTerms( new Collection( [
				(object) [ 'id' => 1, 'name' => 'News', 'parent_id' => null, 'posts_count' => 0 ],
			] ), $attrs );
		}

		protected function fetchTags( array $attrs ): Collection
		{
			return $this->withoutEmptyTerms( new Collection( [
				(object) [ 'id' => 2, 'name' => 'Laravel', 'parent_id' => null, 'posts_count' => 0 ],
			] ), $attrs );
		}
	} );
}

it( 'lists a built-in taxonomy\'s terms, including empty ones', function ( string $taxonomy, int $id, string $name ): void {
	actingAsTermsUser();
	bindBuiltInTerms();

	$this->getJson( "/visual-editor/api/taxonomies/{$taxonomy}/terms" )
		->assertOk()
		->assertExactJson( [ 'data' => [ [ 'id' => $id, 'name' => $name ] ] ] );
} )->with( [
	'category' => [ 'category', 1, 'News' ],
	'post_tag' => [ 'post_tag', 2, 'Laravel' ],
] );
