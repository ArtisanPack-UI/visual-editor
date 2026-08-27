<?php

/**
 * Font file write failure.
 *
 * Thrown by {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontFileWriter}
 * and {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontsCssGenerator} when
 * a face file or the generated `fonts.css` bundle cannot be written to the
 * configured disk — a permissions problem, a full volume, or a failed atomic
 * rename. The installer treats it as a partial failure and rolls the
 * in-flight install back.
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

class FontFileWriteException extends RuntimeException
{
}
