{{--
 * Color System Component
 *
 * A Gutenberg-style color picker with palette, custom hex input, and optional contrast checking.
 * Supports compact mode (dropdown) and inline mode.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		color: {{ Js::from( $value ?? '' ) }},
		tab: 'color',
		open: false,
		showPicker: false,
		dropdownStyle: '',
		select( value ) {
			this.color = value
			$dispatch( 've-color-change', { color: value } )
		},
		clear() {
			this.color = ''
			$dispatch( 've-color-change', { color: '' } )
		},
		positionDropdown() {
			const trigger = this.$refs.trigger;
			if ( ! trigger ) return;
			const rect   = trigger.getBoundingClientRect();
			const dropW  = 288;
			const margin = 8;

			let top  = rect.bottom + 4;
			let left = rect.right - dropW;

			if ( left < margin ) {
				left = margin;
			}

			if ( top + 400 > window.innerHeight ) {
				top = rect.top - 400 - 4;
				if ( top < margin ) {
					top = margin;
				}
			}

			this.dropdownStyle = 'top: ' + top + 'px; left: ' + left + 'px;';
		},
	}"
	{{ $attributes->merge( [ 'class' => $compact ? 'relative' : 'flex flex-col gap-3' ] ) }}
>
	@if ( $compact )
		{{-- Compact trigger --}}
		<div
			x-ref="trigger"
			role="button"
			tabindex="0"
			x-on:click="open = ! open; if ( open ) { $nextTick( () => positionDropdown() ) }"
			x-on:keydown.enter="open = ! open; if ( open ) { $nextTick( () => positionDropdown() ) }"
			x-on:keydown.space.prevent="open = ! open; if ( open ) { $nextTick( () => positionDropdown() ) }"
			:aria-expanded="open"
			class="flex items-center justify-between gap-3 w-full cursor-pointer border border-base-300 rounded-lg px-3 py-2 hover:bg-base-200/50 transition-colors"
		>
			@if ( $label )
				<span class="text-sm text-base-content truncate">{{ $label }}</span>
			@endif
			<div class="flex items-center gap-2 shrink-0">
				<div
					class="h-6 w-6 rounded-full ring-1 ring-base-300"
					:class="! color && 'bg-[repeating-conic-gradient(#d1d5db_0%_25%,transparent_0%_50%)] bg-[length:8px_8px]'"
					:style="color ? 'background-color: ' + color : ''"
				></div>
				<button
					type="button"
					x-show="color"
					x-on:click.stop="clear()"
					x-on:keydown.enter.stop="clear()"
					x-on:keydown.space.stop.prevent="clear()"
					class="text-base-content/40 hover:text-base-content/70 transition-colors text-lg leading-none cursor-pointer"
					aria-label="{{ __( 'visual-editor::ve.clear_color' ) }}"
				>&ndash;</button>
			</div>
		</div>

		{{-- Dropdown panel (teleported to body to escape overflow-hidden ancestors) --}}
		<template x-teleport="body">
			<div
				x-show="open"
				x-on:click.outside="open = false"
				x-on:keydown.escape.window="open = false"
				x-transition:enter="transition ease-out duration-100"
				x-transition:enter-start="opacity-0 scale-95"
				x-transition:enter-end="opacity-100 scale-100"
				x-transition:leave="transition ease-in duration-75"
				x-transition:leave-start="opacity-100 scale-100"
				x-transition:leave-end="opacity-0 scale-95"
				x-ref="dropdown"
				class="fixed z-[9999] w-72 rounded-lg border border-base-300 bg-base-100 shadow-lg p-4 flex flex-col gap-3"
				:style="dropdownStyle"
			>
				@include( 'visual-editor::components._color-picker-content' )
			</div>
		</template>
	@else
		@if ( $label )
			<label class="text-xs font-medium text-base-content/60">
				{{ $label }}
			</label>
		@endif

		@include( 'visual-editor::components._color-picker-content' )
	@endif

	@if ( $showContrast && $value )
		@php
			$contrastResult = $checkContrast( $value, $contrastBackground );
		@endphp
		@if ( null !== $contrastResult )
			<x-artisanpack-badge
				:value="$contrastResult ? __( 'visual-editor::ve.pass' ) : __( 'visual-editor::ve.fail' )"
				:color="$contrastResult ? 'success' : 'error'"
				size="sm"
			/>
		@endif
	@endif

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>
