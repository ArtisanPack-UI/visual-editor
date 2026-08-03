<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\VisualEditorServiceProvider;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\TestAppliedTemplateModel;
use Tests\TestUser;

beforeEach( function () {
	config()->set( 'artisanpack.visual-editor.resources', [
		'pages' => TestAppliedTemplateModel::class,
	] );

	( new VisualEditorServiceProvider( app() ) )->registerResourceResolver();

	// Simple gate — any authenticated user can view/update. The endpoint
	// only needs `view`, but the policy shape mirrors the other resource
	// controllers.
	Gate::define( 'view', fn ( $user ) => null !== $user );
	Gate::define( 'update', fn ( $user ) => null !== $user );
} );

afterEach( function (): void {
	removeAllFilters( 'ap.visual-editor.templates' );
	removeAllFilters( 'ap.visual-editor.template-parts' );

	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
} );

function actingAsAppliedTemplateUser(): TestUser
{
	$user = TestUser::create( [
		'name'     => 'Applied Template Tester',
		'email'    => 'applied+' . uniqid() . '@example.com',
		'password' => bcrypt( 'secret' ),
	] );

	test()->actingAs( $user );

	return $user;
}

function registerSingleTemplate( string $slug, array $blocks = [], string $title = 'Single Post' ): void
{
	addFilter( 'ap.visual-editor.templates', function ( array $existing ) use ( $slug, $blocks, $title ): array {
		return array_merge( [
			$slug => [
				'slug'    => $slug,
				'theme'   => 'test-theme',
				'title'   => $title,
				'source'  => 'theme',
				'blocks'  => $blocks,
			],
		], $existing );
	} );

	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

function registerHeaderPart(): void
{
	addFilter( 'ap.visual-editor.template-parts', function ( array $existing ): array {
		return array_merge( [
			'header' => [
				'slug'   => 'header',
				'theme'  => 'test-theme',
				'title'  => 'Header',
				'area'   => 'header',
				'source' => 'theme',
				'blocks' => [
					[
						'name'        => 'core/site-title',
						'attributes'  => [],
						'innerBlocks' => [],
					],
				],
			],
		], $existing );
	} );

	( new VisualEditorServiceProvider( app() ) )->registerSiteEditorResolvers();
}

it( 'returns 401 for an unauthenticated request', function () {
	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Anon',
		'template' => 'single-post',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template" )
		->assertUnauthorized();
} );

it( 'returns 200 with status=missing/reason=empty when the model has no template set', function () {
	actingAsAppliedTemplateUser();
	registerSingleTemplate( 'single-post' );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'No template',
		'template' => null,
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template" )
		->assertOk()
		->assertJson( [ 'status' => 'missing', 'reason' => 'empty' ] );
} );

it( 'returns 200 with status=missing/reason=unknown-slug when the template does not resolve', function () {
	actingAsAppliedTemplateUser();
	registerSingleTemplate( 'single-post' );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Bad slug',
		'template' => 'does-not-exist',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template" )
		->assertOk()
		->assertJsonPath( 'status', 'missing' )
		->assertJsonPath( 'reason', 'unknown-slug' )
		->assertJsonPath( 'slug', 'does-not-exist' );
} );

it( 'returns the resolved template with referenced template-parts', function () {
	actingAsAppliedTemplateUser();
	registerHeaderPart();
	registerSingleTemplate( 'single-post', [
		[
			'name'       => 'core/template-part',
			'attributes' => [ 'slug' => 'header', 'theme' => 'test-theme' ],
			'innerBlocks' => [],
		],
		[
			'name'       => 'core/post-content',
			'attributes' => [],
			'innerBlocks' => [],
		],
	], 'Single Post' );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Real page',
		'template' => 'single-post',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template" )
		->assertOk()
		->assertJsonPath( 'status', 'ok' )
		->assertJsonPath( 'slug', 'single-post' )
		->assertJsonPath( 'name', 'Single Post' )
		->assertJsonPath( 'source', 'theme' )
		->assertJsonPath( 'blocks.0.name', 'core/template-part' )
		->assertJsonPath( 'blocks.1.name', 'core/post-content' )
		->assertJsonPath( 'template_parts.header.slug', 'header' )
		->assertJsonPath( 'template_parts.header.area', 'header' )
		->assertJsonPath( 'template_parts.header.blocks.0.name', 'core/site-title' );
} );

it( 'treats whitespace-only template values as empty', function () {
	actingAsAppliedTemplateUser();
	registerSingleTemplate( 'single-post' );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Whitespace',
		'template' => '   ',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template" )
		->assertOk()
		->assertJsonPath( 'reason', 'empty' );
} );

it( 'skips template-parts that cannot be resolved rather than failing', function () {
	actingAsAppliedTemplateUser();
	registerSingleTemplate( 'single-post', [
		[
			'name'       => 'core/template-part',
			'attributes' => [ 'slug' => 'missing-part' ],
			'innerBlocks' => [],
		],
	] );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Missing part',
		'template' => 'single-post',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template" )
		->assertOk()
		->assertJsonPath( 'template_parts', [] );
} );

it( 'resolves the ?template= override instead of the slug stored on the model', function () {
	actingAsAppliedTemplateUser();
	registerSingleTemplate( 'full-width', [], 'Full Width' );

	// The model still points at the *previous* template — the editor sends
	// the newly-selected slug before the debounced save has persisted it.
	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Switching templates',
		'template' => 'single-post',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template?template=full-width" )
		->assertOk()
		->assertJsonPath( 'status', 'ok' )
		->assertJsonPath( 'slug', 'full-width' )
		->assertJsonPath( 'name', 'Full Width' );
} );

it( 'reports unknown-slug for an override that does not resolve', function () {
	actingAsAppliedTemplateUser();
	registerSingleTemplate( 'single-post' );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Bad override',
		'template' => 'single-post',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template?template=nope" )
		->assertOk()
		->assertJsonPath( 'status', 'missing' )
		->assertJsonPath( 'reason', 'unknown-slug' )
		->assertJsonPath( 'slug', 'nope' );
} );

it( 'treats a blank ?template= override as a deliberate no-template selection', function () {
	actingAsAppliedTemplateUser();
	registerSingleTemplate( 'single-post' );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Cleared override',
		'template' => 'single-post',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template?template=" )
		->assertOk()
		->assertJsonPath( 'status', 'missing' )
		->assertJsonPath( 'reason', 'empty' );
} );

it( 'falls back to the model template when no override is supplied', function () {
	actingAsAppliedTemplateUser();
	registerSingleTemplate( 'single-post', [], 'Single Post' );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'No override',
		'template' => 'single-post',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template" )
		->assertOk()
		->assertJsonPath( 'slug', 'single-post' );
} );

it( 'still authorizes the override path against the view gate', function () {
	registerSingleTemplate( 'full-width', [], 'Full Width' );

	$page = TestAppliedTemplateModel::create( [
		'title'    => 'Anon override',
		'template' => 'single-post',
		'content'  => [],
	] );

	test()->getJson( "/visual-editor/api/pages/{$page->id}/applied-template?template=full-width" )
		->assertUnauthorized();
} );
