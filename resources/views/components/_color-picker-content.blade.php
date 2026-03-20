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
		{{ __( 'visual-editor::ve.color' ) }}
	</button>
	<button
		type="button"
		x-on:click="tab = 'gradient'"
		:class="tab === 'gradient' ? 'border-primary text-primary font-medium' : 'border-transparent text-base-content/50 hover:text-base-content/70'"
		class="px-4 py-2 text-sm border-b-2 -mb-px transition-colors"
	>
		{{ __( 'visual-editor::ve.gradient' ) }}
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
		<div class="text-sm text-base-content/60" x-text="color || '{{ __( 'visual-editor::ve.no_color_selected' ) }}'"></div>
	</div>

	{{-- Theme Palette --}}
	<div>
		<div class="text-[10px] font-semibold uppercase tracking-wider text-base-content/40 mb-2">
			{{ __( 'visual-editor::ve.theme' ) }}
		</div>
		<div
			class="flex flex-wrap gap-2"
			role="listbox"
			aria-label="{{ $label ?? __( 'visual-editor::ve.color_palette' ) }}"
		>
			@php
			$lightColors = [ '#ffffff', '#d1d5db', '#9ca3af', '#f9fafb', '#f3f4f6', '#e5e7eb', '#f0f9ff', '#e0f2fe', '#e2e8f0', '#bfdbfe', '#f8fafc' ];
		@endphp
		@foreach ( $palette as $index => $paletteColor )
				@php
					$entry      = $paletteEntries[ $index ] ?? null;
					$entryName  = $entry['name'] ?? ucfirst( str_replace( '#', '', $paletteColor ) );
					$entrySlug  = $entry['slug'] ?? null;
					$checkColor = '#ffffff';

					if ( function_exists( 'a11yGetContrastColor' ) ) {
						try {
							$checkColor = a11yGetContrastColor( $paletteColor );
						} catch ( \Throwable $e ) {
							$checkColor = in_array( strtolower( $paletteColor ), $lightColors ) ? '#374151' : '#ffffff';
						}
					} else {
						$checkColor = in_array( strtolower( $paletteColor ), $lightColors ) ? '#374151' : '#ffffff';
					}
				@endphp
				<div class="relative group">
					<button
						type="button"
						x-on:click="select( '{{ $paletteColor }}' )"
						:class="color === '{{ $paletteColor }}' ? 'ring-2 ring-primary ring-offset-2 ring-offset-base-100' : 'ring-1 ring-base-300 hover:ring-base-content/30'"
						class="h-7 w-7 rounded-full transition-all cursor-pointer"
						style="background-color: {{ $paletteColor }}"
						role="option"
						:aria-selected="color === '{{ $paletteColor }}' ? 'true' : 'false'"
						aria-label="{{ $entryName ? $entryName . ' — ' : '' }}{{ __( 'visual-editor::ve.select_color' ) }} {{ $paletteColor }}"
					>
						<span
							x-show="color === '{{ $paletteColor }}'"
							class="flex h-full w-full items-center justify-center"
						>
							<svg class="h-3.5 w-3.5" fill="none" stroke="{{ $checkColor }}" viewBox="0 0 24 24">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
							</svg>
						</span>
					</button>
					<div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 px-2 py-1 text-[10px] font-medium text-base-100 bg-base-content rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
						{{ $entryName }}
					</div>
				</div>
			@endforeach
		</div>
	</div>

	@if ( $showCustom )
		{{-- Custom Color Picker --}}
		<div>
			<div class="text-[10px] font-semibold uppercase tracking-wider text-base-content/40 mb-2">
				{{ __( 'visual-editor::ve.custom' ) }}
			</div>

			{{-- Swatch button that toggles the full picker --}}
			<button
				type="button"
				x-on:click="showPicker = ! showPicker"
				class="h-7 w-7 rounded-full ring-1 transition-all cursor-pointer"
				:class="showPicker ? 'ring-2 ring-primary ring-offset-2 ring-offset-base-100' : 'ring-base-300 hover:ring-base-content/30'"
				:style="color ? `background-color: ${color}` : 'background: conic-gradient(red, yellow, lime, aqua, blue, magenta, red)'"
				aria-label="{{ __( 'visual-editor::ve.open_custom_color_picker' ) }}"
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
			{{ __( 'visual-editor::ve.clear' ) }}
		</button>
	</div>
</div>

{{-- Gradient Tab Content (placeholder for future) --}}
<div x-show="tab === 'gradient'" class="py-6 text-center text-sm text-base-content/40">
	{{ __( 'visual-editor::ve.gradient_options_coming' ) }}
</div>
