{{--
 * Color System Component
 *
 * A Gutenberg-style color picker with palette, custom hex input, and optional contrast checking.
 * Includes Color/Gradient tabs for future gradient support.
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
		showCustomInput: false,
		customColor: {{ Js::from( $value ? ltrim( $value, '#' ) : '' ) }},
		select( value ) {
			this.color = value
			this.customColor = value.replace( /^#/, '' )
			$dispatch( 've-color-change', { color: value } )
		},
		clear() {
			this.color = ''
			this.customColor = ''
			$dispatch( 've-color-change', { color: '' } )
		},
		onCustomInput() {
			if ( /^[0-9A-Fa-f]{6}$/.test( this.customColor ) ) {
				this.color = '#' + this.customColor
				$dispatch( 've-color-change', { color: this.color } )
			}
		},
		onNativePickerInput() {
			this.customColor = this.color.replace( /^#/, '' )
			$dispatch( 've-color-change', { color: this.color } )
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-3' ] ) }}
>
	@if ( $label )
		<label class="text-xs font-medium text-base-content/60">
			{{ $label }}
		</label>
	@endif

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
			{{-- Custom Section --}}
			<div>
				<div class="text-[10px] font-semibold uppercase tracking-wider text-base-content/40 mb-2">
					{{ __( 'Custom' ) }}
				</div>

				<div class="flex items-center gap-3">
					{{-- Custom color swatch / native picker trigger --}}
					<label class="relative cursor-pointer">
						<div
							class="h-7 w-7 rounded-full ring-1 ring-base-300 hover:ring-base-content/30 transition-all"
							:class="showCustomInput && 'ring-2 ring-primary ring-offset-2 ring-offset-base-100'"
							:style="customColor ? `background-color: #${customColor}` : 'background: conic-gradient(red, yellow, lime, aqua, blue, magenta, red)'"
						></div>
						<input
							type="color"
							x-model="color"
							x-on:input="onNativePickerInput()"
							x-on:click="showCustomInput = true"
							class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
							aria-label="{{ __( 'Color picker' ) }}"
						/>
					</label>

					{{-- Hex input (show when custom is active) --}}
					<div x-show="showCustomInput" x-collapse class="flex items-center gap-2 flex-1">
						<span class="text-sm text-base-content/40">#</span>
						<x-artisanpack-input
							x-model="customColor"
							x-on:input="onCustomInput()"
							placeholder="FFFFFF"
							size="sm"
							class="font-mono"
							:aria-label="__( 'Hex color value' )"
						/>
					</div>
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

	@if ( $showContrast && $value )
		@php
			$contrastResult = $checkContrast( $value, $contrastBackground );
		@endphp
		@if ( null !== $contrastResult )
			<x-artisanpack-badge
				:value="$contrastResult ? __( 'Pass' ) : __( 'Fail' )"
				:color="$contrastResult ? 'success' : 'error'"
				size="sm"
			/>
		@endif
	@endif

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>
