<?php

/**
 * Result envelope for SVG sanitization.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Icon;

/**
 * The sanitizer always returns BOTH the cleaned markup and the list of
 * things it removed. Phase 5 (#556) surfaces the warnings inline in the
 * editor and Phase 6 (#557) shows them in the admin upload report, so
 * collapsing the result into a bare string would force every caller to
 * re-parse to figure out what was stripped.
 */
final class SvgSanitizationResult
{
	/**
	 * @param  string             $sanitized  Cleaned SVG markup (may be empty if the input was unsalvageable).
	 * @param  array<int, string> $warnings   Human-readable removals (e.g. "removed <script> tag").
	 */
	public function __construct(
		public readonly string $sanitized,
		public readonly array $warnings = [],
	) {
	}

	public function isEmpty(): bool
	{
		return '' === trim( $this->sanitized );
	}

	public function hasWarnings(): bool
	{
		return [] !== $this->warnings;
	}
}
