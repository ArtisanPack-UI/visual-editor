<?php

/**
 * Translations for the editor's `artisanpack-visual-editor` text domain.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

/**
 * Resolves the Jed locale data the editor installs for its text domain
 * before it loads (#896).
 *
 * Hosts publish a Jed 1.x JSON file per locale at
 * `lang/vendor/visual-editor/{locale}.json`, as `po2json -f jed1.x`
 * produces it from a translated `.po` (the shape `wp i18n make-json`
 * writes is accepted too). The mounts
 * (`<x-visual-editor>`, the `/editor/mount` view and the site editor)
 * stamp the result as `data-locale-data`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.1
 */
class EditorLocaleData
{
	/**
	 * Filter hosts hook to supply or adjust the locale data. Receives the
	 * resolved data (or `null`) and the locale; return `null` for none.
	 *
	 * @since 1.15.1
	 */
	public const FILTER = 'ap.visualEditor.localeData';

	/**
	 * The editor's text domain, as `vendor/i18n.ts` registers it.
	 *
	 * @since 1.15.1
	 */
	public const TEXT_DOMAIN = 'artisanpack-visual-editor';

	/**
	 * Where the locale files live, relative to `lang_path()`.
	 *
	 * @since 1.15.1
	 */
	public const DIRECTORY = 'vendor/visual-editor';

	/**
	 * The locale data for a locale (default: the app locale), or `null`
	 * when no file resolves.
	 *
	 * Tries the locale as given, then its language alone (`fr_CA` →
	 * `fr`), then the app's fallback locale, and normalises the first
	 * file that decodes to a Jed payload.
	 *
	 * @since 1.15.1
	 *
	 * @return array<string, mixed>|null
	 */
	public static function resolve( ?string $locale = null ): ?array
	{
		$locale = $locale ?? (string) app()->getLocale();
		$data   = null;

		foreach ( self::candidates( $locale ) as $candidate ) {
			$path = lang_path( self::DIRECTORY . '/' . $candidate . '.json' );

			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$data = self::normalise( json_decode( (string) file_get_contents( $path ), true ) );

			if ( null !== $data ) {
				break;
			}
		}

		$filtered = applyFilters( self::FILTER, $data, $locale );

		return is_array( $filtered ) ? self::normalise( $filtered ) : null;
	}

	/**
	 * Read a Jed payload as the bare locale data `setLocaleData()` takes:
	 * a Jed 1.x file (`{ locale_data: { {domain}: … } }`, as `po2json` and
	 * `wp i18n make-json` write it) or
	 * the locale data itself (`{ "": { … }, "Save": [ "…" ] }`). Returns
	 * `null` for anything else.
	 *
	 * @since 1.15.1
	 *
	 * @return array<string, mixed>|null
	 */
	public static function normalise( mixed $payload ): ?array
	{
		if ( ! is_array( $payload ) ) {
			return null;
		}

		if ( is_array( $payload['locale_data'] ?? null ) ) {
			$domains = $payload['locale_data'];
			$payload = $domains[ self::TEXT_DOMAIN ] ?? $domains['messages'] ?? reset( $domains );
		}

		if ( ! is_array( $payload ) || [] === $payload ) {
			return null;
		}

		$messages = [];

		foreach ( $payload as $key => $value ) {
			if ( '' === $key ) {
				if ( is_array( $value ) ) {
					$messages[''] = $value;
				}

				continue;
			}

			// PHP turns a numeric msgid (`"404"`) into an int key.
			if ( ! is_array( $value ) ) {
				continue;
			}

			$translations = array_values( array_filter( $value, 'is_string' ) );

			if ( [] !== $translations ) {
				$messages[ (string) $key ] = $translations;
			}
		}

		return [] === array_diff_key( $messages, [ '' => true ] ) ? null : $messages;
	}

	/**
	 * Locales to try, most specific first, without repeats.
	 *
	 * @since 1.15.1
	 *
	 * @return list<string>
	 */
	protected static function candidates( string $locale ): array
	{
		$candidates = [];

		foreach ( [ $locale, preg_split( '/[-_]/', $locale )[0] ?? '', (string) config( 'app.fallback_locale', '' ) ] as $candidate ) {
			$candidate = trim( $candidate );

			// Locale codes only — never a path segment.
			if ( 1 === preg_match( '/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', $candidate ) ) {
				$candidates[] = $candidate;
			}
		}

		return array_values( array_unique( $candidates ) );
	}
}
