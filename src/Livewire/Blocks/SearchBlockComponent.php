<?php

/**
 * Search Block Livewire Component.
 *
 * Server-side rendering component for the Search dynamic block.
 * Renders a configurable search form with proper accessibility
 * attributes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\Blocks;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Livewire component for the Search dynamic block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Blocks
 *
 * @since      2.0.0
 */
class SearchBlockComponent extends Component
{
	/**
	 * Input placeholder text.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $placeholder = 'Search…';

	/**
	 * Submit button text.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $buttonText = 'Search';

	/**
	 * Button position (inside, outside, or none).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $buttonPosition = 'outside';

	/**
	 * Button icon identifier.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $buttonIcon = 'magnifying-glass';

	/**
	 * Whether to show the form label.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $showLabel = true;

	/**
	 * The form label text.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $label = 'Search';

	/**
	 * Results per page.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public int $resultsPerPage = 10;

	/**
	 * Search scope (all or specific post types).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $searchScope = 'all';

	/**
	 * Display style (inline or stacked).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $displayStyle = 'inline';

	/**
	 * Whether this is being rendered in the editor.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $isEditor = false;

	/**
	 * Unique ID for the search input element.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $inputId = '';

	/**
	 * Initialize the component.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$this->inputId        = 've-search-input-' . (string) \Illuminate\Support\Str::uuid();
		$this->resultsPerPage = max( 1, min( 100, (int) $this->resultsPerPage ) );
	}

	/**
	 * Get the search form action URL.
	 *
	 * Supports customization via the ve.search.action-url filter hook.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getActionUrl(): string
	{
		$url = '/search';

		if ( function_exists( 'applyFilters' ) ) {
			$url = applyFilters( 've.search.action-url', $url );
		}

		return $url;
	}

	/**
	 * Render the component.
	 *
	 * @since 2.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'visual-editor::livewire.blocks.search-block', [
			'actionUrl' => $this->getActionUrl(),
		] );
	}
}
