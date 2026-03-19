<?php

/**
 * Document Excerpt Component.
 *
 * Textarea bound to the editor store's meta bag for the document excerpt.
 * Supports optional character count and max length.
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
use Illuminate\Support\Str;
use Illuminate\View\Component;

/**
 * Document Excerpt field component for the document panel.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class DocumentExcerpt extends Component
{
	/**
	 * Unique identifier for this component instance.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $uuid;

	/**
	 * Create a new component instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $id          Optional custom ID.
	 * @param string      $metaKey     The meta key to bind to in the editor store.
	 * @param string|null $label       Label text for the field.
	 * @param string|null $placeholder Placeholder text for the textarea.
	 * @param int|null    $maxLength   Maximum character length (null for no limit).
	 */
	public function __construct(
		public ?string $id = null,
		public string $metaKey = 'excerpt',
		public ?string $label = null,
		public ?string $placeholder = null,
		public ?int $maxLength = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
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
		return view( 'visual-editor::components.document-excerpt' );
	}
}
