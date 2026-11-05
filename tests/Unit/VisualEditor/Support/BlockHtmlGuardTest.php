<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Support\BlockContentGuard;
use ArtisanPackUI\VisualEditor\Support\BlockHtmlGuard;
use ArtisanPackUI\VisualEditor\Support\UnfilteredHtmlAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

function htmlBlock( string $content, array $innerBlocks = [] ): array
{
	return [ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => $content ], 'innerBlocks' => $innerBlocks ];
}

function allowUnfilteredHtml( bool $allowed ): void
{
	Gate::define( UnfilteredHtmlAccess::ABILITY, fn ( ?Authenticatable $user = null ): bool => $allowed );
}

it( 'saves markup as sent for users with the ability', function (): void {
	allowUnfilteredHtml( true );

	$blocks = [ htmlBlock( '<script>alert(1)</script>' ) ];

	expect( app( BlockHtmlGuard::class )->filter( $blocks ) )->toBe( $blocks );
} );

it( 'filters markup at any depth for users without it', function (): void {
	allowUnfilteredHtml( false );

	$filtered = app( BlockHtmlGuard::class )->filter( [
		htmlBlock( 'a<script>1</script>', [ htmlBlock( '<a href="javascript:x">b</a>' ) ] ),
	] );

	expect( $filtered[0]['attributes']['content'] )->toBe( 'a' )
		->and( $filtered[0]['innerBlocks'][0]['attributes']['content'] )->toBe( '<a>b</a>' );
} );

it( 'filters every key a renderer can read', function (): void {
	allowUnfilteredHtml( false );

	[ $block ] = app( BlockHtmlGuard::class )->filter( [ [
		'blockName'    => 'core/table',
		'attrs'        => [ 'body' => [ [ 'cells' => [ [ 'content' => '<img src=x onerror=1>' ] ] ] ] ],
		'innerHTML'    => '<p onclick="x">a</p>',
		'innerContent' => [ '<script>1</script>b', null ],
	] ] );

	expect( $block['attrs']['body'][0]['cells'][0]['content'] )->toBe( '<img src="x">' )
		->and( $block['innerHTML'] )->toBe( '<p>a</p>' )
		->and( $block['innerContent'] )->toBe( [ 'b', null ] );
} );

it( 'keeps markup already stored on the record', function (): void {
	allowUnfilteredHtml( false );

	$stored   = [ htmlBlock( '<script>admin()</script>' ) ];
	$filtered = app( BlockHtmlGuard::class )->filter( [ htmlBlock( 'moved', [ htmlBlock( '<script>admin()</script>' ) ] ) ], $stored );

	expect( $filtered[0]['innerBlocks'][0]['attributes']['content'] )->toBe( '<script>admin()</script>' );
} );

it( 'filters a changed value on the record', function (): void {
	allowUnfilteredHtml( false );

	$filtered = app( BlockHtmlGuard::class )->filter( [ htmlBlock( '<script>admin(1)</script>' ) ], [ htmlBlock( '<script>admin()</script>' ) ] );

	expect( $filtered[0]['attributes']['content'] )->toBe( '' );
} );

it( 'keeps vouched-for values through a later model-level pass', function (): void {
	allowUnfilteredHtml( false );

	$guard = app( BlockHtmlGuard::class );
	$theme = [ htmlBlock( '<script>theme()</script>' ) ];

	$guard->filter( $theme, $theme );

	expect( $guard->filterAgainst( $theme, null ) )->toBe( $theme );
} );

it( 'does not vouch for stored values for later requests', function (): void {
	allowUnfilteredHtml( false );

	$theme = [ htmlBlock( '<script>theme()</script>' ) ];

	app( BlockHtmlGuard::class )->filter( $theme, $theme );
	app()->forgetScopedInstances();

	expect( app( BlockHtmlGuard::class )->filterAgainst( $theme, null )[0]['attributes']['content'] )->toBe( '' );
} );

it( 'does not vouch for markup stored under a skipped key', function ( array $stored ): void {
	allowUnfilteredHtml( false );

	$incoming = [ [ 'name' => 'core/html', 'attributes' => [ 'content' => '<img src=x onerror=alert(1)>' ] ] ];

	expect( app( BlockHtmlGuard::class )->filterAgainst( $incoming, $stored )[0]['attributes']['content'] )->not->toContain( 'onerror' );

	app()->forgetScopedInstances();

	expect( app( BlockHtmlGuard::class )->filter( $incoming, $stored )[0]['attributes']['content'] )->not->toContain( 'onerror' );
} )->with( [
	'alt'    => [ [ [ 'name' => 'core/image', 'attributes' => [ 'alt' => '<img src=x onerror=alert(1)>' ] ] ] ],
	'search' => [ [ [ 'name' => 'core/query', 'attributes' => [ 'query' => [ 'search' => '<img src=x onerror=alert(1)>' ] ] ] ] ],
] );

it( 'leaves plain text fields alone', function (): void {
	allowUnfilteredHtml( false );

	$blocks = [ [ 'name' => 'artisanpack/image', 'attributes' => [ 'alt' => 'List<String> diagram', 'query' => [ 'search' => 'Vector<int>' ] ] ] ];

	expect( app( BlockHtmlGuard::class )->filter( $blocks ) )->toBe( $blocks );
} );

