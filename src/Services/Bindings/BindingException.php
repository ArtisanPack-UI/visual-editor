<?php

/**
 * Soft-failure marker thrown into Laravel's `report()` channel when a
 * block binding cannot be resolved — e.g. the source driver is not
 * registered or the bound field is missing.
 *
 * The resolver never lets a binding break the surrounding render; this
 * exception exists so the failure is observable in error tracking
 * without being raised as an HTTP-visible error.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings;

use RuntimeException;

class BindingException extends RuntimeException
{
}
