<?php

/**
 * Raised when an admin uploads an icon set whose prefix is already taken.
 *
 * Phase 6 (#557) of the Icon Block feature (#494). The controller maps
 * this exception to a 409 response with the offending prefix visible to
 * the admin so they can pick a different one without losing their zip.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Icon;

use RuntimeException;

final class PrefixCollisionException extends RuntimeException
{
	public function __construct( public readonly string $prefix )
	{
		parent::__construct( "Icon-set prefix \"{$prefix}\" is already registered." );
	}
}
