<?php

use ArtisanPackUI\VisualEditor\Models\Pattern;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
	/**
	 * The name for a new pattern.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * The category for a new pattern.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $category = '';

	/**
	 * The currently selected pattern ID.
	 *
	 * @since 1.0.0
	 *
	 * @var int|null
	 */
	public ?int $selectedPatternId = null;

	/**
	 * Get all available patterns.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection<int, Pattern>
	 */
	#[Computed]
	public function patterns(): Collection
	{
		return Pattern::query()->orderBy( 'name' )->get();
	}

	/**
	 * Save a new pattern from the current blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks The block content to save.
	 *
	 * @return void
	 */
	#[On( 've-save-pattern' )]
	public function savePattern( array $blocks ): void
	{
		if ( ! auth()->check() ) {
			return;
		}

		$this->validate( [
			'name' => 'required|string|max:255',
		] );

		Pattern::create( [
			'name'     => $this->name,
			'slug'     => Str::slug( $this->name ),
			'blocks'   => $blocks,
			'category' => $this->category ?: null,
			'user_id'  => auth()->id(),
		] );

		$this->name     = '';
		$this->category = '';

		unset( $this->patterns );

		$this->dispatch( 've-pattern-saved' );
	}

	/**
	 * Load a pattern and dispatch its blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The pattern ID to load.
	 *
	 * @return void
	 */
	public function loadPattern( int $id ): void
	{
		if ( ! auth()->check() ) {
			return;
		}

		$pattern = Pattern::findOrFail( $id );

		$this->dispatch( 've-pattern-loaded', blocks: $pattern->blocks, name: $pattern->name );
	}

	/**
	 * Delete a pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The pattern ID to delete.
	 *
	 * @return void
	 */
	public function deletePattern( int $id ): void
	{
		if ( ! auth()->check() ) {
			return;
		}

		$pattern = Pattern::findOrFail( $id );

		if ( null !== $pattern->user_id && (int) $pattern->user_id !== (int) auth()->id() ) {
			abort( 403 );
		}

		$pattern->delete();

		unset( $this->patterns );

		$this->dispatch( 've-pattern-deleted' );
	}
}; ?>

<div>
	{{-- Pattern store is a headless component — no visible UI --}}
</div>
