<?php

/**
 * All-digit template and template-part slugs (#1074).
 *
 * A slug such as `404` is also a valid-looking row id. The site-editor
 * controllers match it on `(theme, slug)` first and fall back to the
 * primary key, and a request that names a theme never acts on a row in
 * another theme.
 *
 * @since 1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\Template;
use ArtisanPackUI\CMSFramework\Modules\SiteEditor\Models\TemplatePart;
use ArtisanPackUI\CMSFramework\Modules\Themes\Managers\ThemeManager;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\GrantsSiteEditorAccess;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;
use Tests\TestUser;

uses( TestCase::class, WithCmsFramework::class, GrantsSiteEditorAccess::class );

beforeEach( function (): void {
	$this->actingAs( TestUser::create( [
		'name'     => 'Numeric slug tester',
		'email'    => 'numeric-slug+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );

	$this->mock( ThemeManager::class, function ( $mock ): void {
		$mock->shouldReceive( 'getActiveTheme' )->andReturn( [
			'name' => 'Digital Shopfront',
			'slug' => 'digital-shopfront',
		] );
	} );
} );

dataset( 'site-editor entities', [
	'templates' => [
		[
			'model'      => Template::class,
			'route'      => 'templates',
			'filter'     => 'ap.visualEditor.templates',
			'attributes' => [],
		],
	],
	'template parts' => [
		[
			'model'      => TemplatePart::class,
			'route'      => 'template-parts',
			'filter'     => 'ap.visualEditor.templateParts',
			'attributes' => [ 'area' => 'footer' ],
		],
	],
] );

/**
 * Create a site-editor row, optionally with a fixed primary key.
 *
 * @param  array<string, mixed>  $entity  The dataset entry.
 * @param  array<string, mixed>  $overrides  Attribute overrides.
 */
function numericSlugRow( array $entity, array $overrides = [] ): object
{
	$attributes = array_merge( [
		'theme'         => 'digital-shopfront',
		'slug'          => '404',
		'title'         => 'Not Found',
		'status'        => 'publish',
		'is_custom'     => false,
		'block_content' => [],
		'author_id'     => null,
	], $entity['attributes'], $overrides );

	return $entity['model']::query()->forceCreate( $attributes );
}

/**
 * Contribute a theme-file entry with slug `404` and rebuild the resolvers.
 *
 * The entry is appended rather than keyed by its slug: cms-framework's
 * contributor `array_merge`s the map, which renumbers an all-digit key,
 * so the resolver relies on each entry's own `slug`.
 *
 * @param  array<string, mixed>  $entity  The dataset entry.
 */
function registerNumericSlugThemeFile( array $entity ): void
{
	addFilter( $entity['filter'], function ( array $entries ) use ( $entity ): array {
		// A DB override replaces the theme file, as cms-framework's resolver does.
		foreach ( $entries as $entry ) {
			if ( '404' === (string) ( $entry['slug'] ?? '' ) ) {
				return $entries;
			}
		}

		$entries[] = array_merge( [
			'slug'    => '404',
			'theme'   => 'digital-shopfront',
			'title'   => 'Not Found',
			'source'  => 'theme',
			'content' => [ 'raw' => '', 'blocks' => [] ],
		], $entity['attributes'] );

		return $entries;
	} );

	rebuildResolversForNumericSlugTest();
}

