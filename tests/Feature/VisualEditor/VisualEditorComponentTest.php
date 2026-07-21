<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestBlockContentPageModel;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.resources', [
		'posts' => TestBlockContentModel::class,
		'pages' => TestBlockContentPageModel::class,
	] );
} );

it( 'renders the data attributes the React bootstrap needs', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Rendered',
		'status'  => 'published',
		'content' => [],
	] );

	$html = Blade::render(
		'<x-visual-editor :model="$model" />',
		[ 'model' => $model ]
	);

	expect( $html )->toContain( 'data-ap-visual-editor' )
		->and( $html )->toContain( 'data-resource="posts"' )
		->and( $html )->toContain( 'data-id="' . $model->id . '"' )
		->and( $html )->toContain( 'data-api-base="/visual-editor/api"' );
} );

it( 'infers the resource slug from the page fixture', function () {
	$page = TestBlockContentPageModel::create( [
		'title' => 'A page',
		'body'  => [],
	] );

	$html = Blade::render(
		'<x-visual-editor :model="$model" />',
		[ 'model' => $page ]
	);

	expect( $html )->toContain( 'data-resource="pages"' );
} );

it( 'throws when the model is not registered as a resource', function () {
	config()->set( 'artisanpack.visual-editor.resources', [] );

	$model = TestBlockContentModel::create( [
		'title'   => 'Orphan',
		'status'  => 'published',
		'content' => [],
	] );

	try {
		Blade::render( '<x-visual-editor :model="$model" />', [ 'model' => $model ] );
		test()->fail( 'Expected a RuntimeException to be thrown.' );
	} catch ( \Throwable $e ) {
		$root = $e;

		while ( $root->getPrevious() && ! $root instanceof RuntimeException ) {
			$root = $root->getPrevious();
		}

		expect( $root )->toBeInstanceOf( RuntimeException::class );
	}
} );

it( 'honors an explicit resource override prop', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Override',
		'status'  => 'published',
		'content' => [],
	] );

	$html = Blade::render(
		'<x-visual-editor :model="$model" resource="custom-slug" />',
		[ 'model' => $model ]
	);

	expect( $html )->toContain( 'data-resource="custom-slug"' );
} );

it( 'throws when mounted against an unsaved model', function () {
	$model = new TestBlockContentModel( [
		'title'   => 'Unsaved',
		'status'  => 'published',
		'content' => [],
	] );

	try {
		Blade::render( '<x-visual-editor :model="$model" />', [ 'model' => $model ] );
		test()->fail( 'Expected a RuntimeException to be thrown.' );
	} catch ( \Throwable $e ) {
		$root = $e;

		while ( $root->getPrevious() && ! $root instanceof RuntimeException ) {
			$root = $root->getPrevious();
		}

		expect( $root )->toBeInstanceOf( RuntimeException::class )
			->and( $root->getMessage() )->toContain( 'unsaved' );
	}
} );

it( 'honors an explicit api-base override prop', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Api override',
		'status'  => 'published',
		'content' => [],
	] );

	$html = Blade::render(
		'<x-visual-editor :model="$model" api-base="/custom/api" />',
		[ 'model' => $model ]
	);

	expect( $html )->toContain( 'data-api-base="/custom/api"' );
} );

it( 'emits document-panel data attributes for the inspector sidebar', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'With sidebar data',
		'status'  => 'published',
		'content' => [],
	] );

	$html = Blade::render(
		'<x-visual-editor :model="$model" :initial-title="$title" :initial-slug="$slug" :initial-status="$status" :initial-excerpt="$excerpt" :initial-author-id="$authorId" :initial-comments-open="$commentsOpen" :initial-featured-image="$featuredImage" :author-options="$authorOptions" :supports="$supports" />',
		[
			'model'           => $model,
			'title'           => 'Rendered title',
			'slug'            => 'rendered-slug',
			'status'          => 'draft',
			'excerpt'         => 'A short summary.',
			'authorId'        => 42,
			'commentsOpen'    => true,
			'featuredImage'   => [ 'id' => 7, 'url' => 'https://example.test/x.jpg' ],
			'authorOptions'   => [ [ 'value' => 42, 'label' => 'Alice' ] ],
			'supports'        => [ 'comments' => true, 'excerpt' => true ],
		]
	);

	expect( $html )->toContain( 'data-title="Rendered title"' )
		->and( $html )->toContain( 'data-slug="rendered-slug"' )
		->and( $html )->toContain( 'data-status="draft"' )
		->and( $html )->toContain( 'data-excerpt="A short summary."' )
		->and( $html )->toContain( 'data-author-id="42"' )
		->and( $html )->toContain( 'data-comments-open="true"' )
		->and( $html )->toContain( 'data-featured-image=' )
		->and( $html )->toContain( 'data-author-options=' )
		->and( $html )->toContain( 'data-supports=' );
} );

