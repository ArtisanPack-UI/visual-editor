<?php

namespace ArtisanPackUI\VisualEditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerBlock(string|\Closure $source)
 * @method static void registerBlockType(string $name, array $definition)
 * @method static \ArtisanPackUI\VisualEditor\Blocks\DynamicBlock registerDynamicBlock(\ArtisanPackUI\VisualEditor\Blocks\DynamicBlock|string $blockOrName, ?array $config = null)
 * @method static \ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry getRegistry()
 * @method static \ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry getDynamicBlockRegistry()
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
