<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TestBindingsModel;
use Tests\Fixtures\TestBlockContentModel;

function makeStaticSource( string $name, callable $resolver, array $eagerRelations = [] ): BlockBindingSource
{
	return new class( $name, $resolver, $eagerRelations ) implements BlockBindingSource
	{
		public function __construct(
			protected string $sourceName,
			protected $resolver,
			protected array $eager
		) {
		}

		public function name(): string
		{
			return $this->sourceName;
		}

		public function resolve( BindingContext $context, array $args ): mixed
		{
			return ( $this->resolver )( $context, $args );
		}

		public function eagerLoadRelations( array $bindingArgs ): array
		{
			return $this->eager;
		}

		public function availableFields( string $resource, ?string $modelClass = null ): array
		{
			return [];
		}
	};
}

function buildResolver( array $sources = [] ): BindingResolver
{
	$registry = new BlockBindingSourceRegistry();

	foreach ( $sources as $source ) {
		$registry->register( $source );
	}

	return new BindingResolver( $registry );
}

it( 'returns an empty tree untouched', function () {
	$resolver = buildResolver();

	expect( $resolver->resolve( [] ) )->toBe( [] );
} );

it( 'leaves a tree without bindings byte-identical (BC regression)', function () {
	$resolver = buildResolver();

	$tree = [
		[
			'name'        => 'core/paragraph',
			'attrs'       => [ 'content' => 'Hello', 'fontSize' => 'large' ],
			'innerBlocks' => [
				[
					'name'  => 'core/heading',
					'attrs' => [ 'level' => 2, 'content' => 'Sub' ],
				],
			],
		],
		[
			'name'  => 'artisanpack/icon',
			'attrs' => [ 'icon' => 'o-star', 'size' => 'md' ],
		],
	];

	expect( $resolver->resolve( $tree ) )->toBe( $tree );
} );

