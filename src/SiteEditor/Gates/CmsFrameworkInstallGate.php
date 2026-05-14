<?php

/**
 * cms-framework install-gate — H7 (#432).
 *
 * Reusable {@see SiteEditorAccessGate} implementation consumers can
 * bind (directly or via composition) when they want the historical
 * behaviour: render the install-instructions page when cms-framework's
 * SiteEditor module is not on the classpath / not booted, otherwise
 * fall through to the SPA mount.
 *
 * Hosts that need an authorisation check on top — Keystone's admin-
 * role check, for example — typically wrap this gate in their own
 * implementation and run the install check first (so an unauthorised
 * visitor still sees the install page rather than a 403, which leaks
 * less about the deployment state).
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

use ArtisanPackUI\VisualEditor\SiteEditor\CmsFrameworkIntegration;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CmsFrameworkInstallGate implements SiteEditorAccessGate
{
	/**
	 * Allow the request when cms-framework's SiteEditor module is
	 * booted (the H6 resolver binding is bound). Otherwise short-
	 * circuit with the install-instructions page so the user gets a
	 * single actionable message instead of the SPA mounting and then
	 * receiving a cascade of 404s from every H6 controller.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The incoming site-editor request.
	 *
	 * @return Response|null Null when cms-framework is booted, the
	 *                      install-gate view otherwise.
	 */
	public function check( Request $request ): ?Response
	{
		if ( CmsFrameworkIntegration::isAvailable() ) {
			return null;
		}

		return response()->view(
			'visual-editor::site-editor.install-gate',
			[
				'postEditorUrl' => route( 'visual-editor.editor' ),
			]
		);
	}
}
