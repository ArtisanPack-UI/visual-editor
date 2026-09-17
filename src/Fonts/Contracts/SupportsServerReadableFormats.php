<?php

/**
 * SupportsServerReadableFormats contract.
 *
 * Optional companion to {@see FontProvider} for providers that can serve
 * TTF/OTF alongside their default web-optimized format (usually WOFF2).
 *
 * The browser-side site editor is happy with WOFF2 — smaller downloads,
 * excellent cross-browser support — but **server-side renderers can't
 * read it**. GD's FreeType binding, most PDF libraries (Dompdf, Mpdf),
 * and every image/OG generator we've touched only understand TTF/OTF.
 * Downstream #794 lays out the concrete case: `artisanpack-ui/seo`'s
 * OG image generator picks up an installed WOFF2-only family, GD's
 * `imagettftext` fails silently, and the card ships with no text on it.
 *
 * A provider that implements this contract tells {@see FontInstaller}
 * which server-readable formats it can supply, and the installer fetches
 * each one alongside the default face and persists a sibling
 * {@see \ArtisanPackUI\VisualEditor\Fonts\Models\FontFace} row per format.
 * Server-side consumers query `->faces()->whereIn('format', ['ttf', 'otf'])`
 * (or equivalent) and get a real path they can hand to their renderer.
 *
 * Failures fetching a supplementary format do **not** fail the install —
 * a missing TTF is a soft-fail: the install proceeds with only its
 * primary WOFF2 face, and the installer logs the miss so an operator can
 * see why a server-side render fell back to a bitmap font later.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.11.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Contracts;

interface SupportsServerReadableFormats
{
	/**
	 * List the server-readable formats this provider can supply
	 * alongside its default {@see FontProvider::fetchFace()} output.
	 *
	 * Returned values are lowercase file-format tokens matching what
	 * {@see \ArtisanPackUI\VisualEditor\Fonts\Models\FontFace::$format}
	 * stores — typically `'ttf'` and/or `'otf'`. Return `[]` to opt
	 * out at runtime (e.g. when a provider is configured against an
	 * endpoint that only serves WOFF2).
	 *
	 * @since 1.11.0
	 *
	 * @return list<string>
	 */
	public function serverReadableFormats(): array;

	/**
	 * Fetch the same face as {@see FontProvider::fetchFace()} but in the
	 * given `$format`. Throws when the provider can't serve that format
	 * for that face — the installer catches the throw, logs it, and
	 * continues with whatever formats did resolve.
	 *
	 * @since 1.11.0
	 *
	 * @param  string  $slug    The provider-scoped family slug.
	 * @param  string  $weight  The face weight (e.g. `400`, `700`).
	 * @param  string  $style   The face style (`normal` or `italic`).
	 * @param  string  $format  One of {@see serverReadableFormats()}.
	 *
	 * @return string The raw binary contents of the face file in `$format`.
	 */
	public function fetchFaceInFormat( string $slug, string $weight, string $style, string $format ): string;
}
