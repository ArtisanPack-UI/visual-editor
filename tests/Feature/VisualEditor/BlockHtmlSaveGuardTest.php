<?php

/**
 * Block markup filter on the save paths (#1062). Acting as a user without
 * the unfiltered-HTML ability, a save strips active markup and keeps what's
 * already stored on the record.
 *
 * @since 1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Effects\CustomCssModelGuard;
use ArtisanPackUI\VisualEditor\Models\Snippet;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use ArtisanPackUI\VisualEditor\Support\UnfilteredHtmlAccess;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\TestBlockContentModel;
use Tests\Fixtures\TestG3Policy;
use Tests\TestUser;

beforeEach( function (): void {
	config()->set( 'artisanpack.visual-editor.resources', [ 'posts' => TestBlockContentModel::class ] );
	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();
	Gate::policy( TestBlockContentModel::class, TestG3Policy::class );

	app()->bind( SiteEditorAccessGate::class, fn (): SiteEditorAccessGate => new class implements SiteEditorAccessGate {
		public function check( Request $request ): ?Response
		{
			return null;
		}
	} );

	$this->actingAs( TestUser::create( [
		'name'     => 'Html guard tester',
		'email'    => 'html-guard+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );
} );

/**
 * A paragraph block holding the given markup.
 *
 * @return array<string, mixed>
 */
function savedHtmlBlock( string $clientId, string $content ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'artisanpack/paragraph',
		'attributes'  => [ 'content' => $content ],
		'innerBlocks' => [],
	];
}

const ACTIVE_MARKUP = 'Hi<script>alert(1)</script> <a href="javascript:alert(1)" onclick="x">there</a>';

it( 'filters markup saved through the content API', function (): void {
	$post = TestBlockContentModel::create( [ 'title' => 'Post', 'status' => 'published', 'content' => [] ] );

	$this->putJson( "/visual-editor/api/posts/{$post->id}/content", [
		'blocks' => [ savedHtmlBlock( 'a', ACTIVE_MARKUP ) ],
	] )->assertOk()->assertJsonPath( 'blocks.0.attributes.content', 'Hi <a>there</a>' );

	expect( $post->fresh()->getBlockContent()[0]['attributes']['content'] )->toBe( 'Hi <a>there</a>' );
} );

it( 'filters markup saved through a WP-shape entity update', function (): void {
	$post = TestBlockContentModel::create( [ 'title' => 'Post', 'status' => 'published', 'content' => [] ] );

	$this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'content' => [ 'raw' => '', 'blocks' => [ savedHtmlBlock( 'a', ACTIVE_MARKUP ) ] ],
	] )->assertOk();

	expect( $post->fresh()->getBlockContent()[0]['attributes']['content'] )->toBe( 'Hi <a>there</a>' );
} );

it( 'filters markup saved through a snippet', function (): void {
	$this->postJson( '/visual-editor/api/snippets', [
		'slug'   => 'snippet',
		'title'  => 'Snippet',
		'blocks' => [ savedHtmlBlock( 'a', ACTIVE_MARKUP ) ],
	] )->assertCreated();

	expect( Snippet::query()->where( 'slug', 'snippet' )->sole()->blocks[0]['attributes']['content'] )->toBe( 'Hi <a>there</a>' );
} );

it( 'filters a write that skips the controllers', function (): void {
	$post = TestBlockContentModel::create( [
		'title'   => 'Direct',
		'status'  => 'published',
		'content' => [ savedHtmlBlock( 'a', ACTIVE_MARKUP ) ],
	] );

	expect( $post->fresh()->getBlockContent()[0]['attributes']['content'] )->toBe( 'Hi <a>there</a>' );
} );

it( 'keeps markup already stored on the record', function (): void {
	$post = app( CustomCssModelGuard::class )->trusted( fn (): TestBlockContentModel => TestBlockContentModel::create( [
		'title'   => 'Stored',
		'status'  => 'published',
		'content' => [ savedHtmlBlock( 'a', '<script>admin()</script>' ) ],
	] ) );

	$this->putJson( "/visual-editor/api/posts/{$post->id}/content", [
		'blocks' => [ savedHtmlBlock( 'b', 'New' ), savedHtmlBlock( 'a', '<script>admin()</script>' ) ],
	] )->assertOk();

	expect( $post->fresh()->getBlockContent()[1]['attributes']['content'] )->toBe( '<script>admin()</script>' );
} );

it( 'leaves markup alone for a user with the ability', function (): void {
	Gate::define( UnfilteredHtmlAccess::ABILITY, fn ( ?Authenticatable $user = null ): bool => true );

	$post = TestBlockContentModel::create( [ 'title' => 'Post', 'status' => 'published', 'content' => [] ] );

	$this->putJson( "/visual-editor/api/posts/{$post->id}/content", [
		'blocks' => [ savedHtmlBlock( 'a', ACTIVE_MARKUP ) ],
	] )->assertOk();

	expect( $post->fresh()->getBlockContent()[0]['attributes']['content'] )->toBe( ACTIVE_MARKUP );
} );

