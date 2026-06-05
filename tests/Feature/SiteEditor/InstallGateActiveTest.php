<?php

/**
 * H7 (#432) install-gate companion — runs with cms-framework's
 * SiteEditor provider booted and asserts the gate at
 * `/visual-editor/site/{path?}` falls through to the SPA-mount blade.
 * Pairs with {@see InstallGateTest} (no cms-framework) which asserts
 * the gate fires.
 *
 * Both files explicitly bind {@see CmsFrameworkInstallGate} since the
 * H7 policy-contract refactor: the package default is now fail-closed
 * {@see DenyByDefaultGate}, and the install-gate behaviour is one of
 * several gates a consumer can opt into. See
 * {@see SiteEditorAccessGateTest} for default-binding coverage.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\CmsFrameworkInstallGate;
use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Tests\Concerns\WithCmsFramework;
use Tests\TestCase;

uses( TestCase::class, WithCmsFramework::class );

beforeEach( function (): void {
	$this->app->bind( SiteEditorAccessGate::class, CmsFrameworkInstallGate::class );
} );

test( 'site-editor route mounts the SPA when cms-framework is booted', function (): void {
	// `withoutVite` swaps the Vite handler for an empty stub so the SPA
	// mount blade doesn't fail looking for a built manifest under
	// Testbench's public path. We're asserting the route picks the SPA
	// view, not exercising Vite asset resolution.
	$this->withoutVite();

	$response = $this->get( '/visual-editor/site' );

	$response->assertOk();
	$response->assertViewIs( 'visual-editor::site-editor.index' );
	$response->assertSee( 'data-ap-site-editor', false );
} );

test( 'site-editor sub-paths also reach the SPA mount when cms-framework is booted', function (): void {
	$this->withoutVite();

	$response = $this->get( '/visual-editor/site/templates/single' );

	$response->assertOk();
	$response->assertViewIs( 'visual-editor::site-editor.index' );
} );
