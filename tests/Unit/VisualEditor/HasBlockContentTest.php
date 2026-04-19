<?php

declare( strict_types=1 );

use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestBlockContentPageModel;

it( 'defaults the block content column to "content"', function () {
	$model = new TestBlockContentModel();

	expect( $model->getBlockContentColumn() )->toBe( 'content' );
} );

it( 'honors a custom block content column override', function () {
	$model = new TestBlockContentPageModel();

	expect( $model->getBlockContentColumn() )->toBe( 'body' );
} );

it( 'returns null scope when none is configured', function () {
	$model = new TestBlockContentPageModel();

	expect( $model->getBlockContentScope() )->toBeNull();
} );

it( 'exposes the configured block content scope', function () {
	$model = new TestBlockContentModel();

	expect( $model->getBlockContentScope() )->toBe( 'published' );
} );

it( 'reads and writes block content through the trait', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Draft Post',
		'status'  => 'published',
		'content' => [
			[ 'clientId' => 'a', 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ],
		],
	] );

	expect( $model->getBlockContent() )->toEqual( [
		[ 'clientId' => 'a', 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ],
	] );

	$model->setBlockContent( [
		[ 'clientId' => 'b', 'name' => 'core/heading', 'attributes' => ['content' => 'Hi'], 'innerBlocks' => [] ],
	] );
	$model->save();

	expect( $model->fresh()->getBlockContent() )->toEqual( [
		[ 'clientId' => 'b', 'name' => 'core/heading', 'attributes' => ['content' => 'Hi'], 'innerBlocks' => [] ],
	] );
} );

it( 'casts the content column to array on retrieval', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Cast Check',
		'status'  => 'published',
		'content' => [ [ 'clientId' => 'c1', 'name' => 'core/paragraph', 'attributes' => [], 'innerBlocks' => [] ] ],
	] );

	$fetched = TestBlockContentModel::query()->find( $model->id );

	expect( $fetched->content )->toBeArray();
} );

it( 'returns empty array when block content column is null', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Empty',
		'status'  => 'published',
		'content' => null,
	] );

	expect( $model->getBlockContent() )->toBe( [] );
} );

it( 'applies the configured scope in forVisualEditor()', function () {
	TestBlockContentModel::create( [ 'title' => 'Draft',     'status' => 'draft' ] );
	TestBlockContentModel::create( [ 'title' => 'Live Post', 'status' => 'published' ] );

	$results = TestBlockContentModel::query()->forVisualEditor()->get();

	expect( $results )->toHaveCount( 1 )
		->and( $results->first()->title )->toBe( 'Live Post' );
} );

it( 'leaves the query untouched when no scope is configured', function () {
	TestBlockContentPageModel::create( [ 'title' => 'A', 'body' => [] ] );
	TestBlockContentPageModel::create( [ 'title' => 'B', 'body' => [] ] );

	$results = TestBlockContentPageModel::query()->forVisualEditor()->get();

	expect( $results )->toHaveCount( 2 );
} );

it( 'throws when the configured scope method does not exist on the model', function () {
	$model = new class extends \Illuminate\Database\Eloquent\Model {
		use \ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;

		protected $table = 'test_block_content_models';

		protected $guarded = [];

		protected string $blockContentScope = 'nonexistent';
	};

	expect( fn () => $model->newQuery()->forVisualEditor() )
		->toThrow( InvalidArgumentException::class );
} );

it( 'emits a searchable array stub for Scout integration', function () {
	$model = TestBlockContentModel::create( [
		'title'   => 'Searchable',
		'status'  => 'published',
		'content' => [
			[ 'clientId' => 'x', 'name' => 'core/paragraph', 'attributes' => ['content' => 'Hello'], 'innerBlocks' => [] ],
		],
	] );

	expect( $model->toBlockContentSearchableArray() )->toEqual( [
		'block_content' => [
			[ 'clientId' => 'x', 'name' => 'core/paragraph', 'attributes' => ['content' => 'Hello'], 'innerBlocks' => [] ],
		],
	] );
} );