it( 'overrides a static attribute with the bound value', function () {
	$resolver = buildResolver( [
		makeStaticSource( 'custom_field', fn () => 'o-heart' ),
	] );

	$tree = [
		[
			'name'     => 'artisanpack/icon',
			'attrs'    => [ 'icon' => 'o-star', 'size' => 'md' ],
			'bindings' => [
				'icon' => [
					'source' => 'custom_field',
					'args'   => [ 'key' => 'featured_icon' ],
				],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['attrs']['icon'] )->toBe( 'o-heart' )
		->and( $resolved[0]['attrs']['size'] )->toBe( 'md' );
} );

it( 'falls back to the static value by default when the binding is empty', function () {
	$resolver = buildResolver( [
		makeStaticSource( 'custom_field', fn () => null ),
	] );

	$tree = [
		[
			'name'     => 'artisanpack/icon',
			'attrs'    => [ 'icon' => 'o-star' ],
			'bindings' => [
				'icon' => [
					'source' => 'custom_field',
					'args'   => [ 'key' => 'featured_icon' ],
				],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['attrs']['icon'] )->toBe( 'o-star' );
} );

it( 'nulls the attribute when the empty-value policy is "hide"', function () {
	$resolver = buildResolver( [
		makeStaticSource( 'custom_field', fn () => null ),
	] );

	$tree = [
		[
			'name'     => 'artisanpack/icon',
			'attrs'    => [ 'icon' => 'o-star' ],
			'bindings' => [
				'icon' => [
					'source'  => 'custom_field',
					'args'    => [ 'key' => 'featured_icon' ],
					'onEmpty' => BindingResolver::POLICY_HIDE,
				],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['attrs'] )->toHaveKey( 'icon' )
		->and( $resolved[0]['attrs']['icon'] )->toBeNull();
} );

it( 'writes the placeholder when the empty-value policy is "placeholder"', function () {
	$resolver = buildResolver( [
		makeStaticSource( 'custom_field', fn () => null ),
	] );

	$tree = [
		[
			'name'     => 'artisanpack/icon',
			'attrs'    => [ 'icon' => 'o-star' ],
			'bindings' => [
				'icon' => [
					'source'      => 'custom_field',
					'args'        => [ 'key' => 'featured_icon' ],
					'onEmpty'     => BindingResolver::POLICY_PLACEHOLDER,
					'placeholder' => '— pick one —',
				],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['attrs']['icon'] )->toBe( '— pick one —' );
} );

it( 'falls back when the named source is not registered', function () {
	$resolver = buildResolver();

	$tree = [
		[
			'name'     => 'artisanpack/icon',
			'attrs'    => [ 'icon' => 'o-star' ],
			'bindings' => [
				'icon' => [ 'source' => 'unknown', 'args' => [] ],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['attrs']['icon'] )->toBe( 'o-star' );
} );

it( 'falls back when the source throws — does not break the render', function () {
	$resolver = buildResolver( [
		makeStaticSource( 'breaks', function () {
			throw new RuntimeException( 'boom' );
		} ),
	] );

	$tree = [
		[
			'name'     => 'artisanpack/icon',
			'attrs'    => [ 'icon' => 'o-star' ],
			'bindings' => [
				'icon' => [ 'source' => 'breaks', 'args' => [] ],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['attrs']['icon'] )->toBe( 'o-star' );
} );

it( 'treats 0 and false as legitimate (non-empty) bound values', function () {
	$resolver = buildResolver( [
		makeStaticSource( 'flag', fn () => false ),
		makeStaticSource( 'count', fn () => 0 ),
	] );

	$tree = [
		[
			'name'     => 'demo/two',
			'attrs'    => [ 'enabled' => true, 'count' => 5 ],
			'bindings' => [
				'enabled' => [ 'source' => 'flag', 'args' => [] ],
				'count'   => [ 'source' => 'count', 'args' => [] ],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['attrs']['enabled'] )->toBeFalse()
		->and( $resolved[0]['attrs']['count'] )->toBe( 0 );
} );

it( 'resolves bindings on innerBlocks recursively', function () {
	$resolver = buildResolver( [
		makeStaticSource( 'custom_field', fn ( BindingContext $context, array $args ) => 'value-of-' . ( $args['key'] ?? '' ) ),
	] );

	$tree = [
		[
			'name'        => 'core/group',
			'attrs'       => [],
			'innerBlocks' => [
				[
					'name'     => 'artisanpack/icon',
					'attrs'    => [ 'icon' => 'o-star' ],
					'bindings' => [
						'icon' => [
							'source' => 'custom_field',
							'args'   => [ 'key' => 'featured_icon' ],
						],
					],
				],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['innerBlocks'][0]['attrs']['icon'] )->toBe( 'value-of-featured_icon' );
} );

it( 'loads the parent model at most once across a tree of 20+ bound blocks', function () {
	$model = TestBlockContentModel::query()->create( [
		'title'   => 'Source Of Truth',
		'status'  => 'published',
		'content' => [],
	] );

	$resolver = buildResolver( [
		makeStaticSource(
			'custom_field',
			fn ( BindingContext $context, array $args ) => $context->model()?->getAttribute( $args['key'] ?? 'title' )
		),
	] );

	$tree = [];

	for ( $i = 0; $i < 25; $i++ ) {
		$tree[] = [
			'name'     => 'artisanpack/icon',
			'attrs'    => [ 'icon' => 'o-default' ],
			'bindings' => [
				'icon' => [
					'source' => 'custom_field',
					'args'   => [ 'key' => 'title' ],
				],
			],
		];
	}

	DB::connection()->enableQueryLog();
	DB::connection()->flushQueryLog();

	$resolved = $resolver->resolve( $tree, new BindingContext( $model ) );

	$queries = DB::connection()->getQueryLog();
	DB::connection()->disableQueryLog();

	expect( $queries )->toHaveCount( 0 )
		->and( $resolved )->toHaveCount( 25 )
		->and( $resolved[0]['attrs']['icon'] )->toBe( 'Source Of Truth' )
		->and( $resolved[24]['attrs']['icon'] )->toBe( 'Source Of Truth' );
} );

it( 'eager-loads relations declared by source drivers exactly once', function () {
	$author = Tests\TestUser::query()->create( [
		'name'     => 'Author Name',
		'email'    => 'author+' . uniqid() . '@example.com',
		'password' => 'secret',
	] );

	$model = TestBindingsModel::query()->create( [
		'title'     => 'Has Author',
		'status'    => 'published',
		'author_id' => $author->getKey(),
		'content'   => [],
	] );

	$resolver = buildResolver( [
		makeStaticSource(
			'post_core',
			fn ( BindingContext $context, array $args ) => $context->model()?->getAttribute( 'author' )?->getAttribute( 'name' ),
			[ 'author' ]
		),
	] );

	$tree = [];

	for ( $i = 0; $i < 10; $i++ ) {
		$tree[] = [
			'name'     => 'artisanpack/byline',
			'attrs'    => [ 'name' => 'Fallback' ],
			'bindings' => [
				'name' => [
					'source' => 'post_core',
					'args'   => [ 'key' => 'author_name' ],
				],
			],
		];
	}

	DB::connection()->enableQueryLog();
	DB::connection()->flushQueryLog();

	$result = $resolver->resolve( $tree, new BindingContext( $model ) );

	$queries = DB::connection()->getQueryLog();
	DB::connection()->disableQueryLog();

	// Exactly one query — the eager-load of the `author` relation.
	expect( $queries )->toHaveCount( 1 )
		->and( $result[0]['attrs']['name'] )->toBe( 'Author Name' )
		->and( $result[9]['attrs']['name'] )->toBe( 'Author Name' );
} );

it( 'ignores malformed binding entries on a block but still rewrites valid ones', function () {
	$resolver = buildResolver( [
		makeStaticSource( 'custom_field', fn () => 'GOOD' ),
	] );

	$tree = [
		[
			'name'     => 'artisanpack/icon',
			'attrs'    => [ 'icon' => 'o-star', 'label' => 'fallback' ],
			'bindings' => [
				'icon'  => [ 'source' => 'custom_field', 'args' => [] ],
				'label' => 'not-an-array',
				123     => [ 'source' => 'custom_field', 'args' => [] ],
			],
		],
	];

	$resolved = $resolver->resolve( $tree );

	expect( $resolved[0]['attrs']['icon'] )->toBe( 'GOOD' )
		->and( $resolved[0]['attrs']['label'] )->toBe( 'fallback' );
} );
