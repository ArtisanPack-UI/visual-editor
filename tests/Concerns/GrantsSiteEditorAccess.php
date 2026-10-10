<?php

/**
 * GrantsSiteEditorAccess — binds an allow-all site-editor access gate.
 *
 * The package default {@see \ArtisanPackUI\VisualEditor\SiteEditor\Gates\DenyByDefaultGate}
 * fails closed, and since 1.12.0 every site-editor data API write runs
 * through the bound gate (`EnsureSiteEditorAccess`). Tests that exercise
 * those writes as an authorised site editor `use` this trait; Laravel's
 * `setUpTraits()` calls {@see setUpGrantsSiteEditorAccess()} before each
 * test.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.12.0
 */

declare( strict_types=1 );

namespace Tests\Concerns;

use ArtisanPackUI\VisualEditor\SiteEditor\Gates\SiteEditorAccessGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait GrantsSiteEditorAccess
{
	/**
	 * Bind the allow-all gate before each test.
	 *
	 * @since 1.12.0
	 */
	protected function setUpGrantsSiteEditorAccess(): void
	{
		$this->grantSiteEditorAccess();
	}

	/**
	 * Bind a gate that lets every request through.
	 *
	 * @since 1.12.0
	 */
	protected function grantSiteEditorAccess(): void
	{
		$this->app->bind( SiteEditorAccessGate::class, fn (): SiteEditorAccessGate => new class implements SiteEditorAccessGate {
			public function check( Request $request ): ?Response
			{
				return null;
			}
		} );
	}

	/**
	 * Bind a gate that denies every request with a page response, the
	 * way a real host gate (or the deny-by-default gate) would.
	 *
	 * @since 1.12.0
	 */
	protected function denySiteEditorAccess(): void
	{
		$this->app->bind( SiteEditorAccessGate::class, fn (): SiteEditorAccessGate => new class implements SiteEditorAccessGate {
			public function check( Request $request ): ?Response
			{
				return response( 'denied', Response::HTTP_FORBIDDEN );
			}
		} );
	}
}
