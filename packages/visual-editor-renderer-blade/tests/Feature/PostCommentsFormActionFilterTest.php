<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

/**
 * cms-framework#245 — the `post-comments-form` block is the only fire site
 * for the comment form's action filter, but the filter is namespaced under
 * `ap.cmsFramework.*` because comments are that package's domain. The name
 * was un-prefixed before 2.8.0 and the fire site had never been covered, so
 * nothing would have caught the rename silently dropping a host's override.
 */

afterEach( function (): void {
	removeAllFilters( 'comments.form.action' );
	removeAllFilters( 'ap.cmsFramework.comments.form.action' );
} );

it( 'posts to the cms-framework comments endpoint by default', function (): void {
	$html = app( BlockRenderer::class )->renderBlock( [
		'name'       => 'artisanpack/post-comments-form',
		'attributes' => [],
	] );

	expect( $html )->toContain( 'action="/api/v1/comments"' );
} );

it( 'lets a host redirect the form action through the namespaced filter', function (): void {
	addFilter( 'ap.cmsFramework.comments.form.action', static fn (): string => '/comments' );

	$html = app( BlockRenderer::class )->renderBlock( [
		'name'       => 'artisanpack/post-comments-form',
		'attributes' => [],
	] );

	expect( $html )->toContain( 'action="/comments"' );
	expect( $html )->not->toContain( 'action="/api/v1/comments"' );
} );

it( 'still honors a host subscribed under the pre-2.8 un-prefixed name', function (): void {
	addFilter( 'comments.form.action', static fn (): string => '/comments' );

	$html = app( BlockRenderer::class )->renderBlock( [
		'name'       => 'artisanpack/post-comments-form',
		'attributes' => [],
	] );

	expect( $html )->toContain( 'action="/comments"' );
} );
