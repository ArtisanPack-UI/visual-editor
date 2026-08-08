<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedEntity;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplatePartResolver;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\TemplateResolver;
use Tests\TestUser;

/*
 * Template + template-part searches reach cms-framework's
 * `TemplateResolver` / `TemplatePartResolver` (see
 * `src/Http/Controllers/EntitySearchController.php`). No theme is
 * active in this Testbench environment, so the real resolvers return
 * nothing and the template / template-part branches come back empty —
 * the consuming app's test suite covers the populated path. The
 * override tests below bind stand-in resolvers to prove the controller
 * resolves them through the container.
 *
 * Resource-config sources (pages, posts, etc.) are still testable
 * here because they go through Eloquent directly via the
 * `artisanpack.visual-editor.resources` map.
 */

/**
 * Builds the `ResolvedEntity` rows a stand-in resolver hands back.
 * `area` is null for templates and the part's own area for parts,
 * matching what cms-framework's resolvers emit.
 *
 * @param  list<array{slug: string, title: string, area?: string}>  $rows
 * @return list<ResolvedEntity>
 */
function entitySearchStubEntities( array $rows ): array
{
	return array_map( static fn ( array $row ): ResolvedEntity => new ResolvedEntity(
		slug: $row['slug'],
		theme: 'artisanpack-ui',
		source: 'theme',
		raw: '',
		blocks: [],
		title: $row['title'],
		description: '',
		status: 'publish',
		hasThemeFile: true,
		isCustom: false,
		area: $row['area'] ?? null,
		model: null,
	), $rows );
}

function actingAsSearchUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Search Tester',
		'email'    => 'search+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

it( 'returns an empty data array when type is missing', function () {
	actingAsSearchUser();

	$this->getJson( '/visual-editor/api/search' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );

it( 'returns an empty data array for an unknown type slug', function () {
	actingAsSearchUser();

	$this->getJson( '/visual-editor/api/search?type=mystery&q=anything' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );

it( 'returns an empty data array for template type when no theme resolves', function () {
	actingAsSearchUser();

	$this->getJson( '/visual-editor/api/search?type=template' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );

it( 'returns an empty data array for template-part type when no theme resolves', function () {
	actingAsSearchUser();

	$this->getJson( '/visual-editor/api/search?type=template-part' )
		->assertOk()
		->assertJsonPath( 'data', [] );
} );

/*
 * #692 — the controller used to build the resolver FQCN with a leading
 * backslash. Laravel's container doesn't normalise that, so a binding
 * registered under `TemplateResolver::class` was skipped and a fresh
 * resolver was constructed instead. These two tests fail against the
 * old string and pass against the canonical key.
 */

it( 'resolves the template resolver through the container so hosts can override it', function () {
	actingAsSearchUser();

	app()->instance( TemplateResolver::class, new class extends TemplateResolver
	{
		public function __construct()
		{
		}

		public function all(): array
		{
			return entitySearchStubEntities( [
				[ 'slug' => 'single', 'title' => 'Single Post' ],
				[ 'slug' => 'archive', 'title' => 'Archive' ],
			] );
		}
	} );

	$this->getJson( '/visual-editor/api/search?type=template' )
		->assertOk()
		->assertJsonPath( 'data.0', [ 'type' => 'template', 'id' => 'archive', 'title' => 'Archive', 'url' => null ] )
		->assertJsonPath( 'data.1', [ 'type' => 'template', 'id' => 'single', 'title' => 'Single Post', 'url' => null ] );
} );

it( 'resolves the template-part resolver through the container so hosts can override it', function () {
	actingAsSearchUser();

	app()->instance( TemplatePartResolver::class, new class extends TemplatePartResolver
	{
		public function __construct()
		{
		}

		public function all(): array
		{
			return entitySearchStubEntities( [
				[ 'slug' => 'header', 'title' => 'Header', 'area' => 'header' ],
				[ 'slug' => 'footer', 'title' => 'Footer', 'area' => 'footer' ],
			] );
		}
	} );

	$this->getJson( '/visual-editor/api/search?type=template-part&q=head' )
		->assertOk()
		->assertJsonPath( 'data', [
			[ 'type' => 'template-part', 'id' => 'header', 'title' => 'Header', 'url' => null ],
		] );
} );
