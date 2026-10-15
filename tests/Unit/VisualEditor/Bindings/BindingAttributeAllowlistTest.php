<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingAttributeAllowlist;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\DefinesBindableAttributes;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\CustomFieldSource;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\PostCoreSource;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\RelationSource;
use Illuminate\Database\Eloquent\Model;
use Tests\Fixtures\TestBindingsModel;
use Tests\TestUser;

/**
 * Builds a post with a loaded `author` relation for the allowlist tests.
 *
 * @return array{0: TestBindingsModel, 1: TestUser}
 */
function allowlistPostWithAuthor( ?TestUser $author = null ): array
{
	$author ??= TestUser::query()->create( [
		'name'     => 'Grace Hopper',
		'email'    => 'grace+' . uniqid() . '@example.com',
		'password' => 'secret',
	] );

	$post = TestBindingsModel::query()->create( [
		'title'     => 'Welcome',
		'status'    => 'published',
		'author_id' => $author->getKey(),
		'content'   => [],
	] );
	$post->setRelation( 'author', $author );

	return [ $post, $author ];
}

afterEach( function (): void {
	removeAllFilters( 'ap.visualEditor.bindings.allowedAttributes' );
} );

describe( 'enforcement off (default)', function (): void {
	it( 'is not enforced unless the host opts in', function (): void {
		expect( BindingAttributeAllowlist::isEnforced() )->toBeFalse();
	} );

	it( 'leaves relation bindings unchanged', function (): void {
		[ $post ] = allowlistPostWithAuthor();
		$source   = new RelationSource();
		$ctx      = new BindingContext( $post );

		expect( $source->resolve( $ctx, [ 'path' => 'author.name' ] ) )->toBe( 'Grace Hopper' )
			->and( $source->resolve( $ctx, [ 'path' => 'author.email' ] ) )->toContain( '@example.com' );
	} );

	it( 'allows every key on any model', function (): void {
		expect( BindingAttributeAllowlist::allows( new TestUser(), 'email' ) )->toBeTrue();
	} );
} );