it( 'omits optional data attributes when the matching props are not supplied', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Minimal',
		'status'  => 'published',
		'content' => [],
	] );

	$html = Blade::render(
		'<x-visual-editor :model="$model" />',
		[ 'model' => $model ]
	);

	expect( $html )->not->toContain( 'data-excerpt=' )
		->and( $html )->not->toContain( 'data-featured-image=' )
		->and( $html )->not->toContain( 'data-author-id=' )
		->and( $html )->not->toContain( 'data-comments-open=' )
		->and( $html )->not->toContain( 'data-author-options=' )
		->and( $html )->not->toContain( 'data-supports=' );
} );

describe( 'ap.visualEditor.editorConfig filter', function (): void {
	afterEach( function (): void {
		removeAllFilters( 'ap.visualEditor.editorConfig' );
	} );

	it( 'lets a callback rewrite props before render', function (): void {
		$model = TestBlockContentModel::create( [
			'title'   => 'Base',
			'status'  => 'published',
			'content' => [],
		] );

		addFilter( 'ap.visualEditor.editorConfig', function ( array $config, string $screen ): array {
			$config['apiBase']      = '/custom/api';
			$config['initialTitle'] = 'Filtered';

			return $config;
		}, 10, 2 );

		$html = Blade::render(
			'<x-visual-editor :model="$model" :initial-title="\'Base\'" />',
			[ 'model' => $model ]
		);

		expect( $html )->toContain( 'data-api-base="/custom/api"' )
			->and( $html )->toContain( 'data-title="Filtered"' );
	} );

	it( 'passes the "post" screen identifier so hosts can gate per surface', function (): void {
		$model = TestBlockContentModel::create( [
			'title'   => 'Screen',
			'status'  => 'published',
			'content' => [],
		] );

		$screensSeen = [];
		addFilter( 'ap.visualEditor.editorConfig', function ( array $config, string $screen ) use ( &$screensSeen ): array {
			$screensSeen[] = $screen;

			return $config;
		}, 10, 2 );

		Blade::render(
			'<x-visual-editor :model="$model" />',
			[ 'model' => $model ]
		);

		expect( $screensSeen )->toBe( [ 'post' ] );
	} );

	it( 'ignores a non-array filter return so props survive intact', function (): void {
		$model = TestBlockContentModel::create( [
			'title'   => 'Intact',
			'status'  => 'published',
			'content' => [],
		] );

		addFilter( 'ap.visualEditor.editorConfig', function ( array $config, string $screen ): ?bool {
			return null;
		}, 10, 2 );

		$html = Blade::render(
			'<x-visual-editor :model="$model" />',
			[ 'model' => $model ]
		);

		expect( $html )->toContain( 'data-resource="posts"' )
			->and( $html )->toContain( 'data-api-base="/visual-editor/api"' );
	} );

	it( 'preserves props whose filtered value is a bad shape for the coercer', function (): void {
		$model = TestBlockContentModel::create( [
			'title'   => 'Coerce',
			'status'  => 'published',
			'content' => [],
		] );

		addFilter( 'ap.visualEditor.editorConfig', function ( array $config, string $screen ): array {
			// Malformed values across each coercer type; every one should
			// be rejected in favour of the current prop value.
			$config['apiBase']             = 42;              // string coercer
			$config['initialTitle']        = [ 'not string' ]; // nullableString
			$config['initialCommentsOpen'] = 'yes';           // nullableBool
			$config['authorOptions']       = 'oops';          // nullableArray
			$config['initialAuthorId']     = true;            // nullableIntOrString — must not TypeError

			return $config;
		}, 10, 2 );

		$html = Blade::render(
			'<x-visual-editor :model="$model" :initial-title="\'Kept\'" />',
			[ 'model' => $model ]
		);

		expect( $html )->toContain( 'data-api-base="/visual-editor/api"' )
			->and( $html )->toContain( 'data-title="Kept"' )
			// initialCommentsOpen was null (unset), so no data-comments-open should be emitted.
			->and( $html )->not->toContain( 'data-comments-open=' )
			->and( $html )->not->toContain( 'data-author-options=' );
	} );

	it( 'leaves omitted keys untouched when the filter returns a partial config', function (): void {
		$model = TestBlockContentModel::create( [
			'title'   => 'Partial',
			'status'  => 'published',
			'content' => [],
		] );

		addFilter( 'ap.visualEditor.editorConfig', function ( array $config, string $screen ): array {
			return [ 'apiBase' => '/only-this' ];
		}, 10, 2 );

		$html = Blade::render(
			'<x-visual-editor :model="$model" :initial-title="\'Untouched\'" />',
			[ 'model' => $model ]
		);

		expect( $html )->toContain( 'data-api-base="/only-this"' )
			->and( $html )->toContain( 'data-title="Untouched"' )
			->and( $html )->toContain( 'data-resource="posts"' );
	} );
} );
