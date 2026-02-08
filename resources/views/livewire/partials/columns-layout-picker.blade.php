<?php

declare( strict_types=1 );

/**
 * Visual Editor - Columns Layout Picker Partial
 *
 * Displays visual representations of different column layout presets
 * that users can select for their columns block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      2.0.0
 *
 * @var string $blockId The block ID.
 */

?>

<div class="w-full rounded-lg border border-gray-200 bg-white p-6 text-center shadow-sm">
	<div class="mb-4">
		<div class="mb-2 flex items-center justify-center gap-2">
			<x-artisanpack-icon name="fas.columns" class="h-5 w-5 text-gray-600" />
			<h3 class="text-lg font-semibold text-gray-900">{{ __( 'Columns' ) }}</h3>
		</div>
		<p class="text-sm text-gray-600">{{ __( 'Select a layout:' ) }}</p>
	</div>

	<div class="grid grid-cols-3 gap-4">
		{{-- 100% --}}
		<button
			type="button"
			wire:click="applyColumnsLayout( '{{ $blockId }}', '100' )"
			class="group flex flex-col items-center gap-2 rounded-md border-2 border-gray-200 bg-white p-3 transition-all hover:border-blue-400 hover:bg-blue-50"
		>
			<div class="flex h-12 w-full gap-1">
				<div class="flex-1 rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
			</div>
			<span class="text-xs font-medium text-gray-600 group-hover:text-blue-600">100</span>
		</button>

		{{-- 50-50 --}}
		<button
			type="button"
			wire:click="applyColumnsLayout( '{{ $blockId }}', '50-50' )"
			class="group flex flex-col items-center gap-2 rounded-md border-2 border-gray-200 bg-white p-3 transition-all hover:border-blue-400 hover:bg-blue-50"
		>
			<div class="flex h-12 w-full gap-1">
				<div class="flex-1 rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
				<div class="flex-1 rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
			</div>
			<span class="text-xs font-medium text-gray-600 group-hover:text-blue-600">50 / 50</span>
		</button>

		{{-- 33-67 --}}
		<button
			type="button"
			wire:click="applyColumnsLayout( '{{ $blockId }}', '33-67' )"
			class="group flex flex-col items-center gap-2 rounded-md border-2 border-gray-200 bg-white p-3 transition-all hover:border-blue-400 hover:bg-blue-50"
		>
			<div class="flex h-12 w-full gap-1">
				<div class="flex-[33] rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
				<div class="flex-[67] rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
			</div>
			<span class="text-xs font-medium text-gray-600 group-hover:text-blue-600">33 / 67</span>
		</button>

		{{-- 67-33 --}}
		<button
			type="button"
			wire:click="applyColumnsLayout( '{{ $blockId }}', '67-33' )"
			class="group flex flex-col items-center gap-2 rounded-md border-2 border-gray-200 bg-white p-3 transition-all hover:border-blue-400 hover:bg-blue-50"
		>
			<div class="flex h-12 w-full gap-1">
				<div class="flex-[67] rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
				<div class="flex-[33] rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
			</div>
			<span class="text-xs font-medium text-gray-600 group-hover:text-blue-600">67 / 33</span>
		</button>

		{{-- 33-33-33 --}}
		<button
			type="button"
			wire:click="applyColumnsLayout( '{{ $blockId }}', '33-33-33' )"
			class="group flex flex-col items-center gap-2 rounded-md border-2 border-gray-200 bg-white p-3 transition-all hover:border-blue-400 hover:bg-blue-50"
		>
			<div class="flex h-12 w-full gap-1">
				<div class="flex-1 rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
				<div class="flex-1 rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
				<div class="flex-1 rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
			</div>
			<span class="text-xs font-medium text-gray-600 group-hover:text-blue-600">33 / 33 / 33</span>
		</button>

		{{-- 25-50-25 --}}
		<button
			type="button"
			wire:click="applyColumnsLayout( '{{ $blockId }}', '25-50-25' )"
			class="group flex flex-col items-center gap-2 rounded-md border-2 border-gray-200 bg-white p-3 transition-all hover:border-blue-400 hover:bg-blue-50"
		>
			<div class="flex h-12 w-full gap-1">
				<div class="flex-[25] rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
				<div class="flex-[50] rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
				<div class="flex-[25] rounded bg-gray-300 transition-colors group-hover:bg-blue-300"></div>
			</div>
			<span class="text-xs font-medium text-gray-600 group-hover:text-blue-600">25 / 50 / 25</span>
		</button>
	</div>

	<div class="mt-4">
		<button
			type="button"
			wire:click="skipColumnsLayoutPicker( '{{ $blockId }}' )"
			class="text-xs text-gray-500 underline hover:text-gray-700"
		>
			{{ __( 'Skip' ) }}
		</button>
	</div>
</div>
