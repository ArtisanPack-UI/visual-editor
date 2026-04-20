<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use Tests\Fixtures\TestBlockContentModel;

it( 'merges block content text with a model\'s own searchable fields', function () {
	$searchable = new class extends TestBlockContentModel {
		protected $table = 'test_block_content_models';

		/**
		 * Mirrors the pattern a Scout-backed model would use: merge
		 * HasBlockContent's plain-text output with the model's native
		 * columns so both are indexed under a single record.
		 *
		 * @return array<string, mixed>
		 */
		public function toSearchableArray(): array
		{
			return array_merge(
				[ 'title' => $this->title ],
				$this->toBlockContentSearchableArray()
			);
		}
	};

	$model = $searchable::create( [
		'title'   => 'Release Notes',
		'status'  => 'published',
		'content' => [
			[
				'clientId'    => 'h',
				'name'        => 'core/heading',
				'attributes'  => [ 'content' => 'Whats new in 1.0' ],
				'innerBlocks' => [],
			],
			[
				'clientId'    => 'p',
				'name'        => 'core/paragraph',
				'attributes'  => [ 'content' => 'Ships search extraction today.' ],
				'innerBlocks' => [],
			],
		],
	] );

	expect( $model->toSearchableArray() )->toBe( [
		'title'         => 'Release Notes',
		'block_content' => 'Whats new in 1.0 Ships search extraction today.',
	] );
} );

it( 'resolves dynamic block data at index time', function () {
	$products = [
		1 => 'Eloquent T-Shirt',
		2 => 'Blade Coffee Mug',
		3 => 'Livewire Notebook',
	];

	$block = new class ( $products ) extends DynamicBlock {
		/**
		 * @param  array<int, string>  $catalog
		 */
		public function __construct( protected array $catalog ) {}

		public function name(): string
		{
			return 'acme/latest-products';
		}

		public function render( array $attrs ): string
		{
			return '';
		}

		public function searchableText( array $attrs ): string
		{
			$ids   = is_array( $attrs['productIds'] ?? null ) ? $attrs['productIds'] : [];
			$names = [];

			foreach ( $ids as $id ) {
				if ( isset( $this->catalog[ $id ] ) ) {
					$names[] = $this->catalog[ $id ];
				}
			}

			return implode( ' ', $names );
		}
	};

	VisualEditor::registerDynamicBlock( $block );

	$model = TestBlockContentModel::create( [
		'title'   => 'Store Front',
		'status'  => 'published',
		'content' => [
			[
				'clientId'    => 'intro',
				'name'        => 'core/paragraph',
				'attributes'  => [ 'content' => 'Our current picks:' ],
				'innerBlocks' => [],
			],
			[
				'clientId'    => 'latest',
				'name'        => 'acme/latest-products',
				'attributes'  => [ 'productIds' => [ 1, 3 ] ],
				'innerBlocks' => [],
			],
		],
	] );

	$text = $model->blockContentSearchableText();

	expect( $text )->toBe( 'Our current picks: Eloquent T-Shirt Livewire Notebook' )
		->and( $text )->toContain( 'Eloquent T-Shirt' )
		->and( $text )->toContain( 'Livewire Notebook' )
		->and( $text )->not->toContain( 'Blade Coffee Mug' );
} );

it( 'reflects updated dynamic data on the next index call', function () {
	$products = [
		1 => 'Original Name',
	];

	$block = new class ( $products ) extends DynamicBlock {
		/**
		 * @param  array<int, string>  $catalog
		 */
		public function __construct( protected array $catalog ) {}

		public function name(): string
		{
			return 'acme/latest-products';
		}

		public function render( array $attrs ): string
		{
			return '';
		}

		public function updateCatalog( int $id, string $name ): void
		{
			$this->catalog[ $id ] = $name;
		}

		public function searchableText( array $attrs ): string
		{
			$ids   = is_array( $attrs['productIds'] ?? null ) ? $attrs['productIds'] : [];
			$names = [];

			foreach ( $ids as $id ) {
				if ( isset( $this->catalog[ $id ] ) ) {
					$names[] = $this->catalog[ $id ];
				}
			}

			return implode( ' ', $names );
		}
	};

	VisualEditor::registerDynamicBlock( $block );

	$model = TestBlockContentModel::create( [
		'title'   => 'Rename Demo',
		'status'  => 'published',
		'content' => [
			[
				'clientId'    => 'latest',
				'name'        => 'acme/latest-products',
				'attributes'  => [ 'productIds' => [ 1 ] ],
				'innerBlocks' => [],
			],
		],
	] );

	expect( $model->blockContentSearchableText() )->toBe( 'Original Name' );

	$block->updateCatalog( 1, 'Renamed Product' );

	expect( $model->blockContentSearchableText() )->toBe( 'Renamed Product' );
} );
