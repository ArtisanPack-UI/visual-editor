<?php

/**
 * Query Loop post-type picker mount wiring (#847).
 *
 * Both editor mounts stamp the `ap.visualEditor.query.postTypes`-filtered
 * option list as `data-query-post-types`, so the Query Loop inspector can
 * offer host content types in post content and in site-editor templates.
 *
 * @since 1.14.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\QueryPostTypeRegistry;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\TestBlockContentModel;

beforeEach( function (): void {
	removeAllFilters( QueryPostTypeRegistry::FILTER );

	config()->set( 'artisanpack.visual-editor.resources', [
		'posts' => TestBlockContentModel::class,
	] );

	addFilter( QueryPostTypeRegistry::FILTER, function ( array $options ): array {
		$options[] = [ 'value' => 'case-study', 'label' => 'Case Studies' ];

		return $options;
	} );
} );

afterEach( function (): void {
	removeAllFilters( QueryPostTypeRegistry::FILTER );
	removeAllFilters( 'ap.visualEditor.editorConfig' );
} );

it( 'stamps the filtered post types onto the post-editor mount', function (): void {
	$model = TestBlockContentModel::create( [
		'title'   => 'Query',
		'status'  => 'published',
		'content' => [],
	] );

	$html = Blade::render(
		'<x-visual-editor :model="$model" />',
		[ 'model' => $model ],
	);

	$expected = e( json_encode( QueryPostTypeRegistry::resolve(), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) );

	expect( $html )->toContain( 'data-query-post-types="' . $expected . '"' )
		->and( $html )->toContain( 'case-study' );
} );

it( 'stamps the filtered post types onto the site-editor mount', function (): void {
	$this->app->bind( SiteEditorAccessGate::class, function () {
		return new class implements SiteEditorAccessGate {
			public function check( Request $request ): ?Response
			{
				return null;
			}
		};
	} );

	$this->withoutVite();

	$html = $this->get( '/visual-editor/site' )->assertOk()->getContent();

	expect( $html )->toContain( 'data-query-post-types=' )
		->and( $html )->toContain( 'case-study' )
		->and( $html )->toContain( 'Case Studies' );
} );

it( 'normalises a queryPostTypes override from the editorConfig filter', function (): void {
	$model = TestBlockContentModel::create( [
		'title'   => 'Query',
		'status'  => 'published',
		'content' => [],
	] );

	addFilter( 'ap.visualEditor.editorConfig', function ( array $config ): array {
		$config['queryPostTypes'] = [
			[ 'value' => 'event', 'label' => 'Events' ],
			[ 'value' => 'bad value', 'label' => 'Bad' ],
		];

		return $config;
	} );

	$html = Blade::render(
		'<x-visual-editor :model="$model" />',
		[ 'model' => $model ],
	);

	$expected = e( json_encode( [ [ 'value' => 'event', 'label' => 'Events' ] ], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS ) );

	expect( $html )->toContain( 'data-query-post-types="' . $expected . '"' );
} );

it( 'keeps the resolved list when the editorConfig override has no valid entries', function (): void {
	$model = TestBlockContentModel::create( [
		'title'   => 'Query',
		'status'  => 'published',
		'content' => [],
	] );

	addFilter( 'ap.visualEditor.editorConfig', function ( array $config ): array {
		$config['queryPostTypes'] = [ 'garbage' ];

		return $config;
	} );

	$html = Blade::render(
		'<x-visual-editor :model="$model" />',
		[ 'model' => $model ],
	);

	expect( $html )->toContain( 'case-study' );
} );
