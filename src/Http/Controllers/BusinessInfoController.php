<?php

/**
 * BusinessInfoController — exposes the editor-facing business-info envelope
 * at `GET /visual-editor/api/business-info` for the four
 * `artisanpack/business-*` block previews (#761).
 *
 * The four business-info blocks (`business-hours`, `business-address`,
 * `business-phone`, `business-email`) all share one host-supplied envelope
 * resolved through the `ap.visualEditor.businessInfo` filter. On the front
 * end the Blade / React / Vue renderers stamp this envelope onto each block
 * via `BusinessInfoResolver::stampTree()`. In the editor, the same envelope
 * powers a WYSIWYG preview so authors see the real address / hours / phone
 * / email data — including the map iframe on the address block — instead of
 * a placeholder stub.
 *
 * The endpoint is a singleton (no id segment) and reuses the exact same
 * envelope-composition logic as the resolver so editor and front-end never
 * drift: the response is `$resolver->buildEnvelope()` with the address
 * block's default `mapEmbedUrl` composed and the hours block's default
 * special-hours window filter applied. Callers that want per-block
 * `showMap` / `mapProvider` / `specialHoursWindowDays` variants pass those
 * as query parameters and get the same overrides `stampTree()` would apply
 * on the front end.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\BusinessInfoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BusinessInfoController extends Controller
{
	public function __construct( protected BusinessInfoResolver $resolver )
	{
	}

	/**
	 * Return the singleton business-info envelope.
	 *
	 * Optional query parameters mirror the address / hours block
	 * attributes so the editor previews compose their map URL and
	 * special-hours window with the same rules `stampTree()` would apply
	 * on the front end:
	 *
	 *  - `mapProvider`             — `osm`, `google`, or `none`.
	 *  - `showMap`                 — `1` / `0` / `true` / `false`.
	 *  - `zoom`                    — 1-20 integer.
	 *  - `specialHoursWindowDays`  — positive integer.
	 *
	 * @since 1.9.0
	 */
	public function show( Request $request ): JsonResponse
	{
		$envelope = $this->resolver->buildEnvelope();

		$addressAttributes = $this->addressAttributesFromRequest( $request );
		$hoursAttributes   = $this->hoursAttributesFromRequest( $request );

		$envelope['mapEmbedUrl']  = $this->resolver->composeMapEmbedUrl( $envelope, $addressAttributes );
		$envelope['specialHours'] = $this->resolver->filterSpecialHoursWindow(
			$this->resolver->normalizeSpecialHours( $envelope['specialHours'] ?? [] ),
			$hoursAttributes
		);

		// Whitelist to the documented public shape so a host filter that
		// stuffs internal / private keys onto the envelope does not leak
		// them out of `/visual-editor/api/business-info`.
		$response = array_intersect_key( $envelope, array_flip( [
			'address',
			'phone',
			'email',
			'hours',
			'specialHours',
			'latitude',
			'longitude',
			'mapEmbedUrl',
		] ) );

		return response()->json( $response );
	}

	/**
	 * Extract the address-block attribute overrides from the request.
	 *
	 * Missing / malformed values fall through to the resolver's own
	 * defaults so the editor's default preview matches what the front
	 * end renders for an author who has not yet touched the inspector
	 * controls.
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, mixed>
	 */
	protected function addressAttributesFromRequest( Request $request ): array
	{
		$attributes = [];

		if ( $request->has( 'mapProvider' ) ) {
			$provider = (string) $request->query( 'mapProvider' );

			if ( in_array( $provider, [ 'osm', 'google', 'none' ], true ) ) {
				$attributes['mapProvider'] = $provider;
			}
		}

		if ( $request->has( 'showMap' ) ) {
			// Only assign when validation actually succeeds — passing
			// `null` for a garbage value ("garbage", "maybe") would
			// force the resolver to see a strict `null` and skip its
			// default of `true`, so a caller with a typo would silently
			// suppress the map. Falling through leaves `showMap`
			// unset here so `composeMapEmbedUrl` applies its default.
			$parsed = filter_var(
				$request->query( 'showMap' ),
				FILTER_VALIDATE_BOOLEAN,
				FILTER_NULL_ON_FAILURE
			);

			if ( null !== $parsed ) {
				$attributes['showMap'] = $parsed;
			}
		}

		if ( $request->has( 'zoom' ) ) {
			$zoom = (int) $request->query( 'zoom' );

			if ( $zoom > 0 ) {
				$attributes['zoom'] = $zoom;
			}
		}

		return $attributes;
	}

	/**
	 * Extract the hours-block attribute overrides from the request.
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, mixed>
	 */
	protected function hoursAttributesFromRequest( Request $request ): array
	{
		$attributes = [];

		if ( $request->has( 'specialHoursWindowDays' ) ) {
			$window = (int) $request->query( 'specialHoursWindowDays' );

			if ( $window > 0 ) {
				$attributes['specialHoursWindowDays'] = $window;
			}
		}

		return $attributes;
	}
}
