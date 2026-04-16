<?php

namespace ArtisanPackUI\VisualEditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerBlock(string $blockJsonPath)
 * @method static void registerBlockType(string $name, array $definition)
 * @method static \ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry getRegistry()
 *
 * @see \ArtisanPackUI\VisualEditor\VisualEditor
 */
class VisualEditor extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor(): string
	{
		return \ArtisanPackUI\VisualEditor\VisualEditor::class;
	}
}
