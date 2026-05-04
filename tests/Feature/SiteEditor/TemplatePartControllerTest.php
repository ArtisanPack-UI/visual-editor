<?php

/**
 * H6 TemplatePartController integration tests — runs the visual-editor
 * REST surface against a booted cms-framework. Mirrors the structure of
 * {@see TemplateControllerTest} with the additional `area` field.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	$user = TestUser::create( [
		'name'     => 'Site editor tester',
		'email'    => 'site-editor-parts+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	$this->actingAs( $user );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );
} );

function rebuildSiteEditorResolversForPartTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

describe( 'GET /visual-editor/api/template-parts', function (): void {
	it( 'returns an empty list when no parts exist', function (): void {
		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts' )
			->assertOk()
			->assertExactJson( [] );
	} );

	it( 'lists DB-backed parts merged via cms-framework filter contributor', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [ [ 'name' => 'core/site-title', 'attributes' => [], 'innerBlocks' => [] ] ],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts' )
			->assertOk()
			->assertJsonCount( 1 )
			->assertJsonPath( '0.slug', 'header' )
			->assertJsonPath( '0.type', 'wp_template_part' )
			->assertJsonPath( '0.area', 'header' );
	} );
} );

describe( 'GET /visual-editor/api/template-parts/{slug}', function (): void {
	it( 'returns the resolved part for an existing slug', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'footer',
			'title'         => 'Footer',
			'area'          => 'footer',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts/footer' )
			->assertOk()
			->assertJsonPath( 'slug', 'footer' )
			->assertJsonPath( 'area', 'footer' )
			->assertJsonPath( 'type', 'wp_template_part' );
	} );

	it( 'returns 404 when the slug does not resolve', function (): void {
		rebuildSiteEditorResolversForPartTest();

		$this->getJson( '/visual-editor/api/template-parts/missing' )->assertNotFound();
	} );
} );

describe( 'POST /visual-editor/api/template-parts', function (): void {
	it( 'creates a DB-stored part and returns the resolved record', function (): void {
		$this->postJson( '/visual-editor/api/template-parts', [
			'slug'    => 'sidebar',
			'title'   => 'Sidebar',
			'area'    => 'sidebar',
			'theme'   => 'digital-shopfront',
			'content' => [
				'raw'    => '',
				'blocks' => [ [ 'name' => 'core/navigation', 'attributes' => [], 'innerBlocks' => [] ] ],
			],
		] )
			->assertCreated()
			->assertJsonPath( 'slug', 'sidebar' )
			->assertJsonPath( 'area', 'sidebar' )
			->assertJsonPath( 'content.blocks.0.name', 'core/navigation' );

		expect( TemplatePart::query()->where( 'slug', 'sidebar' )->exists() )->toBeTrue();
	} );

	it( 'rejects unknown areas at validation', function (): void {
		$this->postJson( '/visual-editor/api/template-parts', [
			'slug'  => 'rejected',
			'title' => 'Rejected',
			'area'  => 'menu-bar',
			'theme' => 'digital-shopfront',
		] )->assertStatus( 422 )->assertJsonValidationErrors( 'area' );
	} );

	it( 'returns 409 on duplicate (theme, slug)', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->postJson( '/visual-editor/api/template-parts', [
			'slug'  => 'header',
			'title' => 'Duplicate',
			'area'  => 'header',
			'theme' => 'digital-shopfront',
		] )->assertStatus( 409 );
	} );
} );

describe( 'PUT /visual-editor/api/template-parts/{slug}', function (): void {
	it( 'updates an existing part', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->putJson( '/visual-editor/api/template-parts/header', [
			'theme' => 'digital-shopfront',
			'title' => 'Header Renamed',
			'area'  => 'header',
		] )
			->assertOk()
			->assertJsonPath( 'title.raw', 'Header Renamed' );
	} );

	it( 'requires area when upserting a new part', function (): void {
		$this->putJson( '/visual-editor/api/template-parts/new-part', [
			'theme' => 'digital-shopfront',
			'title' => 'No area',
		] )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'area' );
	} );
} );

describe( 'DELETE /visual-editor/api/template-parts/{slug}', function (): void {
	it( 'deletes the DB override scoped to (theme, slug) and returns 204', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/template-parts/header?theme=digital-shopfront' )->assertNoContent();

		expect( TemplatePart::query()->where( 'slug', 'header' )->exists() )->toBeFalse();
	} );

	it( 'leaves overrides in other themes intact when deleting one (theme, slug)', function (): void {
		TemplatePart::create( [
			'theme'         => 'digital-shopfront',
			'slug'          => 'header',
			'title'         => 'Shopfront Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		TemplatePart::create( [
			'theme'         => 'other-theme',
			'slug'          => 'header',
			'title'         => 'Other Header',
			'area'          => 'header',
			'status'        => 'publish',
			'is_custom'     => false,
			'block_content' => [],
			'author_id'     => null,
		] );

		$this->deleteJson( '/visual-editor/api/template-parts/header?theme=digital-shopfront' )->assertNoContent();

		expect( TemplatePart::query()->where( 'theme', 'digital-shopfront' )->where( 'slug', 'header' )->exists() )->toBeFalse()
			->and( TemplatePart::query()->where( 'theme', 'other-theme' )->where( 'slug', 'header' )->exists() )->toBeTrue();
	} );

	it( 'returns 422 when the theme query parameter is missing', function (): void {
		$this->deleteJson( '/visual-editor/api/template-parts/header' )
			->assertStatus( 422 )
			->assertJsonValidationErrors( 'theme' );
	} );

	it( 'returns 404 when no DB override matches the (theme, slug)', function (): void {
		$this->deleteJson( '/visual-editor/api/template-parts/missing?theme=digital-shopfront' )->assertNotFound();
	} );
} );
