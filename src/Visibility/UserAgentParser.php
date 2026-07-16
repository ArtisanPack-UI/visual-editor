<?php

/**
 * Fallback user-agent parser used by the Browser / OS / Device rule when
 * `jenssegers/agent` is not installed.
 *
 * Recognises the handful of browsers, OS families, and device classes
 * the editor's inspector exposes. Not a general-purpose UA library —
 * the intent is to answer the rule questions "is this Chrome?", "is this
 * iOS?", "is this a mobile phone?" with reasonable accuracy for the
 * top ~95% of real traffic. Hosts that need finer buckets install
 * `jenssegers/agent` and swap this out through the container.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility;

class UserAgentParser
{
	public const BROWSER_CHROME  = 'chrome';
	public const BROWSER_FIREFOX = 'firefox';
	public const BROWSER_SAFARI  = 'safari';
	public const BROWSER_EDGE    = 'edge';
	public const BROWSER_OPERA   = 'opera';
	public const BROWSER_IE      = 'ie';
	public const BROWSER_OTHER   = 'other';

	public const OS_WINDOWS = 'windows';
	public const OS_MACOS   = 'macos';
	public const OS_IOS     = 'ios';
	public const OS_ANDROID = 'android';
	public const OS_LINUX   = 'linux';
	public const OS_CHROMEOS = 'chromeos';
	public const OS_OTHER   = 'other';

	public const DEVICE_MOBILE  = 'mobile';
	public const DEVICE_TABLET  = 'tablet';
	public const DEVICE_DESKTOP = 'desktop';
	public const DEVICE_BOT     = 'bot';

	public function browser( string $userAgent ): string
	{
		if ( '' === $userAgent ) {
			return self::BROWSER_OTHER;
		}

		// Order matters — Edge / Opera / Chromium forks all include
		// "Chrome" in their UA string, so match the more-specific
		// tokens first.
		if ( str_contains( $userAgent, 'Edg/' ) || str_contains( $userAgent, 'Edge/' ) ) {
			return self::BROWSER_EDGE;
		}

		if ( str_contains( $userAgent, 'OPR/' ) || str_contains( $userAgent, 'Opera' ) ) {
			return self::BROWSER_OPERA;
		}

		if ( str_contains( $userAgent, 'Firefox/' ) || str_contains( $userAgent, 'FxiOS' ) ) {
			return self::BROWSER_FIREFOX;
		}

		if ( str_contains( $userAgent, 'Chrome/' ) || str_contains( $userAgent, 'CriOS' ) ) {
			return self::BROWSER_CHROME;
		}

		if ( str_contains( $userAgent, 'Safari/' ) && str_contains( $userAgent, 'Version/' ) ) {
			return self::BROWSER_SAFARI;
		}

		if ( str_contains( $userAgent, 'MSIE ' ) || str_contains( $userAgent, 'Trident/' ) ) {
			return self::BROWSER_IE;
		}

		return self::BROWSER_OTHER;
	}

	public function os( string $userAgent ): string
	{
		if ( '' === $userAgent ) {
			return self::OS_OTHER;
		}

		// iOS / Android before generic mobile so `iPhone OS 17_0` is
		// classified as iOS rather than falling through to `Mac OS X`.
		if ( str_contains( $userAgent, 'iPhone' ) || str_contains( $userAgent, 'iPad' ) || str_contains( $userAgent, 'iPod' ) ) {
			return self::OS_IOS;
		}

		if ( str_contains( $userAgent, 'Android' ) ) {
			return self::OS_ANDROID;
		}

		if ( str_contains( $userAgent, 'CrOS' ) ) {
			return self::OS_CHROMEOS;
		}

		if ( str_contains( $userAgent, 'Windows NT' ) || str_contains( $userAgent, 'Windows' ) ) {
			return self::OS_WINDOWS;
		}

		if ( str_contains( $userAgent, 'Mac OS X' ) || str_contains( $userAgent, 'Macintosh' ) ) {
			return self::OS_MACOS;
		}

		if ( str_contains( $userAgent, 'Linux' ) ) {
			return self::OS_LINUX;
		}

		return self::OS_OTHER;
	}

	public function device( string $userAgent ): string
	{
		if ( '' === $userAgent ) {
			return self::DEVICE_DESKTOP;
		}

		if ( $this->looksLikeBot( $userAgent ) ) {
			return self::DEVICE_BOT;
		}

		// iPad / Android tablets before generic mobile — "Mobile" appears
		// in most Android phone UAs but is absent on tablets.
		if ( str_contains( $userAgent, 'iPad' ) ) {
			return self::DEVICE_TABLET;
		}

		if ( str_contains( $userAgent, 'Android' ) && ! str_contains( $userAgent, 'Mobile' ) ) {
			return self::DEVICE_TABLET;
		}

		if ( str_contains( $userAgent, 'Mobile' ) || str_contains( $userAgent, 'iPhone' ) ) {
			return self::DEVICE_MOBILE;
		}

		return self::DEVICE_DESKTOP;
	}

	protected function looksLikeBot( string $userAgent ): bool
	{
		// Cheap, non-exhaustive list — matches the top crawlers editors
		// realistically care about excluding. Hosts that need more
		// precise detection install `jenssegers/agent` and get its
		// bundled Mobile_Detect crawler regex.
		static $tokens = [ 'bot', 'crawler', 'spider', 'slurp', 'facebookexternalhit', 'facebot', 'pingdom', 'monitor' ];

		$needle = strtolower( $userAgent );

		foreach ( $tokens as $token ) {
			if ( str_contains( $needle, $token ) ) {
				return true;
			}
		}

		return false;
	}
}
