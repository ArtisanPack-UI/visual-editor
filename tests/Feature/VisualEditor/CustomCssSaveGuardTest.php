<?php

/**
 * Custom CSS guard coverage for the snippet and WP-shape entity save
 * paths (M-11). Acting as a user without the block-CSS ability, a save
 * keeps Custom CSS already stored on the record and strips new values.
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Effects\CustomCssModelGuard;
use ArtisanPackUI\VisualEditor\Models\Snippet;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
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
		'name'     => 'Save guard tester',
		'email'    => 'save-guard+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] ) );
} );

/**
 * A paragraph block carrying the given Custom CSS.
 *
 * @return array<string, mixed>
 */
function entityGuardBlock( string $clientId, string $css ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => 'Styled', 'artisanpackEffects' => [ 'opacity' => 50, 'customCss' => $css ] ],
		'innerBlocks' => [],
	];
}

it( 'strips Custom CSS when a snippet is created', function (): void {
	$this->postJson( '/visual-editor/api/snippets', [
		'slug'   => 'styled',
		'title'  => 'Styled',
		'blocks' => [ entityGuardBlock( 'a', 'display: none;' ) ],
	] )->assertCreated();

	expect( Snippet::query()->where( 'slug', 'styled' )->sole()->blocks[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'keeps stored and strips new Custom CSS when a snippet is updated', function (): void {
	$snippet = app( CustomCssModelGuard::class )->trusted( fn (): Snippet => Snippet::create( [
		'slug'   => 'styled',
		'title'  => 'Styled',
		'blocks' => [ entityGuardBlock( 'a', 'color: red;' ) ],
	] ) );

	$this->putJson( "/visual-editor/api/snippets/{$snippet->id}", [
		'slug'   => 'styled',
		'title'  => 'Styled',
		'blocks' => [ entityGuardBlock( 'a', 'color: red;' ), entityGuardBlock( 'b', 'display: none;' ) ],
	] )->assertOk();

	$stored = $snippet->fresh()->blocks;

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'keeps stored and strips new Custom CSS on a WP-shape entity update', function (): void {
	$post = app( CustomCssModelGuard::class )->trusted( fn (): TestBlockContentModel => TestBlockContentModel::create( [
		'title'   => 'Styled',
		'status'  => 'published',
		'content' => [ entityGuardBlock( 'a', 'color: red;' ) ],
	] ) );

	$this->putJson( "/visual-editor/api/posts/{$post->id}", [
		'content' => [ 'raw' => '', 'blocks' => [ entityGuardBlock( 'a', 'color: red;' ), entityGuardBlock( 'b', 'display: none;' ) ] ],
	] )->assertOk();

	$stored = $post->fresh()->getBlockContent();

	expect( $stored[0]['attributes']['artisanpackEffects']['customCss'] )->toBe( 'color: red;' )
		->and( $stored[1]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );

it( 'strips Custom CSS on a WP-shape entity create', function (): void {
	$id = $this->postJson( '/visual-editor/api/posts', [
		'title'   => 'New',
		'status'  => 'published',
		'content' => [ 'raw' => '', 'blocks' => [ entityGuardBlock( 'a', 'display: none;' ) ] ],
	] )->assertCreated()->json( 'id' );

	expect( TestBlockContentModel::query()->findOrFail( $id )->getBlockContent()[0]['attributes']['artisanpackEffects'] )->toBe( [ 'opacity' => 50 ] );
} );
