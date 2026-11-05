<?php

/**
 * Failure to import a remote image.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Media;

use RuntimeException;

/**
 * Thrown when {@see RemoteImageFetcher} refuses or can't fetch a URL. The
 * message is safe to show to the author.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class RemoteImageException extends RuntimeException
{
}
