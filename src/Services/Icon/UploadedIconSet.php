<?php

/**
 * Value object describing a host-uploaded icon set.
 *
 * Phase 6 (#557) of the Icon Block feature (#494). Admins upload zips
 * of licensed SVGs (e.g. Font Awesome Pro) through the settings screen;
 * each persisted set is represented as one of these structs and stored
 * alongside the SVG files in `storage/app/artisanpack/visual-editor/icons/`.
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
 * Plain DTO — kept dumb on purpose. The registry handles persistence,
 * the uploader handles storage layout, and the catalog reads these to
 * surface uploaded sets in the picker. Anything that needs to be a
 * computed property (e.g. the absolute path, derived from the prefix
 * and the registry's base directory) lives on the registry, not here.
 */
final class UploadedIconSet
{
	/**
	 * @param  string $prefix     Lowercase, kebab-cased identifier (e.g. `fa-pro`).
	 *                            Doubles as the directory name under the registry base.
	 * @param  string $label      Human-facing name shown in the picker chip strip.
	 * @param  string $createdAt  ISO-8601 timestamp recorded on first upload.
	 */
	public function __construct(
		public readonly string $prefix,
		public readonly string $label,
		public readonly string $createdAt,
	) {
	}

	/**
	 * Build a DTO from a persisted manifest row. Throws when any of the
	 * required keys are missing or blank so a corrupt manifest surfaces
	 * loudly at the call site instead of producing a half-filled DTO
	 * the catalog would later try to walk. {@see UploadedIconSetRegistry::all()}
	 * wraps this in try/catch so a single bad row doesn't kill the whole
	 * boot-time registration loop — the offending row is silently
	 * dropped and the admin can fix it from the settings screen.
	 *
	 * @param  array<string, mixed> $data
	 */
	public static function fromArray( array $data ): self
	{
		$trimmed = [];
		foreach ( [ 'prefix', 'label', 'created_at' ] as $field ) {
			$value = isset( $data[ $field ] ) ? trim( (string) $data[ $field ] ) : '';
			if ( '' === $value ) {
				throw new \InvalidArgumentException(
					"UploadedIconSet manifest row is missing or blank '{$field}'.",
				);
			}
			$trimmed[ $field ] = $value;
		}

		return new self( $trimmed['prefix'], $trimmed['label'], $trimmed['created_at'] );
	}

	/**
	 * @return array{prefix: string, label: string, created_at: string}
	 */
	public function toArray(): array
	{
		return [
			'prefix'     => $this->prefix,
			'label'      => $this->label,
			'created_at' => $this->createdAt,
		];
	}
}