it( 'does not let markup saved under a skipped key vouch for a Custom HTML block', function (): void {
	$post    = TestBlockContentModel::create( [ 'title' => 'Post', 'status' => 'published', 'content' => [] ] );
	$payload = '<img src=x onerror=alert(document.domain)>';

	$this->putJson( "/visual-editor/api/posts/{$post->id}/content", [
		'blocks' => [ [ 'clientId' => 'a', 'name' => 'core/image', 'attributes' => [ 'alt' => $payload ], 'innerBlocks' => [] ] ],
	] )->assertOk();

	expect( $post->fresh()->getBlockContent()[0]['attributes']['alt'] )->toBe( $payload );

	app()->forgetScopedInstances();

	$this->putJson( "/visual-editor/api/posts/{$post->id}/content", [
		'blocks' => [ [
			'clientId'    => 'b',
			'name'        => 'core/html',
			'attributes'  => [ 'content' => $payload ],
			'innerHTML'   => $payload,
			'innerBlocks' => [],
		] ],
	] )->assertOk();

	$saved = $post->fresh()->getBlockContent()[0];

	expect( $saved['attributes']['content'] )->not->toContain( 'onerror' )
		->and( $saved['innerHTML'] )->not->toContain( 'onerror' );
} );

it( 'filters a title or excerpt saved through the entry endpoint', function ( string $field ): void {
	$post = TestBlockContentModel::create( [ 'title' => 'Post', 'status' => 'published', 'content' => [] ] );

	$this->putJson( "/visual-editor/api/posts/{$post->id}/entry", [
		$field => '<img src=x onerror=alert(1)>Hi',
	] )->assertOk();

	$saved = (string) $post->fresh()->getAttribute( $field );

	expect( $saved )->not->toContain( 'onerror' )
		->and( $saved )->toContain( 'Hi' );
} )->with( [ 'title', 'excerpt' ] );

it( 'filters a title or excerpt saved through a WP-shape entity update', function ( string $field ): void {
	$post = TestBlockContentModel::create( [ 'title' => 'Post', 'status' => 'published', 'content' => [] ] );

	$this->putJson( "/visual-editor/api/posts/{$post->id}", [
		$field => '<img src=x onerror=alert(1)>Hi',
	] )->assertOk();

	$saved = (string) $post->fresh()->getAttribute( $field );

	expect( $saved )->not->toContain( 'onerror' )
		->and( $saved )->toContain( 'Hi' );
} )->with( [ 'title', 'excerpt' ] );

it( 'filters a title saved through a WP-shape entity create', function (): void {
	$id = $this->postJson( '/visual-editor/api/posts', [
		'title'  => '<img src=x onerror=alert(1)>Hi',
		'status' => 'draft',
	] )->assertCreated()->json( 'id' );

	$saved = (string) TestBlockContentModel::query()->findOrFail( $id )->title;

	expect( $saved )->not->toContain( 'onerror' )
		->and( $saved )->toContain( 'Hi' );
} );

it( 'keeps a title with markup for a user with the ability', function (): void {
	Gate::define( UnfilteredHtmlAccess::ABILITY, fn ( ?Authenticatable $user = null ): bool => true );

	$post = TestBlockContentModel::create( [ 'title' => 'Post', 'status' => 'published', 'content' => [] ] );

	$this->putJson( "/visual-editor/api/posts/{$post->id}/entry", [ 'title' => '<em onclick="x">Hi</em>' ] )->assertOk();
	expect( $post->fresh()->title )->toBe( '<em onclick="x">Hi</em>' );

	$this->putJson( "/visual-editor/api/posts/{$post->id}", [ 'title' => '<b onclick="y">Hi</b>' ] )->assertOk();
	expect( $post->fresh()->title )->toBe( '<b onclick="y">Hi</b>' );
} );

it( 'keeps an unchanged stored title that holds markup', function (): void {
	$post = TestBlockContentModel::create( [ 'title' => '<em onclick="x">Admin</em>', 'status' => 'published', 'content' => [] ] );

	$this->putJson( "/visual-editor/api/posts/{$post->id}/entry", [
		'title'   => '<em onclick="x">Admin</em>',
		'excerpt' => 'Changed',
	] )->assertOk();

	expect( $post->fresh()->title )->toBe( '<em onclick="x">Admin</em>' )
		->and( $post->fresh()->excerpt )->toBe( 'Changed' );

	$this->putJson( "/visual-editor/api/posts/{$post->id}", [ 'title' => '<em onclick="x">Admin</em>' ] )->assertOk();

	expect( $post->fresh()->title )->toBe( '<em onclick="x">Admin</em>' );
} );
