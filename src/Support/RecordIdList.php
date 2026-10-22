<?php

/**
 * Record id list parser.
 *
 * Parses the `include` query parameter the editor sends to batch title
 * lookups (`?include=11,12,21`) into a de-duplicated, capped list of
 * primary keys.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.16.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

class RecordIdList
{
	/**
	 * Parses a comma-separated string (or array) of record ids.
	 *
	 * Entries outside `[A-Za-z0-9_-]` are dropped so a malformed value
	 * can't reach the query. The result keeps first-seen order and is
	 * capped at `$max` entries.
	 *
	 * @since 1.16.0
	 *
	 * @param  mixed  $raw  The raw `include` input.
	 * @param  int    $max  The maximum number of ids to return.
	 *
	 * @return array<int, string>
	 */
	public static function parse( mixed $raw, int $max ): array
	{
		if ( is_string( $raw ) ) {
			$raw = explode( ',', $raw );
		}

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$ids = [];

		foreach ( $raw as $entry ) {
			if ( ! is_string( $entry ) && ! is_int( $entry ) ) {
				continue;
			}

			$id = trim( (string) $entry );

			if ( '' === $id || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $id ) ) {
				continue;
			}

			$ids[ $id ] = $id;

			if ( count( $ids ) >= $max ) {
				break;
			}
		}

		return array_values( $ids );
	}
}
