<?php

/**
 * Per-file outcome envelope for the admin icon-set upload pipeline.
 *
 * Phase 6 (#557) of the Icon Block feature (#494). The settings screen
 * surfaces a row-by-row report after a zip upload so admins can see
 * what landed, what was skipped (non-SVG entries), and what failed
 * sanitization with the corresponding warnings.
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
 * Returned by {@see IconSetUploader::upload()}. Callers use the typed
 * arrays directly — there is no behaviour on this struct beyond
 * serialisation for the JSON response.
 */
final class IconSetUploadResult
{
	/**
	 * @param  array<int, string>                                $stored   File names that were sanitized and written under the set directory.
	 * @param  array<int, string>                                $skipped  Zip entries dropped before sanitization (non-SVG extension, empty payload, traversal attempt).
	 * @param  array<int, array{file: string, warnings: array<int, string>}> $failed   Entries the sanitizer reduced to empty markup. The warnings array is the sanitizer's removal list.
	 */
	public function __construct(
		public readonly array $stored = [],
		public readonly array $skipped = [],
		public readonly array $failed = [],
	) {
	}

	public function totalProcessed(): int
	{
		return count( $this->stored ) + count( $this->skipped ) + count( $this->failed );
	}

	/**
	 * @return array{
	 *     stored: array<int, string>,
	 *     skipped: array<int, string>,
	 *     failed: array<int, array{file: string, warnings: array<int, string>}>
	 * }
	 */
	public function toArray(): array
	{
		return [
			'stored'  => $this->stored,
			'skipped' => $this->skipped,
			'failed'  => $this->failed,
		];
	}
}