it( 'leaves a search placeholder alone', function ( string $name, string $shape ): void {
	allowUnfilteredHtml( false );

	$nameKey = 'attrs' === $shape ? 'blockName' : 'name';
	$blocks  = [ [ $nameKey => $name, $shape => [ 'placeholder' => 'Use <Enter> to submit', 'label' => 'List<String> & Map<K, V>' ] ] ];

	$filtered = app( BlockHtmlGuard::class )->filter( $blocks );

	expect( $filtered[0][ $shape ]['placeholder'] )->toBe( 'Use <Enter> to submit' )
		->and( $filtered[0][ $shape ]['label'] )->not->toContain( '<String>' );
} )->with( [
	'core/search'              => [ 'core/search', 'attributes' ],
	'core/search, parsed tree' => [ 'core/search', 'attrs' ],
	'artisanpack/search'       => [ 'artisanpack/search', 'attributes' ],
	'artisanpack/search-field' => [ 'artisanpack/search-field', 'attributes' ],
] );

it( 'filters a placeholder anywhere but a search block\'s own attributes', function ( array $block ): void {
	allowUnfilteredHtml( false );

	$filtered = app( BlockHtmlGuard::class )->filter( [ $block ] );

	expect( json_encode( $filtered ) )->not->toContain( 'onerror' );
} )->with( [
	'binding placeholder' => [ [
		'name'       => 'artisanpack/paragraph',
		'attributes' => [ 'bindings' => [ 'content' => [ 'source' => 'x', 'onEmpty' => 'placeholder', 'placeholder' => '<img src=x onerror=alert(1)>' ] ] ],
	] ],
	'search binding'      => [ [
		'name'       => 'core/search',
		'attributes' => [ 'bindings' => [ 'label' => [ 'source' => 'x', 'placeholder' => '<img src=x onerror=alert(1)>' ] ] ],
	] ],
	'other block'         => [ [ 'name' => 'acme/field', 'attributes' => [ 'placeholder' => '<img src=x onerror=alert(1)>' ] ] ],
] );

it( 'does not vouch for markup stored in a search placeholder', function (): void {
	allowUnfilteredHtml( false );

	$stored   = [ [ 'name' => 'core/search', 'attributes' => [ 'placeholder' => '<img src=x onerror=alert(1)>' ] ] ];
	$incoming = [ [ 'name' => 'core/html', 'attributes' => [ 'content' => '<img src=x onerror=alert(1)>' ] ] ];

	expect( app( BlockHtmlGuard::class )->filter( $incoming, $stored )[0]['attributes']['content'] )->not->toContain( 'onerror' );
} );

it( 'leaves Custom CSS to its own guard', function (): void {
	allowUnfilteredHtml( false );

	$blocks = [ [ 'name' => 'core/group', 'attributes' => [ 'artisanpackEffects' => [ 'customCss' => 'a::before { content: "<b>"; }' ] ] ] ];

	expect( app( BlockHtmlGuard::class )->filter( $blocks ) )->toBe( $blocks );
} );

it( 'passes anything that is not an array through', function ( mixed $value ): void {
	allowUnfilteredHtml( false );

	expect( app( BlockHtmlGuard::class )->filter( $value ) )->toBe( $value );
} )->with( [ 'null' => [ null ], 'string' => [ '<script>' ], 'int' => [ 1 ] ] );

it( 'denies by default, including for a user with no permission backend', function (): void {
	expect( UnfilteredHtmlAccess::allows( null ) )->toBeFalse();

	$user = new class implements Authenticatable {
		public function getAuthIdentifierName(): string
		{
		return 'id';
		}

		public function getAuthIdentifier(): int
		{
		return 1;
		}

		public function getAuthPassword(): string
		{
		return '';
		}

		public function getAuthPasswordName(): string
		{
		return 'password';
		}

		public function getRememberToken(): string
		{
		return '';
		}

		public function setRememberToken( $value ): void
		{
		}

		public function getRememberTokenName(): string
		{
		return '';
		}
	};

	expect( UnfilteredHtmlAccess::allows( $user ) )->toBeFalse();
} );

it( 'reads the configured capability', function (): void {
	$user = new class implements Authenticatable {
		public array $checked = [];

		public function getAuthIdentifierName(): string
		{
		return 'id';
		}

		public function getAuthIdentifier(): int
		{
		return 1;
		}

		public function getAuthPassword(): string
		{
		return '';
		}

		public function getAuthPasswordName(): string
		{
		return 'password';
		}

		public function getRememberToken(): string
		{
		return '';
		}

		public function setRememberToken( $value ): void
		{
		}

		public function getRememberTokenName(): string
		{
		return '';
		}

		public function hasCapability( string $capability ): bool
		{
		return 'raw_html' === $capability;
		}
	};

	config()->set( 'artisanpack.visual-editor.content_filter.unfiltered_html_capability', 'raw_html' );
	expect( UnfilteredHtmlAccess::allows( $user ) )->toBeTrue();

	config()->set( 'artisanpack.visual-editor.content_filter.unfiltered_html_capability', '' );
	expect( UnfilteredHtmlAccess::allows( $user ) )->toBeFalse();
} );

it( 'runs both guards through BlockContentGuard', function (): void {
	allowUnfilteredHtml( false );
	Gate::define( 'visual-editor.edit-block-css', fn ( ?Authenticatable $user = null ): bool => false );

	$block = htmlBlock( 'a<script>1</script>' );

	$block['attributes']['artisanpackEffects'] = [ 'customCss' => 'color: red;' ];

	[ $filtered ] = app( BlockContentGuard::class )->filter( [ $block ] );

	expect( $filtered['attributes']['content'] )->toBe( 'a' )
		->and( $filtered['attributes']['artisanpackEffects'] )->not->toHaveKey( 'customCss' );
} );
