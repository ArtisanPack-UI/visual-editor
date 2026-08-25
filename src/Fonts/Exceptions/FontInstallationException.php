<?php

/**
 * Font installation failure.
 *
 * Thrown by {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller}
 * when an install cannot proceed for a reason the caller can act on — an
 * unregistered provider key, a provider that is not self-hostable, an unknown
 * family slug, or an empty face selection. Distinct from
 * {@see FontProviderException} (a provider's own upstream failure) and
 * {@see FontFileWriteException} (a storage failure); the installer wraps any
 * unexpected error in this type after rolling the partial install back.
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

class FontInstallationException extends RuntimeException
{
}
