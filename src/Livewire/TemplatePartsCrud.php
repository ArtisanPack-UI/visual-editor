<?php

/**
 * Template Parts CRUD Livewire Component.
 *
 * A headless Livewire bridge component that handles create, delete,
 * and assignment operations for template parts from the template
 * editor sidebar. Does not render its own UI; instead dispatches
 * browser events back to the Alpine-driven UI.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire;

use ArtisanPackUI\VisualEditor\Services\TemplatePartManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Headless Livewire component for template part CRUD operations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */
class TemplatePartsCrud extends Component
{
	/**
	 * Create a new template part and dispatch the result back.
	 *
	 * @since 1.0.0
	 *
	 * @param string $area The area for the new template part.
	 * @param string $name The name for the new template part.
	 *
	 * @return void
	 */
	#[On( 've-template-part-create' )]
	public function createPart( string $area, string $name ): void
	{
		$name = trim( $name );

		if ( '' === $name ) {
			return;
		}

		$originalSlug = Str::slug( $name );
		$slug         = $originalSlug;

		/** @var TemplatePartManager $manager */
		$manager  = app( 'visual-editor.template-parts' );
		$attempts = 0;

		while ( $manager->exists( $slug ) && $attempts < 10 ) {
			$slug = $originalSlug . '-' . Str::random( 4 );
			$attempts++;
		}

		$part = $manager->create( [
			'name'      => $name,
			'slug'      => $slug,
			'area'      => $area,
			'content'   => [],
			'status'    => 'active',
			'is_custom' => true,
			'user_id'   => auth()->id(),
		] );

		$this->dispatch( 've-template-part-created', [
			'area' => $area,
			'part' => [
				'id'   => $part->id,
				'name' => $part->name,
				'slug' => $part->slug,
			],
		] );
	}

	/**
	 * Delete a template part assignment (not the part itself).
	 *
	 * @since 1.0.0
	 *
	 * @param string $area The area to clear.
	 *
	 * @return void
	 */
	#[On( 've-template-part-clear-assignment' )]
	public function clearAssignment( string $area ): void
	{
		$this->dispatch( 've-template-part-assignment-cleared', [
			'area' => $area,
		] );
	}

	/**
	 * Get the view that represents the component.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'visual-editor::livewire.template-parts-crud' );
	}
}
