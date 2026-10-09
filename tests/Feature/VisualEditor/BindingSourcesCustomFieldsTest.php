<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ColumnType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\WithCmsFramework;
use Tests\Fixtures\TestBindingsModel;
use Tests\Fixtures\TestGenericContentModel;
use Tests\TestUser;

uses( WithCmsFramework::class );

/**
 * Register resources through the `ap.visualEditor.resources` filter only
 * (static config empty unless a test sets it) and rebuild the resolver.
 *
 * @param  array<string, class-string>  $resources
 */
function registerFilterResources( array $resources ): void
{
	addFilter( 'ap.visualEditor.resources', fn ( array $existing ): array => array_merge( $existing, $resources ) );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();
}

function createBindingCustomField( string $key, array $contentTypes, string $type = 'text', int $order = 0 ): CustomField
{
	return CustomField::query()->create( [
		'name'          => ucfirst( $key ),
		'key'           => $key,
		'type'          => $type,
		'column_type'   => ColumnType::String,
		'content_types' => $contentTypes,
		'order'         => $order,
	] );
}

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.api.middleware', [ 'auth' ] );
	config()->set( 'artisanpack.visual-editor.resources', [] );

	$this->actingAs( TestUser::create( [
		'name'     => 'Custom Fields Tester',
		'email'    => 'custom-fields+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );
} );

it( 'lists custom fields for a resource registered only through the filter', function () {
	registerFilterResources( [ 'portfolio' => TestBindingsModel::class ] );

	createBindingCustomField( 'client_url', [ 'portfolio' ], 'url' );
	createBindingCustomField( 'unrelated', [ 'events' ] );

	$response = $this->getJson( '/visual-editor/api/bindings/sources/custom_field/fields?resource=portfolio' );

	$response->assertOk()
		->assertJsonPath( 'resource', 'portfolio' )
		->assertJsonPath( 'fields', [
			[ 'key' => 'client_url', 'label' => 'Client_url', 'type' => 'url' ],
		] );
} );

it( 'matches content types by slug when the table name differs', function () {
	// TestBindingsModel's table is `test_block_content_models`, not `portfolio`.
	registerFilterResources( [ 'portfolio' => TestBindingsModel::class ] );

	createBindingCustomField( 'year', [ 'portfolio' ], 'number' );

	$keys = collect(
		$this->getJson( '/visual-editor/api/bindings/sources/custom_field/fields?resource=portfolio' )->json( 'fields' )
	)->pluck( 'key' )->all();

	expect( $keys )->toBe( [ 'year' ] );
} );

it( 'lists fields for a shared generic model class without its own table', function () {
	registerFilterResources( [ 'case_study' => TestGenericContentModel::class ] );

	createBindingCustomField( 'outcome', [ 'case_study' ] );

	$keys = collect(
		$this->getJson( '/visual-editor/api/bindings/sources/custom_field/fields?resource=case_study' )->json( 'fields' )
	)->pluck( 'key' )->all();

	expect( $keys )->toBe( [ 'outcome' ] );
} );

it( 'includes fields contributed through the cms-framework custom-field filter', function () {
	registerFilterResources( [ 'portfolio' => TestBindingsModel::class ] );

	createBindingCustomField( 'client_url', [ 'portfolio' ], 'url' );

	app( CustomFieldManager::class )->registerField( [
		'key'           => 'launch_date',
		'name'          => 'Launch date',
		'type'          => 'date',
		'content_types' => [ 'portfolio' ],
	] );

	$fields = collect(
		$this->getJson( '/visual-editor/api/bindings/sources/custom_field/fields?resource=portfolio' )->json( 'fields' )
	)->keyBy( 'key' );

	expect( $fields->keys()->all() )->toEqualCanonicalizing( [ 'client_url', 'launch_date' ] )
		->and( $fields['launch_date'] )->toBe( [ 'key' => 'launch_date', 'label' => 'Launch date', 'type' => 'date' ] );
} );

it( 'keeps config entries winning over filter entries on key collision', function () {
	config()->set( 'artisanpack.visual-editor.resources', [ 'portfolio' => TestBindingsModel::class ] );
	registerFilterResources( [ 'portfolio' => TestGenericContentModel::class ] );

	// Only reachable through the config model's table-name fallback.
	createBindingCustomField( 'legacy_field', [ 'test_block_content_models' ] );
	createBindingCustomField( 'generic_field', [ 'test_generic_content_models' ] );

	$keys = collect(
		$this->getJson( '/visual-editor/api/bindings/sources/custom_field/fields?resource=portfolio' )->json( 'fields' )
	)->pluck( 'key' )->all();

	expect( $keys )->toBe( [ 'legacy_field' ] );
} );

it( 'returns an empty catalog with a 200 for an unknown resource', function () {
	registerFilterResources( [ 'portfolio' => TestBindingsModel::class ] );

	createBindingCustomField( 'client_url', [ 'portfolio' ] );

	$this->getJson( '/visual-editor/api/bindings/sources/custom_field/fields?resource=imaginary' )
		->assertOk()
		->assertJsonPath( 'fields', [] );
} );

it( 'does not expose fields of a content type that is not a registered resource', function () {
	registerFilterResources( [ 'portfolio' => TestBindingsModel::class ] );

	createBindingCustomField( 'secret_note', [ 'internal_memo' ] );

	$this->getJson( '/visual-editor/api/bindings/sources/custom_field/fields?resource=internal_memo' )
		->assertOk()
		->assertJsonPath( 'fields', [] );
} );

it( 'returns an empty catalog when the resource points at a non-Eloquent class', function () {
	registerFilterResources( [ 'broken' => stdClass::class ] );

	$this->getJson( '/visual-editor/api/bindings/sources/custom_field/fields?resource=broken' )
		->assertOk()
		->assertJsonPath( 'fields', [] );
} );
