<?php

/**
 * GlobalStyles controller.
 *
 * Serves the REST surface for the `globalStyles` singleton behind the
 * B1 core-data shim (see `docs/core-data-shim.md` §Global styles). Four
 * endpoints — lookup, show, update, base — mount under
 * `/visual-editor/api/global-styles` via the package's auth-gated API
 * group.
 *
 * `lookup` resolves (or creates) the singleton record for the active
 * theme and returns just its id; it is what the D3 site-editor
 * bootstrap dispatches to `receiveCurrentGlobalStylesId`. `base`
 * returns the theme's default theme.json-shaped payload so the
 * site-editor can diff the user's record against it to highlight
 * customizations. `show` and `update` round-trip the user record
 * itself.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Http\Requests\UpdateGlobalStylesRequest;
use ArtisanPackUI\VisualEditor\Http\Resources\GlobalStylesResource;
use ArtisanPackUI\VisualEditor\Models\VisualEditorGlobalStyles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class GlobalStylesController extends Controller
{
	/**
	 * Returns `{ "id": <int> }` for the active theme's singleton record.
	 *
	 * The shim dispatches this once at bootstrap. If no record exists
	 * yet, one is created lazily with the package's default base
	 * payload — the site-editor can then start reading / writing
	 * immediately without a manual seeder step.
	 *
	 * @since 1.0.0
	 */
	public function lookup( Request $request ): JsonResponse
	{
		Gate::authorize( 'viewAny', VisualEditorGlobalStyles::class );

		$record = VisualEditorGlobalStyles::resolveSingleton(
			$this->activeTheme(),
			$this->basePayload()
		);

		return response()->json( [ 'id' => $record->getKey() ] );
	}

	/**
	 * Returns the user record. The shim expects the payload at the top
	 * level (not wrapped in `data`) so `fetchEntityRecord` can dispatch
	 * it straight into the cache.
	 *
	 * @since 1.0.0
	 */
	public function show( Request $request, VisualEditorGlobalStyles $globalStyle ): JsonResponse
	{
		Gate::authorize( 'view', $globalStyle );

		return response()->json(
			( new GlobalStylesResource( $globalStyle ) )->toArray( $request )
		);
	}

	/**
	 * Updates the user record with a validated theme.json payload.
	 *
	 * @since 1.0.0
	 */
	public function update( UpdateGlobalStylesRequest $request, VisualEditorGlobalStyles $globalStyle ): JsonResponse
	{
		Gate::authorize( 'update', $globalStyle );

		// Validation has already run (request is type-hinted). We pull
		// settings / styles from the raw input rather than from
		// validated() because theme.json is extensible and the
		// form-request only declares shape constraints for the keys we
		// care about enforcing — calling validated() would silently
		// drop any preset families or top-level keys the schema adds in
		// a minor revision (e.g. settings.layout, settings.spacing).
		$settings = $request->input( 'settings', [] );
		$styles   = $request->input( 'styles', [] );

		$globalStyle->fill( [
			'version'  => (int) $request->input( 'version' ),
			'settings' => is_array( $settings ) ? $settings : [],
			'styles'   => is_array( $styles ) ? $styles : [],
		] );
		$globalStyle->save();

		return response()->json(
			( new GlobalStylesResource( $globalStyle ) )->toArray( $request )
		);
	}

	/**
	 * Returns the theme's default (unmodified) theme.json payload.
	 *
	 * The site-editor style UI (D3) dispatches this to
	 * `receiveGlobalStylesBase` and compares it against the user
	 * record to show "what has been customized".
	 *
	 * @since 1.0.0
	 */
	public function base( Request $request ): JsonResponse
	{
		Gate::authorize( 'viewAny', VisualEditorGlobalStyles::class );

		return response()->json( $this->basePayload() );
	}

	/**
	 * Returns the active theme slug used to scope the singleton.
	 *
	 * @since 1.0.0
	 */
	protected function activeTheme(): string
	{
		$theme = config( 'artisanpack.visual-editor.global_styles.theme', 'artisanpack-base' );

		return is_string( $theme ) && '' !== $theme ? $theme : 'artisanpack-base';
	}

	/**
	 * Loads the default base payload — host-app override if configured,
	 * otherwise the package's bundled defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function basePayload(): array
	{
		$configured = config( 'artisanpack.visual-editor.global_styles.base_path' );
		$path       = is_string( $configured ) && '' !== $configured
			? $configured
			: __DIR__ . '/../../../resources/theme-json/default-base.php';

		if ( ! is_file( $path ) ) {
			return [
				'version'  => (int) config( 'artisanpack.visual-editor.global_styles.schema_version', 3 ),
				'settings' => [],
				'styles'   => [],
			];
		}

		$payload = require $path;

		return is_array( $payload ) ? $payload : [];
	}
}
