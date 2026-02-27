<?php

/**
 * Inspector Field Component.
 *
 * Renders the appropriate control component based on a block schema
 * field definition. Acts as a universal field renderer for the inspector.
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
 * Inspector field component that renders controls based on schema type.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\View\Components
 *
 * @since      1.0.0
 */
class InspectorField extends Component
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
	 * @param string       $name    The field name/key.
	 * @param array        $schema  The field schema definition.
	 * @param mixed        $value   The current field value.
	 * @param string|null  $blockId The block ID for dispatching updates.
	 * @param string|null  $id      Optional custom ID.
	 */
	public function __construct(
		public string $name,
		public array $schema = [],
		public mixed $value = null,
		public ?string $blockId = null,
		public ?string $id = null,
	) {
		$this->uuid = 've-' . Str::random( 8 ) . ( $id ? '-' . $id : '' );
	}

	/**
	 * Get the field type from the schema.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function fieldType(): string
	{
		return $this->schema['type'] ?? 'text';
	}

	/**
	 * Get the field label from the schema.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function fieldLabel(): string
	{
		return $this->schema['label'] ?? $this->name;
	}

	/**
	 * Get the field placeholder from the schema.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function fieldPlaceholder(): string
	{
		return $this->schema['placeholder'] ?? '';
	}

	/**
	 * Get the field options from the schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function fieldOptions(): array
	{
		return $this->schema['options'] ?? [];
	}

	/**
	 * Get the field default value from the schema.
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function fieldDefault(): mixed
	{
		return $this->schema['default'] ?? null;
	}

	/**
	 * Get the field hint/description from the schema.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function fieldHint(): string
	{
		return $this->schema['hint'] ?? '';
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
		return view( 'visual-editor::components.inspector-field' );
	}
}
