<?php

/**
 * H7 (#432) site-editor access-gate contract tests.
 *
 * Covers the package-default fail-closed behaviour and the consumer-
 * override contract: a host app binds its own
 * {@see SiteEditorAccessGate} implementation in the container and the
 * site-editor route uses it verbatim — allowing the request through
 * (on null) or short-circuiting with the consumer's response.
 *
 * Paired with {@see InstallGateTest} / {@see InstallGateActiveTest},
 * which cover the bundled {@see CmsFrameworkInstallGate} specifically.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\DenyByDefaultGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses( TestCase::class );

test( 'package default binding is the fail-closed deny gate', function (): void {
	$resolved = $this->app->make( SiteEditorAccessGate::class );

	expect( $resolved )->toBeInstanceOf( DenyByDefaultGate::class );
} );

test( 'site-editor route returns a 503 deny page with the default gate', function (): void {
	$response = $this->get( '/visual-editor/site' );

	$response->assertStatus( Response::HTTP_SERVICE_UNAVAILABLE );
	$response->assertViewIs( 'visual-editor::site-editor.deny-by-default' );
	$response->assertSee( 'Site editor access has not been configured' );
} );

test( 'deny-by-default gate fires for nested SPA paths too', function (): void {
	$response = $this->get( '/visual-editor/site/templates/single' );

	$response->assertStatus( Response::HTTP_SERVICE_UNAVAILABLE );
	$response->assertViewIs( 'visual-editor::site-editor.deny-by-default' );
} );

test( 'a consumer-supplied gate returning null allows the request through', function (): void {
	$this->app->bind( SiteEditorAccessGate::class, function () {
		return new class implements SiteEditorAccessGate
		{
			public function check( Request $request ): ?Response
			{
				return null;
			}
		};
	} );

	$this->withoutVite();

	$response = $this->get( '/visual-editor/site' );

	$response->assertOk();
	$response->assertViewIs( 'visual-editor::site-editor.index' );
} );

test( 'a consumer-supplied gate returning a response short-circuits the route', function (): void {
	$this->app->bind( SiteEditorAccessGate::class, function () {
		return new class implements SiteEditorAccessGate
		{
			public function check( Request $request ): ?Response
			{
				return response( 'consumer-denied', Response::HTTP_FORBIDDEN );
			}
		};
	} );

	$response = $this->get( '/visual-editor/site' );

	$response->assertStatus( Response::HTTP_FORBIDDEN );
	expect( $response->getContent() )->toBe( 'consumer-denied' );
} );

test( 'the gate receives the incoming request', function (): void {
	$capturedPath = null;

	$this->app->bind( SiteEditorAccessGate::class, function () use ( &$capturedPath ) {
		return new class( $capturedPath ) implements SiteEditorAccessGate
		{
			public function __construct( public ?string &$captured ) {}

			public function check( Request $request ): ?Response
			{
				$this->captured = $request->path();

				return response( 'ok' );
			}
		};
	} );

	$this->get( '/visual-editor/site/templates/single' );

	expect( $capturedPath )->toBe( 'visual-editor/site/templates/single' );
} );
