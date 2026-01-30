<?php

declare( strict_types=1 );

/**
 * Visual Editor - Toolbar
 *
 * Top toolbar component providing primary editor actions such as
 * save, publish, undo/redo, and preview. Button labels adapt to
 * the current content status.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use Livewire\Attributes\Reactive;
use Livewire\Component;

new class extends Component {
	/**
	 * The current save status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[Reactive]
	public string $saveStatus = 'saved';

	/**
	 * The content title.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[Reactive]
	public string $contentTitle = '';

	/**
	 * The content publish status.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	#[Reactive]
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
	 * Whether the left sidebar is open.
	 *
	 * @since 1.5.0
	 *
	 * @var bool
	 */
	#[Reactive]
	public bool $sidebarOpen = true;

	/**
	 * Whether the right settings panel is open.
	 *
	 * @since 1.5.0
	 *
	 * @var bool
	 */
	#[Reactive]
	public bool $settingsOpen = false;

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
	 * Dispatch an unpublish event.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function unpublish(): void
	{
		$this->dispatch( 'editor-unpublish' );
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

	/**
	 * Dispatch a toggle sidebar event.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public function toggleSidebar(): void
	{
		$this->dispatch( 'editor-toggle-sidebar' );
	}

	/**
	 * Dispatch a toggle settings event.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function toggleSettings(): void
	{
		$this->dispatch( 'editor-toggle-settings' );
	}
}; ?>

<div class="ve-toolbar flex items-center justify-between border-b border-gray-200 bg-white px-4 py-2">
	{{-- Left: Title + Sidebar Toggle --}}
	<div class="flex items-center gap-3">
		<x-artisanpack-button
			wire:click="toggleSidebar"
			icon="o-bars-3-bottom-left"
			variant="ghost"
			size="sm"
			:tooltip-bottom="__( 'Toggle Sidebar' )"
			:class="$sidebarOpen ? 'text-blue-600 bg-blue-50' : ''"
		/>
		<x-artisanpack-heading level="1" size="text-lg" semibold>
			{{ $contentTitle ?: __( 'Untitled' ) }}
		</x-artisanpack-heading>
		@php
			$statusColor = match ( $contentStatus ) {
				'published' => 'success',
				'scheduled' => 'info',
				'pending'   => 'warning',
				default     => 'warning',
			};
		@endphp
		<x-artisanpack-badge :value="ucfirst( $contentStatus )" :color="$statusColor" />
	</div>

	{{-- Center: Undo / Redo --}}
	<div class="flex items-center gap-1">
		<x-artisanpack-button
			wire:click="undo"
			icon="o-arrow-uturn-left"
			variant="ghost"
			size="sm"
			:tooltip-bottom="__( 'Undo' )"
			:disabled="!$canUndo"
			class="disabled:opacity-50"
		/>
		<x-artisanpack-button
			wire:click="redo"
			icon="o-arrow-uturn-right"
			variant="ghost"
			size="sm"
			:tooltip-bottom="__( 'Redo' )"
			:disabled="!$canRedo"
			class="disabled:opacity-50"
		/>
	</div>

	{{-- Right: Actions --}}
	<div class="flex items-center gap-2">
		<span class="text-sm text-gray-500">
			@if ( 'saving' === $saveStatus )
				{{ __( 'Saving...' ) }}
			@elseif ( 'saved' === $saveStatus )
				{{ __( 'Saved' ) }}
			@elseif ( 'error' === $saveStatus )
				<span class="text-red-500">{{ __( 'Save failed' ) }}</span>
			@else
				{{ __( 'Unsaved changes' ) }}
			@endif
		</span>

		<x-artisanpack-button
			wire:click="toggleSettings"
			icon="o-cog-6-tooth"
			variant="ghost"
			size="sm"
			:tooltip-bottom="__( 'Settings' )"
			:class="$settingsOpen ? 'text-blue-600 bg-blue-50' : ''"
		/>

		<x-artisanpack-button
			wire:click="preview"
			:label="__( 'Preview' )"
			icon="o-eye"
			variant="outline"
			size="sm"
		/>

		<x-artisanpack-button
			wire:click="save"
			:label="__( 'Save Draft' )"
			variant="outline"
			size="sm"
			spinner="save"
		/>

		@if ( 'published' === $contentStatus )
			<x-artisanpack-button
				wire:click="unpublish"
				:label="__( 'Switch to Draft' )"
				variant="outline"
				size="sm"
			/>

			<x-artisanpack-button
				wire:click="publish"
				:label="__( 'Update' )"
				color="primary"
				size="sm"
			/>
		@else
			<x-artisanpack-button
				wire:click="publish"
				:label="__( 'Publish' )"
				color="primary"
				size="sm"
			/>
		@endif
	</div>
</div>
