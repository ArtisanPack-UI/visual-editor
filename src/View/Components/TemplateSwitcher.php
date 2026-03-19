<?php

/**
 * Template Switcher Component.
 *
 * A dropdown in the toolbar for selecting and switching between
 * available templates. Displays the current template name and
 * provides a list of alternatives.
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
 * Template Switcher component for the editor toolbar.
 *
 * Renders a dropdown that shows the currently active template
 * and allows switching to other available templates.
 *
 * Usage:
 *   <x-ve-template-switcher
 *       :templates="$templates"
 *       :current-slug="$currentSlug"
 *   />
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class TemplateSwitcher extends Component
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
	 * @param string|null $label       Accessible label. Defaults to translation.
	 * @param array<int, array<string, mixed>> $templates Available templates (each with name, slug, description).
	 * @param string|null $currentSlug The slug of the currently active template.
	 */
	public function __construct(
		public ?string $id = null,
		public ?string $label = null,
		public array $templates = [],
		public ?string $currentSlug = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
	}

	/**
	 * Get the display name of the currently active template.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function currentTemplateName(): string
	{
		foreach ( $this->templates as $template ) {
			$slug = $template['slug'] ?? '';
			if ( $slug === $this->currentSlug ) {
				return $template['name'] ?? $slug;
			}
		}

		return __( 'visual-editor::ve.template_select' );
	}

	/**
	 * Check if a template is currently selected.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function hasSelection(): bool
	{
		return null !== $this->currentSlug && '' !== $this->currentSlug;
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
		return view( 'visual-editor::components.template-switcher' );
	}
}
