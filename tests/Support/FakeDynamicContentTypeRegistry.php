<?php

/**
 * Fake DynamicContentTypeRegistry used to exercise the visual-editor
 * Dynamic Content sources controller and inserter/autocomplete field
 * catalog without the cms-framework registry.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace Tests\Support;

class FakeDynamicContentTypeRegistry
{
	/**
	 * @param  array<string, array<string, mixed>>  $types
	 */
	public function __construct( protected array $types = [] )
	{
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array
	{
		return $this->types;
	}
}
