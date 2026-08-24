<?php

/**
 * Font provider failure.
 *
 * Thrown by a {@see \ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider}
 * when it cannot fulfil a catalog, family, or face request — a missing API
 * credential, an unsuccessful upstream HTTP response, or a face the provider's
 * catalog does not describe. The Font Library controllers translate this into a
 * user-facing error so a flaky remote source never blanks the modal.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Exceptions;

use RuntimeException;

class FontProviderException extends RuntimeException
{
}
