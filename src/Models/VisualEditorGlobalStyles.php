<?php

/**
 * VisualEditorGlobalStyles model.
 *
 * Singleton backing for the `globalStyles` entity exposed at
 * `/visual-editor/api/global-styles`. Stores a theme.json-shaped
 * `{ version, settings, styles }` blob — `settings` is the palette /
 * typography / layout presets, `styles` is the resolved CSS values
 * (page-level plus per-element and per-block overrides). The record is
 * scoped by `theme` so the singleton invariant is per-theme, not
 * per-site: a host app switching themes gets a separate record rather
 * than silently inheriting a different theme's customizations.
 *
 * The `resolveSingleton()` helper is the single source of truth for
 * "which record does `GET /lookup` return" — it reads the active theme
 * from config, finds the matching row, and creates one seeded with the
 * default `base` payload if it does not yet exist. This keeps the
 * `lookup` endpoint idempotent without a manual seeder.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Models;

use Illuminate\Database\Eloquent\Model;

class VisualEditorGlobalStyles extends Model
{
	protected $table = 'visual_editor_global_styles';

	/**
	 * @var array<int, string>
	 */
	protected $fillable = [
		'version',
		'settings',
		'styles',
		'theme',
	];

	/**
	 * @var array<string, string>
	 */
	protected $casts = [
		'version'  => 'int',
		'settings' => 'array',
		'styles'   => 'array',
	];

	/**
	 * Resolves (or creates) the singleton record for the given theme.
	 *
	 * The record is lazily created the first time it is requested so a
	 * fresh install, a freshly migrated test database, or a new theme
	 * all land on an endpoint that returns a real id instead of 404ing
	 * into the site-editor bootstrap. The seed payload comes from the
	 * package's default base (or the host app's override) so a brand
	 * new site starts with the same defaults the `/base` endpoint
	 * returns.
	 *
	 * The write path relies on Laravel's race-safe `firstOrCreate`
	 * (which catches `UniqueConstraintViolationException` and re-queries
	 * on conflict) plus the unique index on `theme` at the DB level. Two
	 * concurrent cold-start `lookup` requests cannot both succeed with
	 * different ids for the same theme — the loser's insert is converted
	 * back into a read of the winner's row.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $defaults  Seed payload for first-time creation.
	 *                                          Must carry `version`, `settings`, `styles`.
	 */
	public static function resolveSingleton( string $theme, array $defaults ): self
	{
		return self::firstOrCreate(
			[ 'theme' => $theme ],
			[
				'version'  => isset( $defaults['version'] ) ? (int) $defaults['version'] : 3,
				'settings' => isset( $defaults['settings'] ) && is_array( $defaults['settings'] ) ? $defaults['settings'] : [],
				'styles'   => isset( $defaults['styles'] ) && is_array( $defaults['styles'] ) ? $defaults['styles'] : [],
			]
		);
	}
}
