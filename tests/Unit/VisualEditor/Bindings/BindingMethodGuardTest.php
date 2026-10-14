<?php

/**
 * Binding keys and paths must never call arbitrary model methods (#850).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.13.1
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\ModelAttributeVisibility;
use Tests\Fixtures\TestBindingsModel;

/**
 * A record with a side-effecting public method and an untyped relation,
 * neither of which a binding may call.
 */
function guardedBindingsModel(): TestBindingsModel
{
	return new class extends TestBindingsModel {
		public static bool $wiped = false;

		public function wipe()
		{
			static::$wiped = true;

			return null;
		}

		public function untypedAuthor()
		{
			static::$wiped = true;

			return $this->belongsTo( Tests\TestUser::class, 'author_id' );
		}
	};
}

/**
 * @param  array<string, mixed>  $binding
 *
 * @return array<int, array<string, mixed>>
 */
function guardedTree( array $binding ): array
{
	return [ [
		'name'       => 'core/paragraph',
		'attributes' => [ 'content' => 'Static', 'bindings' => [ 'content' => $binding ] ],
	] ];
}

beforeEach( function (): void {
	$this->resolver = $this->app->make( BindingResolver::class );
} );

it( 'does not call a method named by a custom_field key', function (): void {
	$model         = guardedBindingsModel();
	$model::$wiped = false;

	$result = $this->resolver->resolve(
		guardedTree( [ 'source' => 'custom_field', 'args' => [ 'key' => 'wipe' ] ] ),
		new BindingContext( $model ),
	);

	expect( $model::$wiped )->toBeFalse()
		->and( $result[0]['attributes']['content'] )->toBe( 'Static' );
} );

it( 'does not call untyped methods named in a relation path', function ( string $path ): void {
	$model         = guardedBindingsModel();
	$model::$wiped = false;

	$result = $this->resolver->resolve(
		guardedTree( [ 'source' => 'relation', 'args' => [ 'path' => $path ] ] ),
		new BindingContext( $model ),
	);

	expect( $model::$wiped )->toBeFalse()
		->and( $result[0]['attributes']['content'] )->toBe( 'Static' );
} )->with( [ 'wipe.name', 'untypedAuthor.name' ] );

it( 'does not call untyped methods while eager-loading across models', function (): void {
	$model         = guardedBindingsModel();
	$model::$wiped = false;

	$this->resolver->eagerLoadAcross(
		guardedTree( [ 'source' => 'relation', 'args' => [ 'path' => 'untypedAuthor.name' ] ] ),
		[ $model, clone $model ],
	);

	expect( $model::$wiped )->toBeFalse();
} );

it( 'does not forward eager-load paths to query builder methods', function (): void {
	$post = TestBindingsModel::query()->create( [ 'title' => 'Keep me', 'status' => 'published', 'content' => [] ] );

	$this->resolver->resolve(
		guardedTree( [ 'source' => 'relation', 'args' => [ 'path' => 'truncate.name' ] ] ),
		new BindingContext( $post ),
	);

	$this->resolver->eagerLoadAcross(
		guardedTree( [ 'source' => 'relation', 'args' => [ 'path' => 'truncate.name' ] ] ),
		[ $post ],
	);

	expect( TestBindingsModel::query()->count() )->toBe( 1 );
} );

it( 'still resolves typed relations', function (): void {
	$author = Tests\TestUser::query()->create( [
		'name'     => 'Ada Lovelace',
		'email'    => 'ada+' . uniqid() . '@example.com',
		'password' => 'secret',
	] );

	$post = TestBindingsModel::query()->create( [
		'title'     => 'Typed',
		'status'    => 'published',
		'author_id' => $author->getKey(),
		'content'   => [],
	] );

	$result = $this->resolver->resolve(
		guardedTree( [ 'source' => 'relation', 'args' => [ 'path' => 'author.name' ] ] ),
		new BindingContext( $post->fresh() ),
	);

	expect( $result[0]['attributes']['content'] )->toBe( 'Ada Lovelace' )
		->and( ModelAttributeVisibility::isRelationMethod( $post, 'author' ) )->toBeTrue()
		->and( ModelAttributeVisibility::isRelationMethod( $post, 'delete' ) )->toBeFalse()
		->and( ModelAttributeVisibility::isSafeToRead( $post, 'title' ) )->toBeTrue();
} );

it( 'checks each relation segment once per model class', function (): void {
	$model = new class extends TestBindingsModel {
		public static int $calls = 0;

		public function countedAuthor(): Illuminate\Database\Eloquent\Relations\BelongsTo
		{
			static::$calls++;

			return $this->belongsTo( Tests\TestUser::class, 'author_id' );
		}
	};

	$model::$calls = 0;
	$resolver      = $this->resolver;
	$check         = fn ( TestBindingsModel $record ): bool => ( fn (): bool => $this->isLoadableChain( $record, 'countedAuthor' ) )->call( $resolver );

	expect( $check( $model ) )->toBeTrue()
		->and( $check( $model->newInstance() ) )->toBeTrue()
		->and( $model::$calls )->toBe( 1 );
} );

it( 're-checks visibility per instance even when the relation segment is cached', function (): void {
	$post     = new TestBindingsModel();
	$resolver = $this->resolver;
	$check    = fn ( TestBindingsModel $record ): bool => ( fn (): bool => $this->isLoadableChain( $record, 'author' ) )->call( $resolver );

	expect( $check( $post ) )->toBeTrue()
		->and( $check( ( new TestBindingsModel() )->makeHidden( 'author' ) ) )->toBeFalse();
} );
