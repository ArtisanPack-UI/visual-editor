<?php

/**
 * H7 (#432) install-gate tests — exercises the standalone-install path
 * where cms-framework is autoloaded (require-dev) but its SiteEditor
 * provider has NOT been booted. Mirrors the per-controller standalone
 * tests (e.g. {@see TemplateControllerStandaloneTest}) that prove the
 * H6 surface 404s gracefully — H7 layers a route-level gate above
 * those 404s so the user lands on a single install instruction page
 * instead of a cascade of error banners from the SPA.
 *
 * The companion {@see InstallGateActiveTest} runs with cms-framework
 * booted and asserts the gate falls through to the SPA mount.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use Tests\TestCase;

uses( TestCase::class );

test( 'site-editor route renders the install gate when cms-framework is missing', function (): void {
	$response = $this->get( '/visual-editor/site' );

	$response->assertOk();
	$response->assertViewIs( 'visual-editor::site-editor.install-gate' );
	$response->assertViewHas( 'postEditorUrl' );
	$response->assertSee( 'Install cms-framework to enable the site editor' );
	$response->assertSee( 'composer require artisanpack-ui/cms-framework', false );
} );

test( 'install gate renders for nested SPA paths too', function (): void {
	// Sub-paths under /visual-editor/site (e.g. /styles/123) hit the
	// same catch-all route — the gate should fire for all of them so
	// users don't see the SPA mount with broken endpoints.
	$response = $this->get( '/visual-editor/site/styles/global-styles' );

	$response->assertOk();
	$response->assertViewIs( 'visual-editor::site-editor.install-gate' );
} );

test( 'install gate links back to the post editor', function (): void {
	$response = $this->get( '/visual-editor/site' );

	$response->assertOk();
	$response->assertSee( route( 'visual-editor.editor' ), false );
} );
