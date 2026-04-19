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
