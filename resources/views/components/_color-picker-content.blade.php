{{--
 * Color Picker Content Partial
 *
 * Shared content for the color picker used in both compact (popover) and inline modes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

{{-- Color/Gradient Tabs --}}
<div class="flex border-b border-base-300">
	<button
		type="button"
		x-on:click="tab = 'color'"
		:class="tab === 'color' ? 'border-primary text-primary font-medium' : 'border-transparent text-base-content/50 hover:text-base-content/70'"
		class="px-4 py-2 text-sm border-b-2 -mb-px transition-colors"
	>
		{{ __( 'Color' ) }}
	</button>
	<button
		type="button"
		x-on:click="tab = 'gradient'"
		:class="tab === 'gradient' ? 'border-primary text-primary font-medium' : 'border-transparent text-base-content/50 hover:text-base-content/70'"
		class="px-4 py-2 text-sm border-b-2 -mb-px transition-colors"
	>
		{{ __( 'Gradient' ) }}
	</button>
</div>

{{-- Color Tab Content --}}
<div x-show="tab === 'color'" class="flex flex-col gap-4">
	{{-- Color Preview --}}
	<div class="flex items-center gap-3">
		<div
			class="h-12 w-12 rounded-md border border-base-300 shrink-0"
			:class="!color && 'bg-[repeating-conic-gradient(#d1d5db_0%_25%,transparent_0%_50%)] bg-[length:12px_12px]'"
			:style="color ? `background-color: ${color}` : ''"
		></div>
		<div class="text-sm text-base-content/60" x-text="color || '{{ __( 'No color selected' ) }}'"></div>
	</div>

	{{-- Theme Palette --}}
	<div>
		<div class="text-[10px] font-semibold uppercase tracking-wider text-base-content/40 mb-2">
			{{ __( 'Theme' ) }}
		</div>
		<div
			class="flex flex-wrap gap-2"
			role="listbox"
			aria-label="{{ $label ?? __( 'Color palette' ) }}"
		>
			@foreach ( $palette as $paletteColor )
				<button
					type="button"
					x-on:click="select( '{{ $paletteColor }}' )"
					:class="color === '{{ $paletteColor }}' ? 'ring-2 ring-primary ring-offset-2 ring-offset-base-100' : 'ring-1 ring-base-300 hover:ring-base-content/30'"
					class="h-7 w-7 rounded-full transition-all cursor-pointer"
					style="background-color: {{ $paletteColor }}"
					role="option"
					:aria-selected="color === '{{ $paletteColor }}' ? 'true' : 'false'"
					aria-label="{{ __( 'Select color' ) }} {{ $paletteColor }}"
				>
					<span
						x-show="color === '{{ $paletteColor }}'"
						class="flex h-full w-full items-center justify-center"
					>
						<svg class="h-3.5 w-3.5" fill="none" stroke="{{ in_array( $paletteColor, [ '#FFFFFF', '#D1D5DB', '#9CA3AF', '#F9FAFB', '#F3F4F6', '#E5E7EB' ] ) ? '#374151' : '#ffffff' }}" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
						</svg>
					</span>
				</button>
			@endforeach
		</div>
	</div>

	@if ( $showCustom )
		{{-- Custom Color Picker --}}
		<div>
			<div class="text-[10px] font-semibold uppercase tracking-wider text-base-content/40 mb-2">
				{{ __( 'Custom' ) }}
			</div>

			{{-- Swatch button that toggles the full picker --}}
			<button
				type="button"
				x-on:click="showPicker = ! showPicker"
				class="h-7 w-7 rounded-full ring-1 transition-all cursor-pointer"
				:class="showPicker ? 'ring-2 ring-primary ring-offset-2 ring-offset-base-100' : 'ring-base-300 hover:ring-base-content/30'"
				:style="color ? `background-color: ${color}` : 'background: conic-gradient(red, yellow, lime, aqua, blue, magenta, red)'"
				aria-label="{{ __( 'Open custom color picker' ) }}"
			></button>

			{{-- Full color picker (shown when swatch is clicked) --}}
			<div x-show="showPicker" x-collapse class="mt-3">
				<x-ve-color-picker
					:value="$value ?? '#000000'"
					x-on:ve-color-picker-change.stop="select( $event.detail.hex )"
				/>
			</div>
		</div>
	@endif

	{{-- Clear Button --}}
	<div class="flex justify-end">
		<button
			type="button"
			x-on:click="clear()"
			x-show="color"
			class="text-sm text-base-content/50 hover:text-base-content/80 transition-colors"
		>
			{{ __( 'Clear' ) }}
		</button>
	</div>
</div>

{{-- Gradient Tab Content (placeholder for future) --}}
<div x-show="tab === 'gradient'" class="py-6 text-center text-sm text-base-content/40">
	{{ __( 'Gradient options coming soon.' ) }}
</div>
