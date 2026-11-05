<?php

/**
 * Save-time guard for block trees.
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

use ArtisanPackUI\VisualEditor\Effects\CustomCssGuard;

/**
 * Every filter a block tree goes through before it's stored, for the save
 * paths that know the stored tree: {@see CustomCssGuard} (#881) and
 * {@see BlockHtmlGuard} (#1062).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class BlockContentGuard
{
	/**
	 * Filter an incoming block tree for the current user.
	 *
	 * @since 1.20.0
	 *
	 * @param  mixed  $blocks  Incoming block array (anything else passes through).
	 * @param  mixed  $stored  The record's stored block array (or the theme file's
	 *                         blocks for a file→DB upsert), or null for a new record.
	 */
	public function filter( mixed $blocks, mixed $stored = null ): mixed
	{
		$blocks = app( CustomCssGuard::class )->filter( $blocks, $stored );

		return app( BlockHtmlGuard::class )->filter( $blocks, $stored );
	}
}
