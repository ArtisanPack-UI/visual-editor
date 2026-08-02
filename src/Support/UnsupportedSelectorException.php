<?php

/**
 * Raised when a `block.json` attribute `selector` falls outside the CSS
 * subset {@see CssSelectorToXPath} understands. Callers catch it and
 * degrade to "this attribute was not recovered" rather than risk
 * matching the wrong node.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.5.5
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

use InvalidArgumentException;

class UnsupportedSelectorException extends InvalidArgumentException
{
}
