{{--
 * Visual Editor - Column Toolbar
 *
 * A toolbar that appears above a selected column container within a columns block.
 * Allows dragging, reordering, and deleting columns.
 *
 * Required variables:
 * @var string  $parentBlockId  The parent columns block ID.
 * @var int     $columnIndex    The column index (0-based).
 * @var int     $totalColumns   The total number of columns in the parent.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      2.1.0
 --}}

<div
	class="ve-column-toolbar absolute bottom-full left-0 z-20 mb-1 flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-1.5 py-1 shadow-sm"
	x-data="{ openDropdown: null }"
	@mousedown.prevent.self
	@click.stop
>
	{{-- Column Indicator --}}
	<span
		class="flex items-center gap-1 rounded px-1.5 py-1 text-sm font-medium text-gray-500"
		title="{{ __( 'Column :index', [ 'index' => $columnIndex + 1 ] ) }}"
	>
		<x-artisanpack-icon name="fas.table-columns" class="h-4 w-4" />
	</span>

	{{-- Drag Handle --}}
	<span
		x-drag-item="'{{ $parentBlockId }}-col-{{ $columnIndex }}'"
		draggable="true"
		class="cursor-grab rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
		title="{{ __( 'Drag to reorder column' ) }}"
		aria-label="{{ __( 'Drag to reorder column' ) }}"
	>
		<svg class="h-4.5 w-4.5" fill="currentColor" viewBox="0 0 24 24">
			<circle cx="9" cy="5" r="1.5" />
			<circle cx="15" cy="5" r="1.5" />
			<circle cx="9" cy="12" r="1.5" />
			<circle cx="15" cy="12" r="1.5" />
			<circle cx="9" cy="19" r="1.5" />
			<circle cx="15" cy="19" r="1.5" />
		</svg>
	</span>

	{{-- Move Left / Move Right --}}
	<div class="flex gap-0.5">
		<button
			type="button"
			wire:click.stop="moveColumnLeft( '{{ $parentBlockId }}', {{ $columnIndex }} )"
			class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30 disabled:hover:bg-transparent"
			title="{{ __( 'Move left' ) }}"
			@if ( 0 === $columnIndex ) disabled @endif
		>
			<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
			</svg>
		</button>
		<button
			type="button"
			wire:click.stop="moveColumnRight( '{{ $parentBlockId }}', {{ $columnIndex }} )"
			class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-30 disabled:hover:bg-transparent"
			title="{{ __( 'Move right' ) }}"
			@if ( $columnIndex >= $totalColumns - 1 ) disabled @endif
		>
			<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
			</svg>
		</button>
	</div>

	{{-- Separator --}}
	<div class="mx-0.5 h-6 w-px bg-gray-200"></div>

	{{-- More Options with Delete --}}
	<div class="relative">
		<button
			type="button"
			@click="openDropdown = (openDropdown === 'more' ? null : 'more')"
			class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
			title="{{ __( 'More options' ) }}"
		>
			<svg class="h-4.5 w-4.5" fill="currentColor" viewBox="0 0 24 24">
				<circle cx="12" cy="5" r="2" />
				<circle cx="12" cy="12" r="2" />
				<circle cx="12" cy="19" r="2" />
			</svg>
		</button>
		<div
			x-show="openDropdown === 'more'"
			@click.outside="openDropdown = null"
			x-transition
			class="absolute right-0 top-full z-30 mt-1 w-44 rounded border border-gray-200 bg-white py-1 shadow-lg"
		>
			<button
				type="button"
				wire:click.stop="deleteColumn( '{{ $parentBlockId }}', {{ $columnIndex }} )"
				@click="openDropdown = null"
				class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
			>
				<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
					<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
				</svg>
				{{ __( 'Delete column' ) }}
			</button>
		</div>
	</div>
</div>