describe( 'enforcement on', function (): void {
	beforeEach( function (): void {
		config()->set( 'artisanpack.visual-editor.bindings.enforce_allowlist', true );
	} );

	it( 'denies related-model attributes that nothing allowlists', function (): void {
		[ $post ] = allowlistPostWithAuthor();

		expect( ( new RelationSource() )->resolve( new BindingContext( $post ), [ 'path' => 'author.name' ] ) )->toBeNull();
	} );

	it( 'keeps the parent record\'s own attributes readable', function (): void {
		[ $post ] = allowlistPostWithAuthor();

		expect( ( new RelationSource() )->resolve( new BindingContext( $post ), [ 'path' => 'title' ] ) )->toBe( 'Welcome' );
	} );

	it( 'reads attributes listed in the config map and denies the rest', function (): void {
		config()->set( 'artisanpack.visual-editor.bindings.allowed_attributes', [
			TestUser::class => [ 'name' ],
		] );

		[ $post ] = allowlistPostWithAuthor();
		$source   = new RelationSource();
		$ctx      = new BindingContext( $post );

		expect( $source->resolve( $ctx, [ 'path' => 'author.name' ] ) )->toBe( 'Grace Hopper' )
			->and( $source->resolve( $ctx, [ 'path' => 'author.email' ] ) )->toBeNull();
	} );

	it( 'matches config entries against parent classes', function (): void {
		config()->set( 'artisanpack.visual-editor.bindings.allowed_attributes', [
			Model::class => [ 'name' ],
		] );

		expect( BindingAttributeAllowlist::allows( new TestUser(), 'name' ) )->toBeTrue()
			->and( BindingAttributeAllowlist::allows( new TestUser(), 'email' ) )->toBeFalse();
	} );

	it( 'reads attributes the model declares bindable', function (): void {
		$bindableUser = new class extends TestUser implements DefinesBindableAttributes {
			public function bindableAttributes(): array
			{
				return [ 'name' ];
			}
		};

		$author = $bindableUser::query()->create( [
			'name'     => 'Ada Lovelace',
			'email'    => 'ada+' . uniqid() . '@example.com',
			'password' => 'secret',
		] );

		[ $post ] = allowlistPostWithAuthor( $author );
		$source   = new RelationSource();
		$ctx      = new BindingContext( $post );

		expect( $source->resolve( $ctx, [ 'path' => 'author.name' ] ) )->toBe( 'Ada Lovelace' )
			->and( $source->resolve( $ctx, [ 'path' => 'author.email' ] ) )->toBeNull();
	} );

	it( 'still honours $hidden for allowlisted keys', function (): void {
		config()->set( 'artisanpack.visual-editor.bindings.allowed_attributes', [
			TestUser::class => [ '*' ],
		] );

		[ $post, $author ] = allowlistPostWithAuthor();
		$author->makeHidden( 'email' );
		$source = new RelationSource();
		$ctx    = new BindingContext( $post );

		expect( $source->resolve( $ctx, [ 'path' => 'author.name' ] ) )->toBe( 'Grace Hopper' )
			->and( $source->resolve( $ctx, [ 'path' => 'author.email' ] ) )->toBeNull();
	} );

	it( 'lets the filter allow and deny attributes per path', function (): void {
		config()->set( 'artisanpack.visual-editor.bindings.allowed_attributes', [
			TestUser::class => [ 'name' ],
		] );

		$seen = [];

		addFilter( 'ap.visualEditor.bindings.allowedAttributes', function ( array $allowed, Model $model, string $path ) use ( &$seen ): array {
			$seen[] = [ $model::class, $path ];

			if ( 'author.email' === $path ) {
				return [ 'email' ];
			}

			return [];
		}, 10, 3 );

		[ $post ] = allowlistPostWithAuthor();
		$source   = new RelationSource();
		$ctx      = new BindingContext( $post );

		expect( $source->resolve( $ctx, [ 'path' => 'author.email' ] ) )->toContain( '@example.com' )
			->and( $source->resolve( $ctx, [ 'path' => 'author.name' ] ) )->toBeNull()
			->and( $seen )->toContain( [ TestUser::class, 'author.email' ] );
	} );

	it( 'refuses a path that ends on a whole related record', function (): void {
		config()->set( 'artisanpack.visual-editor.bindings.allowed_attributes', [
			TestUser::class => [ '*' ],
		] );

		[ $post ] = allowlistPostWithAuthor();

		expect( ( new RelationSource() )->resolve( new BindingContext( $post ), [ 'path' => 'author' ] ) )->toBeNull();
	} );

	it( 'refuses a custom field key that names a relation', function (): void {
		[ $post ] = allowlistPostWithAuthor();
		$source   = new CustomFieldSource();
		$ctx      = new BindingContext( $post );

		expect( $source->resolve( $ctx, [ 'key' => 'author' ] ) )->toBeNull()
			->and( $source->resolve( $ctx, [ 'key' => 'title' ] ) )->toBe( 'Welcome' );
	} );

	it( 'gates the post_core author name on the author model', function (): void {
		[ $post ] = allowlistPostWithAuthor();
		$source   = new PostCoreSource();
		$ctx      = new BindingContext( $post );

		expect( $source->resolve( $ctx, [ 'key' => 'author_name' ] ) )->toBeNull()
			->and( $source->resolve( $ctx, [ 'key' => 'title' ] ) )->toBe( 'Welcome' );

		config()->set( 'artisanpack.visual-editor.bindings.allowed_attributes', [
			TestUser::class => [ 'name' ],
		] );

		expect( $source->resolve( $ctx, [ 'key' => 'author_name' ] ) )->toBe( 'Grace Hopper' );
	} );

	it( 'drops author_name from the post_core picker when the author model denies it', function (): void {
		$keys = fn (): array => array_column( ( new PostCoreSource() )->availableFields( 'bindings', TestBindingsModel::class ), 'key' );

		expect( $keys() )->not->toContain( 'author_name' )->toContain( 'title' );

		config()->set( 'artisanpack.visual-editor.bindings.allowed_attributes', [
			TestUser::class => [ 'name' ],
		] );

		expect( $keys() )->toContain( 'author_name' );
	} );
} );
