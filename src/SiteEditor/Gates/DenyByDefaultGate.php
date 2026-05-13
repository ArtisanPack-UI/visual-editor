<?php

/**
 * Fail-closed default site-editor access gate — H7 (#432).
 *
 * Returns a 503 view on every request. Bound as the package default
 * for {@see SiteEditorAccessGate} so a fresh install of the visual-
 * editor package cannot expose the site editor without the consuming
 * app explicitly opting in.
 *
 * Consumers replace the binding with one of the bundled gates
 * ({@see CmsFrameworkInstallGate}) or their own implementation —
 * see `docs/site-editor-access-gate.md`.
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

class DenyByDefaultGate implements SiteEditorAccessGate
{
	/**
	 * Short-circuit with a 503 view explaining the gate has not been
	 * configured. The status code is deliberate: this is a deployment
	 * misconfiguration (no consumer-supplied gate is bound), not an
	 * authorisation decision about a specific user.
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The incoming site-editor request.
	 *
	 * @return Response|null Always returns the 503 view response —
	 *                      never null — but the signature is nullable
	 *                      to satisfy {@see SiteEditorAccessGate}.
	 */
	public function check( Request $request ): ?Response
	{
		return response()->view(
			'visual-editor::site-editor.deny-by-default',
			[],
			Response::HTTP_SERVICE_UNAVAILABLE
		);
	}
}
