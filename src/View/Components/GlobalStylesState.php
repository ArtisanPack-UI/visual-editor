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
use Illuminate\Support\Facades\Log;
use Illuminate\View\Component;
use Throwable;

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
		$this->paletteEntries = $this->resolveManagerData( 'visual-editor.color-palette', $palette );
		$this->typographyData = $this->resolveManagerData( 'visual-editor.typography-presets', $typography );
		$this->spacingData    = $this->resolveManagerData( 'visual-editor.spacing-scale', $spacing );
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

	/**
	 * Resolve store-format data from a named manager, optionally applying override data.
	 *
	 * When $override is null, returns the manager's default store format.
	 * When $override is provided, clones the manager, applies the override,
	 * and returns the resulting store format.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $binding  The service container binding key for the manager.
	 * @param array|null $override Optional data to apply via fromStoreFormat().
	 *
	 * @return array<string, mixed>
	 */
	private function resolveManagerData( string $binding, ?array $override ): array
	{
		$manager = app( $binding );

		if ( null !== $override ) {
			$manager = clone $manager;

			try {
				$manager->fromStoreFormat( $override );
			} catch ( Throwable $e ) {
				Log::error( '[ve] GlobalStylesState: fromStoreFormat() failed for binding "' . $binding . '": ' . $e->getMessage() );

				return ( clone app( $binding ) )->toStoreFormat();
			}
		}

		return $manager->toStoreFormat();
	}
}
