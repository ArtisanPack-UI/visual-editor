<?php

declare( strict_types=1 );

use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Enums\ColumnType;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Managers\CustomFieldManager;
use ArtisanPackUI\CMSFramework\Modules\ContentTypes\Models\CustomField;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\CustomFieldSource;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Tests\Concerns\WithCmsFramework;
use Tests\Fixtures\TestBindingsModel;
use Tests\Fixtures\TestGenericContentModel;

uses( WithCmsFramework::class );

function seedSourceCustomField( string $key, array $contentTypes, string $type = 'text', int $order = 0 ): void
{
	CustomField::query()->create( [
		'name'          => ucfirst( $key ),
		'key'           => $key,
		'type'          => $type,
		'column_type'   => ColumnType::String,
		'content_types' => $contentTypes,
		'order'         => $order,
	] );
}

function availableFieldKeys( string $resource, ?string $modelClass = null ): array
{
	return array_column( ( new CustomFieldSource() )->availableFields( $resource, $modelClass ), 'key' );
}

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.resources', [
		'portfolio'  => TestBindingsModel::class,
		'case_study' => TestGenericContentModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();
} );

it( 'matches fields on the resource slug', function (): void {
	seedSourceCustomField( 'client_url', [ 'portfolio' ], 'url' );
	seedSourceCustomField( 'unrelated', [ 'events' ] );

	expect( ( new CustomFieldSource() )->availableFields( 'portfolio', TestBindingsModel::class ) )
		->toBe( [
			[ 'key' => 'client_url', 'label' => 'Client_url', 'type' => 'url' ],
		] );
} );

it( 'lists fields from the slug alone when no model class is known', function (): void {
	seedSourceCustomField( 'client_url', [ 'portfolio' ] );

	expect( availableFieldKeys( 'portfolio' ) )->toBe( [ 'client_url' ] );
} );

it( 'lists fields for a generic model class whose default table matches nothing', function (): void {
	seedSourceCustomField( 'outcome', [ 'case_study' ] );

	expect( availableFieldKeys( 'case_study', TestGenericContentModel::class ) )->toBe( [ 'outcome' ] );
} );

it( 'falls back to the model table name, listing slug matches first', function (): void {
	seedSourceCustomField( 'by_table', [ 'test_block_content_models' ] );
	seedSourceCustomField( 'by_slug', [ 'portfolio' ] );

	expect( availableFieldKeys( 'portfolio', TestBindingsModel::class ) )->toBe( [ 'by_slug', 'by_table' ] );
} );

it( 'de-duplicates a field that matches both the slug and the table', function (): void {
	seedSourceCustomField( 'shared', [ 'portfolio', 'test_block_content_models' ] );

	expect( availableFieldKeys( 'portfolio', TestBindingsModel::class ) )->toBe( [ 'shared' ] );
} );

it( 'includes filter-registered fields after persisted rows', function (): void {
	seedSourceCustomField( 'client_url', [ 'portfolio' ] );

	app( CustomFieldManager::class )->registerField( [
		'key'           => 'is_featured',
		'name'          => 'Featured',
		'type'          => 'boolean',
		'content_types' => [ 'portfolio' ],
	] );

	expect( ( new CustomFieldSource() )->availableFields( 'portfolio' ) )->toBe( [
		[ 'key' => 'client_url', 'label' => 'Client_url', 'type' => 'string' ],
		[ 'key' => 'is_featured', 'label' => 'Featured', 'type' => 'boolean' ],
	] );
} );

it( 'returns an empty catalog with neither a resource nor a model class', function (): void {
	seedSourceCustomField( 'client_url', [ 'portfolio' ] );

	expect( availableFieldKeys( '' ) )->toBe( [] );
} );

it( 'ignores the slug of a resource that is not registered', function (): void {
	seedSourceCustomField( 'secret_note', [ 'internal_memo' ] );

	expect( availableFieldKeys( 'internal_memo' ) )->toBe( [] );
} );
