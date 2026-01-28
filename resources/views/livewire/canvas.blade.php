<?php

declare( strict_types=1 );

/**
 * Visual Editor - Canvas
 *
 * The main editing surface where content sections and blocks
 * are rendered. Supports drag-and-drop reordering of sections.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire
 *
 * @since      1.0.0
 */

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
	/**
	 * The content sections data.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $sections = [];

	/**
	 * The ID of the currently active block.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	public ?string $activeBlockId = null;

	/**
	 * Handle drag-and-drop reordering of sections.
	 *
	 * @since 1.0.0
	 *
	 * @param array $order The new section order.
	 *
	 * @return void
	 */
	public function reorderSections( array $order ): void
	{
		$reordered = [];

		foreach ( $order as $item ) {
			$index = (int) $item['value'];

			if ( isset( $this->sections[ $index ] ) ) {
				$reordered[] = $this->sections[ $index ];
			}
		}

		$this->sections = $reordered;
		$this->dispatch( 'sections-updated', sections: $this->sections );
	}

	/**
	 * Handle a block insert event from the sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type to insert.
	 *
	 * @return void
	 */
	#[On( 'block-insert' )]
	public function insertBlock( string $type ): void
	{
		$this->sections[] = [
			'id'     => uniqid( 've-', true ),
			'type'   => $type,
			'data'   => [],
			'settings' => [],
		];

		$this->dispatch( 'sections-updated', sections: $this->sections );
	}

	/**
	 * Select a block in the canvas.
	 *
	 * @since 1.0.0
	 *
	 * @param string $blockId The block ID to select.
	 *
	 * @return void
	 */
	public function selectBlock( string $blockId ): void
	{
		$this->activeBlockId = $blockId;
	}
}; ?>

<div class="ve-canvas flex-1 overflow-y-auto bg-gray-50 p-6">
	<div class="mx-auto max-w-4xl">
		@if ( empty( $sections ) )
			{{-- Empty State --}}
			<div class="flex min-h-96 flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-white p-12 text-center">
				<svg class="mb-4 h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
				</svg>
				<h3 class="mb-1 text-lg font-medium text-gray-900">
					{{ __( 'Start building your page' ) }}
				</h3>
				<p class="text-sm text-gray-500">
					{{ __( 'Add blocks from the sidebar to begin creating content.' ) }}
				</p>
			</div>
		@else
			{{-- Sections with drag-and-drop --}}
			<div wire:sort="reorderSections" class="space-y-4">
				@foreach ( $sections as $index => $section )
					<div
						wire:sort:item="{{ $index }}"
						wire:key="section-{{ $section['id'] ?? $index }}"
						wire:click="selectBlock( '{{ $section['id'] ?? '' }}' )"
						class="ve-canvas-block cursor-pointer rounded-lg border bg-white p-4 shadow-sm transition-colors
							{{ ( $section['id'] ?? '' ) === $activeBlockId ? 'border-blue-500 ring-2 ring-blue-200' : 'border-gray-200 hover:border-gray-300' }}"
					>
						<div class="flex items-center gap-2">
							<span class="cursor-grab text-gray-400" wire:sort:handle>
								<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
								</svg>
							</span>
							<span class="text-sm font-medium text-gray-700">
								{{ ucfirst( $section['type'] ?? __( 'Block' ) ) }}
							</span>
						</div>
						<div class="mt-2 text-sm text-gray-500">
							{{ __( 'Block content area' ) }}
						</div>
					</div>
				@endforeach
			</div>
		@endif
	</div>
</div>
