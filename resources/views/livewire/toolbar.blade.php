<?php

declare( strict_types=1 );

/**
 * Visual Editor - Toolbar
 *
 * Top toolbar component providing primary editor actions such as
 * save, publish, undo/redo, and preview.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use Livewire\Component;

new class extends Component {
	/**
	 * The current save status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $saveStatus = 'saved';

	/**
	 * The content title.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $contentTitle = '';

	/**
	 * The content publish status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public string $contentStatus = 'draft';

	/**
	 * Whether undo is available.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $canUndo = false;

	/**
	 * Whether redo is available.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $canRedo = false;

	/**
	 * Dispatch a save event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save(): void
	{
		$this->dispatch( 'editor-save' );
	}

	/**
	 * Dispatch a publish event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function publish(): void
	{
		$this->dispatch( 'editor-publish' );
	}

	/**
	 * Dispatch an undo event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function undo(): void
	{
		$this->dispatch( 'editor-undo' );
	}

	/**
	 * Dispatch a redo event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function redo(): void
	{
		$this->dispatch( 'editor-redo' );
	}

	/**
	 * Dispatch a preview event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function preview(): void
	{
		$this->dispatch( 'editor-preview' );
	}
}; ?>

<div class="ve-toolbar flex items-center justify-between border-b border-gray-200 bg-white px-4 py-2">
	{{-- Left: Title --}}
	<div class="flex items-center gap-3">
		<h1 class="text-lg font-semibold text-gray-900">
			{{ $contentTitle ?: __( 'Untitled' ) }}
		</h1>
		<span class="rounded-full px-2 py-0.5 text-xs font-medium
			{{ 'published' === $contentStatus ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
			{{ ucfirst( $contentStatus ) }}
		</span>
	</div>

	{{-- Center: Undo / Redo --}}
	<div class="flex items-center gap-1">
		<button
			wire:click="undo"
			class="rounded p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-50"
			@disabled(!$canUndo)
			title="{{ __( 'Undo' ) }}"
		>
			<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 0 1 0 10H9" />
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 6L3 10l4 4" />
			</svg>
		</button>
		<button
			wire:click="redo"
			class="rounded p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-50"
			@disabled(!$canRedo)
			title="{{ __( 'Redo' ) }}"
		>
			<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a5 5 0 0 0 0 10h4" />
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 6l4 4-4 4" />
			</svg>
		</button>
	</div>

	{{-- Right: Actions --}}
	<div class="flex items-center gap-2">
		<span class="text-sm text-gray-500">
			@if ( 'saving' === $saveStatus )
				{{ __( 'Saving...' ) }}
			@elseif ( 'saved' === $saveStatus )
				{{ __( 'Saved' ) }}
			@else
				{{ __( 'Unsaved changes' ) }}
			@endif
		</span>

		<button
			wire:click="preview"
			class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
		>
			{{ __( 'Preview' ) }}
		</button>

		<button
			wire:click="save"
			class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
		>
			{{ __( 'Save Draft' ) }}
		</button>

		<button
			wire:click="publish"
			class="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
		>
			{{ __( 'Publish' ) }}
		</button>
	</div>
</div>
