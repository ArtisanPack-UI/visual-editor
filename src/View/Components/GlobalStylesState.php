<?php

/**
 * Global Styles State Component.
 *
 * Provides a standalone Alpine.store('globalStyles') that mirrors the
 * global-styles interface of the full editor store. Used on the Global
 * Styles admin page and any context that edits styles without the full
 * document editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Global Styles State component for standalone style editing.
 *
 * Registers a lightweight Alpine store ('globalStyles') that provides
 * the same interface the style editors expect (globalStyles sub-object,
 * _syncGlobalCssVariables, markDirty, _pushHistory, _dispatchChange),
 * but without the full document editor overhead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class GlobalStylesState extends Component
{
	/**
	 * The initial color palette entries.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array{name: string, slug: string, color: string}>
	 */
	public array $paletteEntries;

	/**
	 * The initial typography data.
	 *
	 * @since 1.0.0
	 *
	 * @var array{fontFamilies: array<string, string>, elements: array<string, array<string, string>>}
	 */
	public array $typographyData;

	/**
	 * The initial spacing data.
	 *
	 * @since 1.0.0
	 *
	 * @var array{scale: array<int, array{name: string, slug: string, value: string}>, blockGap: string, customSteps: array<int, array{name: string, slug: string, value: string}>}
	 */
	public array $spacingData;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $palette    Optional palette data (defaults to manager).
	 * @param array|null $typography Optional typography data (defaults to manager).
	 * @param array|null $spacing    Optional spacing data (defaults to manager).
	 */
	public function __construct(
		?array $palette = null,
		?array $typography = null,
		?array $spacing = null,
	) {
		$this->paletteEntries = $palette ?? app( 'visual-editor.color-palette' )->toStoreFormat();
		$this->typographyData = $typography ?? app( 'visual-editor.typography-presets' )->toStoreFormat();
		$this->spacingData    = $spacing ?? app( 'visual-editor.spacing-scale' )->toStoreFormat();
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.0.0
	 *
	 * @return Closure|string|View
	 */
	public function render(): View|Closure|string
	{
		return view( 'visual-editor::components.global-styles-state' );
	}
}
