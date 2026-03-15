<?php

/**
 * Dynamic Block Abstract Class.
 *
 * Base class for blocks that are rendered server-side via
 * Livewire components rather than storing static content.
 * Dynamic blocks mount a Livewire component for both editor
 * preview and frontend display.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks;

/**
 * Abstract base class for dynamic visual editor blocks.
 *
 * Dynamic blocks are rendered server-side using Livewire components
 * instead of storing static HTML content. They receive block attributes
 * as component props and render live previews in the editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @since      2.0.0
 */
abstract class DynamicBlock extends BaseBlock
{
	/**
	 * Whether this block is a dynamic (server-rendered) block.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function isDynamic(): bool
	{
		return true;
	}

	/**
	 * Get the Livewire component class for this dynamic block.
	 *
	 * Must be implemented by each dynamic block subclass.
	 *
	 * @since 2.0.0
	 *
	 * @return string The fully-qualified Livewire component class name.
	 */
	abstract public function getComponent(): string;

	/**
	 * Get the Livewire component tag name for rendering.
	 *
	 * Derives the component tag from the Livewire component class.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getComponentTag(): string
	{
		$class = $this->getComponent();

		$basename = class_basename( $class );

		return 'visual-editor.blocks.' . str( $basename )
			->kebab()
			->toString();
	}

	/**
	 * Render the block for frontend display.
	 *
	 * Dynamic blocks delegate rendering to their Livewire component.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $content     The block content values.
	 * @param array<string, mixed> $styles      The block style values.
	 * @param array<string, mixed> $context     Additional rendering context.
	 * @param array<int, string>   $innerBlocks Pre-rendered inner block HTML strings.
	 *
	 * @return string
	 */
	public function render( array $content, array $styles, array $context = [], array $innerBlocks = [] ): string
	{
		$viewName = $this->resolveView( 'save' );

		return view( $viewName, [
			'content'      => $content,
			'styles'       => $styles,
			'context'      => $context,
			'block'        => $this,
			'innerBlocks'  => $innerBlocks,
			'componentTag' => $this->getComponentTag(),
		] )->render();
	}

	/**
	 * Render the block for the editor.
	 *
	 * Dynamic blocks show a Livewire-powered live preview in the editor.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $content     The block content values.
	 * @param array<string, mixed> $styles      The block style values.
	 * @param array<string, mixed> $context     Additional rendering context.
	 * @param array<int, string>   $innerBlocks Pre-rendered inner block HTML strings.
	 *
	 * @return string
	 */
	public function renderEditor( array $content, array $styles, array $context = [], array $innerBlocks = [] ): string
	{
		$viewName = $this->resolveView( 'edit' );

		return view( $viewName, [
			'content'      => $content,
			'styles'       => $styles,
			'context'      => $context,
			'block'        => $this,
			'innerBlocks'  => $innerBlocks,
			'componentTag' => $this->getComponentTag(),
		] )->render();
	}

	/**
	 * Get block metadata as an array for serialization.
	 *
	 * Adds dynamic block metadata to the base serialization.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return array_merge( parent::toArray(), [
			'dynamic'      => true,
			'component'    => $this->getComponent(),
			'componentTag' => $this->getComponentTag(),
		] );
	}
}
