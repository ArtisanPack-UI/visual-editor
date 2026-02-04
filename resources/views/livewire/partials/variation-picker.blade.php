<?php

declare( strict_types=1 );

/**
 * Visual Editor - Variation Picker Partial
 *
 * Reusable variation picker component that displays available block variations
 * in a grid layout. Can be used by any block type that supports variations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      2.0.0
 *
 * @var string $blockType        The block type (e.g., 'group').
 * @var string $blockId          The block ID.
 * @var array  $variations       Array of variations from BlockRegistry.
 * @var string $currentVariation The currently selected variation name.
 */

?>

<div class="w-full text-center">
	<p class="mb-3 text-sm font-medium text-gray-600">{{ __( 'Choose a variation:' ) }}</p>
	<div class="grid grid-cols-4 gap-3">
		@foreach ( $variations as $varKey => $variation )
			<button
				type="button"
				wire:click="applyBlockVariation( '{{ $blockType }}', '{{ $varKey }}' )"
				class="flex flex-col items-center gap-2 rounded-md border-2 p-3 transition-all hover:border-blue-400 hover:bg-blue-50
					{{ $varKey === $currentVariation ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white' }}"
			>
				<x-artisanpack-icon
					name="{{ $variation['icon'] ?? 'fas.cube' }}"
					class="h-6 w-6 {{ $varKey === $currentVariation ? 'text-blue-600' : 'text-gray-500' }}"
				/>
				<div>
					<div class="text-sm font-medium {{ $varKey === $currentVariation ? 'text-blue-900' : 'text-gray-900' }}">
						{{ $variation['title'] }}
					</div>
					@if ( ! empty( $variation['description'] ) )
						<div class="mt-0.5 text-xs text-gray-500">
							{{ $variation['description'] }}
						</div>
					@endif
				</div>
			</button>
		@endforeach
	</div>
	<p class="mt-3 text-xs text-gray-500">{{ __( 'Or add blocks below' ) }}</p>
</div>
