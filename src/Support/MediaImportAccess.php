<?php

/**
 * Whether the current user can import remote images.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

use Illuminate\Support\Facades\Gate;

/**
 * The server's half of the offer to import pasted images (#1066). The media
 * library is optional, so it's reached through its `apUploadMedia()` helper,
 * and uploads are authorized with its own policy.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class MediaImportAccess
{
	/**
	 * The media library's model, named as a string because it's optional.
	 *
	 * @since 1.20.0
	 */
	public const MEDIA_MODEL = 'ArtisanPackUI\\MediaLibrary\\Models\\Media';

	/**
	 * Whether a media library is installed.
	 *
	 * @since 1.20.0
	 */
	public static function isAvailable(): bool
	{
		return function_exists( 'apUploadMedia' );
	}

	/**
	 * Whether the current user may add to it.
	 *
	 * @since 1.20.0
	 */
	public static function allows(): bool
	{
		return self::isAvailable() && Gate::allows( 'create', self::MEDIA_MODEL );
	}
}
