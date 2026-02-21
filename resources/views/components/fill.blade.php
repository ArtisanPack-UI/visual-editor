{{--
 * Fill Component
 *
 * Registers content to render in a named SlotContainer.
 * Uses x-teleport to move content to the slot target.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		init() {
			if ( $store.slotFills ) {
				$store.slotFills.register( '{{ $slotName }}', '{{ $uuid }}', {{ $priority }} );
			}
		},
		destroy() {
			if ( $store.slotFills ) {
				$store.slotFills.unregister( '{{ $slotName }}', '{{ $uuid }}' );
			}
		}
	}"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	<template x-teleport="[data-ve-slot-target='{{ $slotName }}']">
		<div data-ve-fill="{{ $uuid }}" data-ve-fill-priority="{{ $priority }}">
			{{ $slot }}
		</div>
	</template>
</div>