function rebuildResolversForNumericSlugTest(): void
{
	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

describe( 'DELETE with an all-digit slug', function (): void {
	it( 'reverts the (theme, slug) override when no row has that id', function ( array $entity ): void {
		$override = numericSlugRow( $entity );

		expect( $override->id )->not->toBe( 404 );

		$this->deleteJson( "/visual-editor/api/{$entity['route']}/404?theme=digital-shopfront" )->assertNoContent();

		expect( $entity['model']::query()->whereKey( $override->id )->exists() )->toBeFalse();
	} )->with( 'site-editor entities' );

	it( 'reverts the (theme, slug) override and leaves the row with that id alone', function ( array $entity ): void {
		$unrelated = numericSlugRow( $entity, [ 'id' => 404, 'theme' => 'other-theme', 'slug' => 'archive' ] );
		$override  = numericSlugRow( $entity );

		$this->deleteJson( "/visual-editor/api/{$entity['route']}/404?theme=digital-shopfront" )->assertNoContent();

		expect( $entity['model']::query()->whereKey( $override->id )->exists() )->toBeFalse()
			->and( $entity['model']::query()->whereKey( $unrelated->id )->exists() )->toBeTrue();
	} )->with( 'site-editor entities' );

	it( 'matches the active theme when ?theme= is omitted', function ( array $entity ): void {
		$unrelated = numericSlugRow( $entity, [ 'id' => 404, 'theme' => 'other-theme', 'slug' => 'archive' ] );
		$override  = numericSlugRow( $entity );

		$this->deleteJson( "/visual-editor/api/{$entity['route']}/404" )->assertNoContent();

		expect( $entity['model']::query()->whereKey( $override->id )->exists() )->toBeFalse()
			->and( $entity['model']::query()->whereKey( $unrelated->id )->exists() )->toBeTrue();
	} )->with( 'site-editor entities' );

	it( 'returns 404 instead of deleting a row by id in a theme the request did not name', function ( array $entity ): void {
		$row = numericSlugRow( $entity, [ 'theme' => 'other-theme', 'slug' => 'archive' ] );

		$this->deleteJson( "/visual-editor/api/{$entity['route']}/{$row->id}?theme=digital-shopfront" )->assertNotFound();

		expect( $entity['model']::query()->whereKey( $row->id )->exists() )->toBeTrue();
	} )->with( 'site-editor entities' );

	it( 'still deletes by id when ?theme= names the row\'s own theme', function ( array $entity ): void {
		$row = numericSlugRow( $entity, [ 'theme' => 'other-theme', 'slug' => 'archive' ] );

		$this->deleteJson( "/visual-editor/api/{$entity['route']}/{$row->id}?theme=other-theme" )->assertNoContent();

		expect( $entity['model']::query()->whereKey( $row->id )->exists() )->toBeFalse();
	} )->with( 'site-editor entities' );

	it( 'returns 404 when neither the slug nor the id matches', function ( array $entity ): void {
		$this->deleteJson( "/visual-editor/api/{$entity['route']}/404?theme=digital-shopfront" )->assertNotFound();
	} )->with( 'site-editor entities' );
} );

describe( 'GET with an all-digit slug', function (): void {
	it( 'resolves the template by slug when the id belongs to another row', function ( array $entity ): void {
		numericSlugRow( $entity, [ 'id' => 404, 'theme' => 'other-theme', 'slug' => 'archive' ] );
		$override = numericSlugRow( $entity );

		rebuildResolversForNumericSlugTest();

		$this->getJson( "/visual-editor/api/{$entity['route']}/404" )
			->assertOk()
			->assertJsonPath( 'slug', '404' )
			->assertJsonPath( 'id', $override->id );
	} )->with( 'site-editor entities' );

	it( 'resolves a theme-file entry with an all-digit slug', function ( array $entity ): void {
		registerNumericSlugThemeFile( $entity );

		$this->getJson( "/visual-editor/api/{$entity['route']}/404" )
			->assertOk()
			->assertJsonPath( 'slug', '404' );
	} )->with( 'site-editor entities' );

	it( 'still resolves a row by id', function ( array $entity ): void {
		$row = numericSlugRow( $entity, [ 'slug' => 'archive' ] );

		rebuildResolversForNumericSlugTest();

		$this->getJson( "/visual-editor/api/{$entity['route']}/{$row->id}" )
			->assertOk()
			->assertJsonPath( 'slug', 'archive' );
	} )->with( 'site-editor entities' );
} );

describe( 'PUT with an all-digit slug', function (): void {
	it( 'updates the (theme, slug) override and leaves the row with that id alone', function ( array $entity ): void {
		$unrelated = numericSlugRow( $entity, [ 'id' => 404, 'theme' => 'other-theme', 'slug' => 'archive', 'title' => 'Archive' ] );
		$override  = numericSlugRow( $entity );

		$this->putJson( "/visual-editor/api/{$entity['route']}/404", array_merge( [
			'theme' => 'digital-shopfront',
			'title' => 'Renamed',
		], $entity['attributes'] ) )->assertOk();

		expect( $override->fresh()->title )->toBe( 'Renamed' )
			->and( $unrelated->fresh()->title )->toBe( 'Archive' );
	} )->with( 'site-editor entities' );

	it( 'upserts a theme-file entry with an all-digit slug', function ( array $entity ): void {
		registerNumericSlugThemeFile( $entity );

		$this->putJson( "/visual-editor/api/{$entity['route']}/404", [
			'content' => [ 'raw' => '', 'blocks' => [ [ 'name' => 'core/heading', 'attributes' => [], 'innerBlocks' => [] ] ] ],
		] )->assertOk();

		expect( $entity['model']::query()->where( 'theme', 'digital-shopfront' )->where( 'slug', '404' )->exists() )->toBeTrue();
	} )->with( 'site-editor entities' );

	it( 'returns 404 instead of upserting the active theme\'s file into another theme', function ( array $entity ): void {
		registerNumericSlugThemeFile( $entity );

		$this->putJson( "/visual-editor/api/{$entity['route']}/404", [
			'theme' => 'other-theme',
			'title' => 'Renamed',
		] )->assertNotFound();

		expect( $entity['model']::query()->where( 'slug', '404' )->exists() )->toBeFalse();
	} )->with( 'site-editor entities' );

	it( 'returns 404 instead of updating a row by id in a theme the body did not name', function ( array $entity ): void {
		$row = numericSlugRow( $entity, [ 'theme' => 'other-theme', 'slug' => 'archive', 'title' => 'Archive' ] );

		$this->putJson( "/visual-editor/api/{$entity['route']}/{$row->id}", [
			'theme' => 'digital-shopfront',
			'title' => 'Renamed',
		] )->assertNotFound();

		expect( $row->fresh()->title )->toBe( 'Archive' );
	} )->with( 'site-editor entities' );

	it( 'still updates by id when the body omits the theme', function ( array $entity ): void {
		$row = numericSlugRow( $entity, [ 'theme' => 'other-theme', 'slug' => 'archive', 'title' => 'Archive' ] );

		$this->putJson( "/visual-editor/api/{$entity['route']}/{$row->id}", [ 'title' => 'Renamed' ] )->assertOk();

		expect( $row->fresh()->title )->toBe( 'Renamed' );
	} )->with( 'site-editor entities' );
} );

describe( 'an all-digit slug that collides with a row id in the same theme', function (): void {
	it( 'reads the row the editor addressed by id', function ( array $entity ): void {
		$row = numericSlugRow( $entity, [ 'id' => 404, 'slug' => 'archive' ] );
		numericSlugRow( $entity );

		rebuildResolversForNumericSlugTest();

		$this->getJson( "/visual-editor/api/{$entity['route']}/404" )
			->assertOk()
			->assertJsonPath( 'slug', 'archive' )
			->assertJsonPath( 'id', $row->id );
	} )->with( 'site-editor entities' );

	it( 'updates the row the editor addressed by id, even with its theme in the body', function ( array $entity ): void {
		$row      = numericSlugRow( $entity, [ 'id' => 404, 'slug' => 'archive', 'title' => 'Archive' ] );
		$override = numericSlugRow( $entity );

		$this->putJson( "/visual-editor/api/{$entity['route']}/404", [
			'theme' => 'digital-shopfront',
			'title' => 'Edited',
		] )->assertOk();

		expect( $row->fresh()->title )->toBe( 'Edited' )
			->and( $override->fresh()->title )->toBe( 'Not Found' );
	} )->with( 'site-editor entities' );

	it( 'updates the row by id when a theme file has the same slug', function ( array $entity ): void {
		$row = numericSlugRow( $entity, [ 'id' => 404, 'slug' => 'archive', 'title' => 'Archive' ] );

		registerNumericSlugThemeFile( $entity );

		$this->putJson( "/visual-editor/api/{$entity['route']}/404", [ 'title' => 'Edited' ] )->assertOk();

		expect( $row->fresh()->title )->toBe( 'Edited' )
			->and( $entity['model']::query()->where( 'slug', '404' )->exists() )->toBeFalse();
	} )->with( 'site-editor entities' );

	it( 'deletes the row by id when ?theme= is omitted', function ( array $entity ): void {
		$row      = numericSlugRow( $entity, [ 'id' => 404, 'slug' => 'archive' ] );
		$override = numericSlugRow( $entity );

		$this->deleteJson( "/visual-editor/api/{$entity['route']}/404" )->assertNoContent();

		expect( $entity['model']::query()->whereKey( $row->id )->exists() )->toBeFalse()
			->and( $entity['model']::query()->whereKey( $override->id )->exists() )->toBeTrue();
	} )->with( 'site-editor entities' );

	it( 'deletes the slug override when ?theme= names the theme', function ( array $entity ): void {
		$row      = numericSlugRow( $entity, [ 'id' => 404, 'slug' => 'archive' ] );
		$override = numericSlugRow( $entity );

		$this->deleteJson( "/visual-editor/api/{$entity['route']}/404?theme=digital-shopfront" )->assertNoContent();

		expect( $entity['model']::query()->whereKey( $override->id )->exists() )->toBeFalse()
			->and( $entity['model']::query()->whereKey( $row->id )->exists() )->toBeTrue();
	} )->with( 'site-editor entities' );
} );

describe( 'an all-digit URL value with no theme anywhere', function (): void {
	beforeEach( function (): void {
		$this->mock( ThemeManager::class, function ( $mock ): void {
			$mock->shouldReceive( 'getActiveTheme' )->andReturn( null );
		} );
	} );

	it( 'updates and deletes by id', function ( array $entity ): void {
		$row = numericSlugRow( $entity, [ 'slug' => 'archive', 'title' => 'Archive' ] );

		$this->putJson( "/visual-editor/api/{$entity['route']}/{$row->id}", [ 'title' => 'Edited' ] )->assertOk();

		expect( $row->fresh()->title )->toBe( 'Edited' );

		$this->deleteJson( "/visual-editor/api/{$entity['route']}/{$row->id}" )->assertNoContent();

		expect( $entity['model']::query()->whereKey( $row->id )->exists() )->toBeFalse();
	} )->with( 'site-editor entities' );
} );
