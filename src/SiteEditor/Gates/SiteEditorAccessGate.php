<?php

/**
 * Site-editor access gate contract — H7 (#432).
 *
 * Consumers of the package implement this contract (and bind their
 * implementation to {@see SiteEditorAccessGate::class} in the
 * container) to control who reaches the `/visual-editor/site/{path?}`
 * SPA mount and what unauthorised visitors see instead.
 *
 * The package ships {@see DenyByDefaultGate} as the default binding —
 * a fail-closed gate that returns a 503 page on every request. A fresh
 * install of the package cannot accidentally expose the site editor.
 * Consumers MUST override the binding before the site editor is
 * reachable.
 *
 * Consumers that just want the historical behaviour (allow when
 * cms-framework's SiteEditor module is booted, render the install
 * instructions otherwise) can bind {@see CmsFrameworkInstallGate}
 * directly. CMS hosts that also need an authorisation check (e.g.
 * Keystone's admin-role check) typically compose the install gate with
 * their own role / capability check inside a thin app-side gate.
 *
 * See `docs/site-editor-access-gate.md` for the wiring guide.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Gates;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface SiteEditorAccessGate
{
	/**
	 * Decide whether the incoming request may reach the site-editor
	 * SPA mount.
	 *
	 * Return `null` to allow the request through. The route closure
	 * then renders the SPA mount view as normal.
	 *
	 * Return a {@see Response} to short-circuit the request — the
	 * route closure returns the response verbatim. This is the hook
	 * for install-gate pages, login redirects, 403 / 503 templates,
	 * or anything else the consumer wants to show in place of the
	 * editor.
	 *
	 * Implementations must not throw on the unauthenticated /
	 * unauthorised path — short-circuit with a {@see Response}
	 * instead so the user sees a useful page rather than a generic
	 * framework error.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The incoming site-editor request.
	 *
	 * @return Response|null Null to allow, Response to short-circuit.
	 */
	public function check( Request $request ): ?Response;
}
