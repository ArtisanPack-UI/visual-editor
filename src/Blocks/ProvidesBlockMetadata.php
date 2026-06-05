<?php

/**
 * Marker interface for block classes that expose block.json-compatible metadata.
 *
 * Host apps and packages can register a block by passing the implementing class
 * name to {@see \ArtisanPackUI\VisualEditor\VisualEditor::registerBlock()}. The
 * returned array must match the block.json schema (at minimum a namespaced
 * `name` field).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks;

interface ProvidesBlockMetadata
{
	/**
	 * Return the block's metadata array.
	 *
	 * The shape mirrors block.json: at least a namespaced `name` plus the
	 * keys the editor consumes (`title`, `category`, `attributes`, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function blockMetadata(): array;
}
