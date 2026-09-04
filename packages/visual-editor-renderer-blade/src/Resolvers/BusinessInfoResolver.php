<?php

/**
 * Business-info resolver for the Blade renderer (#761).
 *
 * Walks a saved block tree and stamps `_resolvedBusinessInfo` onto every
 * business-info display block — `artisanpack/business-hours`,
 * `artisanpack/business-address`, `artisanpack/business-phone`, and
 * `artisanpack/business-email` — so the Blade / React / Vue partials can
 * render the host's contact data without each renderer knowing how to
 * source it.
 *
 * The data comes from the host through the `ap.visualEditor.businessInfo`
 * filter, which the resolver applies once per stampTree() call and then
 * memoizes so a tree that carries multiple business-info blocks pays the
 * filter cost exactly once. Hosts return an array shaped like:
 *
 *     [
 *         'address'      => [
 *             'street'      => '123 Main St',
 *             'street2'     => 'Suite 100',
 *             'city'        => 'Springfield',
 *             'region'      => 'IL',
 *             'postal_code' => '62701',
 *             'country'     => 'US',
 *         ],
 *         'phone'        => '+1 555-123-4567',
 *         'email'        => 'hello@example.test',
 *         'hours'        => [ 'monday' => [ 'open' => '09:00', 'close' => '17:00' ], ... ],
 *         'specialHours' => [ [ 'date' => '2025-12-25', 'closed' => true, 'label' => 'Christmas' ], ... ],
 *         'latitude'     => 39.7817,
 *         'longitude'    => -89.6501,
 *         'mapEmbedUrl'  => null, // optional; overrides the resolver's OSM/Google default
 *     ]
 *
 * For `artisanpack/business-address` blocks the resolver additionally
 * composes a final `mapEmbedUrl` from the block's `mapProvider` /
 * `showMap` / `zoom` attributes, the host's `mapEmbedUrl` /
 * `latitude`/`longitude` / address fields, and the
 * `artisanpack.visual-editor.business.google_maps_api_key` config — so
 * the renderers only see a single ready-to-embed URL (or null).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Resolvers;

class BusinessInfoResolver
{
	/**
	 * Block names this resolver stamps. Any other block is walked through
	 * so nested cases (e.g. inside a `core/group`) still resolve, but its
	 * attributes are left untouched.
	 *
	 * @var array<int, string>
	 */
	public const BUSINESS_BLOCKS = [
		'artisanpack/business-hours',
		'artisanpack/business-address',
		'artisanpack/business-phone',
		'artisanpack/business-email',
	];

	/**
	 * Memoized envelopes returned by the `ap.visualEditor.businessInfo`
	 * filter for the current stampTree() call, keyed by post identity
	 * (`spl_object_id()`) so a resolver instance walking one tree that
	 * renders multiple posts (e.g. a landing-page loop over location
	 * posts, each with its own business info) does not serve every
	 * post the first post's envelope. Reset on each top-level entry
	 * so a long-lived worker (Octane / queue) doesn't carry stale
	 * host data across requests.
	 *
	 * @var array<int|string, array<string, mixed>>
	 */
	protected array $cached = [];

	/**
	 * Recursively walk a block subtree and stamp `_resolvedBusinessInfo`
	 * on every business-info block. Non-business blocks are walked through
	 * so nested cases (a business block inside a group / columns / etc.)
	 * still resolve.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  object|null                       $post  Current post context, forwarded to the filter so hosts can vary the envelope per entity.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function stampTree( array $tree, ?object $post = null ): array
	{
		// Reset the per-call memo so a worker-runtime host does not leak
		// an envelope from one request into another.
		$this->cached = [];

		return $this->stampSubtree( $tree, $post );
	}

	/**
	 * Recursive helper — separated from stampTree() so the per-call cache
	 * reset happens exactly once at the top of a walk.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function stampSubtree( array $tree, ?object $post ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out[] = $this->stampBlock( $block, $post );
		}

		return $out;
	}

	/**
	 * Stamp a single block (and recurse into its inner blocks). Only the
	 * four business-info block names get a `_resolvedBusinessInfo` bag;
	 * everything else is forwarded with its inner tree walked through.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	public function stampBlock( array $block, ?object $post = null ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] )
			? $block['attributes']
			: [];

		if ( in_array( $name, self::BUSINESS_BLOCKS, true ) ) {
			// A pre-stamped `_resolvedBusinessInfo` on the host's saved
			// tree wins over the resolver fallback — same contract as
			// BreadcrumbsResolver so a host that has resolved the
			// envelope upstream keeps full control. We still run the
			// special-hours normalize + window filter over its
			// `specialHours` so malformed dates / past holidays don't
			// reach the renderer (which would throw on the sprintf
			// path). `mapEmbedUrl` and `address` are left untouched —
			// those are the host's explicit override.
			if ( ! array_key_exists( '_resolvedBusinessInfo', $attributes ) ) {
				$envelope = $this->buildEnvelope( $post );

				if ( 'artisanpack/business-address' === $name ) {
					$envelope['mapEmbedUrl'] = $this->composeMapEmbedUrl( $envelope, $attributes );
				}

				if ( 'artisanpack/business-hours' === $name ) {
					$envelope['specialHours'] = $this->filterSpecialHoursWindow(
						$this->normalizeSpecialHours( $envelope['specialHours'] ?? [] ),
						$attributes
					);
				}

				$attributes['_resolvedBusinessInfo'] = $envelope;
			} elseif ( is_array( $attributes['_resolvedBusinessInfo'] ) ) {
				$preStamped = $attributes['_resolvedBusinessInfo'];

				if ( array_key_exists( 'specialHours', $preStamped ) ) {
					$preStamped['specialHours'] = $this->filterSpecialHoursWindow(
						$this->normalizeSpecialHours( $preStamped['specialHours'] ),
						$attributes
					);

					$attributes['_resolvedBusinessInfo'] = $preStamped;
				}
			}
		}

		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
			? $this->stampSubtree( $block['innerBlocks'], $post )
			: [];

		return array_merge( $block, [
			'attributes'  => $attributes,
			'innerBlocks' => $inner,
		] );
	}

	/**
	 * Resolve the host-supplied business-info envelope through the
	 * `ap.visualEditor.businessInfo` filter, memoized per stampTree()
	 * call. Non-array returns from the filter fall back to an empty
	 * envelope so a mistaken `null` / `false` return doesn't blank the
	 * renderer.
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, mixed>
	 */
	public function buildEnvelope( ?object $post = null ): array
	{
		$key = null === $post ? '__null__' : spl_object_id( $post );

		if ( array_key_exists( $key, $this->cached ) ) {
			return $this->cached[ $key ];
		}

		$envelope = $this->defaults();

		if ( function_exists( 'applyFilters' ) ) {
			$filtered = applyFilters( 'ap.visualEditor.businessInfo', $envelope, $post );

			if ( is_array( $filtered ) ) {
				$envelope = array_merge( $envelope, $filtered );
			}
		}

		$this->cached[ $key ] = $envelope;

		return $envelope;
	}

	/**
	 * The empty-shape default envelope. Every top-level key is present so
	 * the renderers can rely on their existence and only branch on
	 * emptiness. Address is a nested associative array with its own
	 * empty defaults.
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, mixed>
	 */
	protected function defaults(): array
	{
		return [
			'address'      => [
				'street'      => '',
				'street2'     => '',
				'city'        => '',
				'region'      => '',
				'postal_code' => '',
				'country'     => '',
			],
			'phone'        => '',
			'email'        => '',
			'hours'        => [],
			'specialHours' => [],
			'latitude'     => null,
			'longitude'    => null,
			'mapEmbedUrl'  => null,
		];
	}

	/**
	 * Compose the final map embed URL for an address block. Precedence:
	 *
	 *  1. Block's `mapProvider` set to `none`, or `showMap` set to false → null.
	 *  2. Host-supplied `mapEmbedUrl` on the envelope → passthrough.
	 *  3. Block's `mapProvider` = `google` AND a Google Maps API key is
	 *     configured → Google Maps `/maps/embed/v1/place` URL.
	 *  4. Otherwise → OpenStreetMap `/export/embed.html` URL with a
	 *     bounding box centred on the address's lat/lng. If no
	 *     coordinates were supplied for the OSM branch, returns null —
	 *     OSM's `/search` page is HTML meant for humans and doesn't
	 *     render inside an iframe.
	 *
	 * Returns null when no URL can be composed — the renderer then skips
	 * the map iframe entirely.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, mixed>  $envelope
	 * @param  array<string, mixed>  $attributes
	 */
	public function composeMapEmbedUrl( array $envelope, array $attributes ): ?string
	{
		$showMap = (bool) ( $attributes['showMap'] ?? true );

		if ( true !== $showMap ) {
			return null;
		}

		$provider = isset( $attributes['mapProvider'] ) && is_string( $attributes['mapProvider'] )
			? strtolower( $attributes['mapProvider'] )
			: 'osm';

		if ( 'none' === $provider ) {
			return null;
		}

		$hostSupplied = $envelope['mapEmbedUrl'] ?? null;

		if ( is_string( $hostSupplied ) && '' !== trim( $hostSupplied ) ) {
			return $hostSupplied;
		}

		$zoom = isset( $attributes['zoom'] ) && is_int( $attributes['zoom'] ) && $attributes['zoom'] > 0
			? $attributes['zoom']
			: 15;

		$latitude  = $this->numericOrNull( $envelope['latitude'] ?? null );
		$longitude = $this->numericOrNull( $envelope['longitude'] ?? null );

		$googleKey = config( 'artisanpack.visual-editor.business.google_maps_api_key' );

		if ( 'google' === $provider && is_string( $googleKey ) && '' !== trim( $googleKey ) ) {
			$query = $this->buildAddressQuery( $envelope, $latitude, $longitude );

			if ( '' === $query ) {
				return null;
			}

			return sprintf(
				'https://www.google.com/maps/embed/v1/place?key=%s&q=%s&zoom=%d',
				rawurlencode( trim( $googleKey ) ),
				rawurlencode( $query ),
				$zoom
			);
		}

		// OSM fallback — the keyless `/export/embed.html` endpoint. It
		// needs a bounding box, so we synthesise one around the lat/lng
		// when supplied. Without coordinates, OSM has no valid iframe
		// route (its `/search` page is HTML meant for humans, and
		// browsers won't render it as a map inside an iframe) — return
		// null so the renderers skip the iframe entirely rather than
		// stamping a broken embed URL.
		if ( null === $latitude || null === $longitude ) {
			return null;
		}

		// Bounding-box delta — narrower at higher zoom. A crude but stable
		// approximation that keeps the pin near the centre of the frame
		// without hitting Nominatim for a real bbox.
		$delta = max( 0.001, 0.05 / max( 1, $zoom - 8 ) );
		$west  = $longitude - $delta;
		$east  = $longitude + $delta;
		$south = $latitude - $delta;
		$north = $latitude + $delta;

		return sprintf(
			'https://www.openstreetmap.org/export/embed.html?bbox=%s%%2C%s%%2C%s%%2C%s&layer=mapnik&marker=%s%%2C%s',
			$this->formatCoord( $west ),
			$this->formatCoord( $south ),
			$this->formatCoord( $east ),
			$this->formatCoord( $north ),
			$this->formatCoord( $latitude ),
			$this->formatCoord( $longitude )
		);
	}

	/**
	 * Build a human-readable single-line query string suitable for a map
	 * provider's search endpoint. Prefers coordinates when both are
	 * supplied, then falls back to the address string.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, mixed>  $envelope
	 */
	protected function buildAddressQuery( array $envelope, ?float $latitude, ?float $longitude ): string
	{
		if ( null !== $latitude && null !== $longitude ) {
			return sprintf( '%s,%s', $this->formatCoord( $latitude ), $this->formatCoord( $longitude ) );
		}

		$address = isset( $envelope['address'] ) && is_array( $envelope['address'] )
			? $envelope['address']
			: [];

		$parts = [];

		foreach ( [ 'street', 'street2', 'city', 'region', 'postal_code', 'country' ] as $key ) {
			$value = $address[ $key ] ?? '';

			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$parts[] = trim( (string) $value );
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Format a float coordinate with fixed precision so the resulting URL
	 * is stable across PHP locale changes (`sprintf( '%f', … )` is locale
	 * sensitive on some platforms).
	 *
	 * @since 1.9.0
	 */
	protected function formatCoord( float $value ): string
	{
		return number_format( $value, 6, '.', '' );
	}

	/**
	 * Coerce a mixed value to a float or null. Accepts ints, floats, and
	 * numeric strings; anything else returns null so the URL composer can
	 * fall back to the address-string path.
	 *
	 * @since 1.9.0
	 */
	protected function numericOrNull( mixed $value ): ?float
	{
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}

		if ( is_string( $value ) && '' !== trim( $value ) && is_numeric( $value ) ) {
			return (float) $value;
		}

		return null;
	}

	/**
	 * Normalise special-hours entries: each item must be an array with a
	 * `date` key parseable as `YYYY-MM-DD`. Entries that don't parse are
	 * dropped so a host filter can't crash the renderer with malformed
	 * data.
	 *
	 * @since 1.9.0
	 *
	 * @param  mixed  $specialHours
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function normalizeSpecialHours( mixed $specialHours ): array
	{
		if ( ! is_array( $specialHours ) ) {
			return [];
		}

		$out = [];

		foreach ( $specialHours as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$date = $entry['date'] ?? null;

			if ( ! is_string( $date ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
				continue;
			}

			// The regex proves the shape; `checkdate()` proves the calendar
			// (rejects Feb 31, month 13, day 0, etc.) so a host filter with
			// a typo can't crash the downstream `strtotime()` / sprintf.
			if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
				continue;
			}

			$out[] = $entry;
		}

		return $out;
	}

	/**
	 * Filter the given special-hours list down to entries that fall
	 * within the block's configured window (default: 30 days ahead of
	 * "today"). Entries in the past are always dropped.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<int, array<string, mixed>>  $specialHours
	 * @param  array<string, mixed>              $attributes
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function filterSpecialHoursWindow( array $specialHours, array $attributes ): array
	{
		$windowDays = isset( $attributes['specialHoursWindowDays'] ) && is_int( $attributes['specialHoursWindowDays'] ) && $attributes['specialHoursWindowDays'] > 0
			? $attributes['specialHoursWindowDays']
			: 30;

		$today   = strtotime( 'today' );
		$horizon = strtotime( sprintf( '+%d days', $windowDays ), $today );

		if ( false === $today || false === $horizon ) {
			return $specialHours;
		}

		$out = [];

		foreach ( $specialHours as $entry ) {
			$date = $entry['date'] ?? '';

			if ( ! is_string( $date ) ) {
				continue;
			}

			$stamp = strtotime( $date );

			if ( false === $stamp ) {
				continue;
			}

			if ( $stamp < $today || $stamp > $horizon ) {
				continue;
			}

			$out[] = $entry;
		}

		return $out;
	}
}
