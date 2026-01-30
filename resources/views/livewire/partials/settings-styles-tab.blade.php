{{--
 * Visual Editor - Styles Tab
 *
 * The Styles tab content for the block settings panel. Provides responsive
 * breakpoint controls, state-based styling, and collapsible sections for
 * Sizing, Typography, Position, Colors, and Borders.
 *
 * Required variables:
 * @var array      $activeBlockConfig The active block's registry configuration.
 * @var array      $currentSettings   The active block's current settings values.
 * @var string     $activeBreakpoint  The active responsive breakpoint (base, md, lg).
 * @var string     $activeState       The active interaction state (default, hover, focus, active).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Partials
 *
 * @since      1.8.0
 --}}

@php
	$supports   = $activeBlockConfig['supports'] ?? [];
	$styleValue = fn ( string $section, string $property ) =>
		(string) data_get( $currentSettings, "styles.{$activeBreakpoint}.{$activeState}.{$section}.{$property}", '' );
@endphp

{{-- Screen Size Selector --}}
<div class="border-b border-gray-200 px-3 py-2.5">
	<label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
		{{ __( 'Screen Size' ) }}
	</label>
	<div class="inline-flex rounded-md border border-gray-200">
		{{-- Mobile --}}
		<button
			type="button"
			wire:click="$set( 'activeBreakpoint', 'base' )"
			class="relative rounded-l-md px-3 py-1.5 transition-colors {{ 'base' === $activeBreakpoint ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-50' }}"
			title="{{ __( 'Mobile' ) }}"
		>
			<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z" />
			</svg>
		</button>
		{{-- Tablet --}}
		<button
			type="button"
			wire:click="$set( 'activeBreakpoint', 'md' )"
			class="relative border-x border-gray-200 px-3 py-1.5 transition-colors {{ 'md' === $activeBreakpoint ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-50' }}"
			title="{{ __( 'Tablet' ) }}"
		>
			<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
			</svg>
		</button>
		{{-- Desktop --}}
		<button
			type="button"
			wire:click="$set( 'activeBreakpoint', 'lg' )"
			class="relative rounded-r-md px-3 py-1.5 transition-colors {{ 'lg' === $activeBreakpoint ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:bg-gray-50' }}"
			title="{{ __( 'Desktop' ) }}"
		>
			<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
			</svg>
		</button>
	</div>
</div>

{{-- State Selector --}}
<div class="border-b border-gray-200 px-3 py-2.5">
	<label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">
		{{ __( 'State' ) }}
	</label>
	<select
		wire:model.live="activeState"
		class="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
	>
		<option value="default">{{ __( 'Default' ) }}</option>
		<option value="hover">{{ __( 'Hover' ) }}</option>
		<option value="focus">{{ __( 'Focus' ) }}</option>
		<option value="active">{{ __( 'Active' ) }}</option>
	</select>
</div>

{{-- Sizing Section --}}
@if ( in_array( 'sizing', $supports, true ) )
	<div x-data="{ open: true }" class="border-b border-gray-200">
		<button
			type="button"
			@click="open = !open"
			class="flex w-full items-center justify-between px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-50"
		>
			{{ __( 'Sizing' ) }}
			<svg
				:class="open && 'rotate-180'"
				class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
				fill="none"
				stroke="currentColor"
				viewBox="0 0 24 24"
			>
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
			</svg>
		</button>
		<div x-show="open" x-collapse class="space-y-3 px-3 pb-3">
			{{-- Padding --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Padding' ) }}</label>
				<div class="grid grid-cols-4 gap-1.5">
					@foreach ( [ 'top' => 'T', 'right' => 'R', 'bottom' => 'B', 'left' => 'L' ] as $side => $label )
						<div>
							<label class="mb-0.5 block text-center text-[10px] text-gray-400">{{ $label }}</label>
							<input
								type="text"
								value="{{ $styleValue( 'sizing', 'padding_' . $side ) }}"
								wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.padding_{{ $side }}', $event.target.value )"
								class="w-full rounded border border-gray-300 px-1.5 py-1 text-center text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
								placeholder="0"
							/>
						</div>
					@endforeach
				</div>
			</div>

			{{-- Margin --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Margin' ) }}</label>
				<div class="grid grid-cols-4 gap-1.5">
					@foreach ( [ 'top' => 'T', 'right' => 'R', 'bottom' => 'B', 'left' => 'L' ] as $side => $label )
						<div>
							<label class="mb-0.5 block text-center text-[10px] text-gray-400">{{ $label }}</label>
							<input
								type="text"
								value="{{ $styleValue( 'sizing', 'margin_' . $side ) }}"
								wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.margin_{{ $side }}', $event.target.value )"
								class="w-full rounded border border-gray-300 px-1.5 py-1 text-center text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
								placeholder="0"
							/>
						</div>
					@endforeach
				</div>
			</div>

			{{-- Width / Height --}}
			<div class="grid grid-cols-2 gap-2">
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Width' ) }}</label>
					<input
						type="text"
						value="{{ $styleValue( 'sizing', 'width' ) }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.width', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="{{ __( 'auto' ) }}"
					/>
				</div>
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Height' ) }}</label>
					<input
						type="text"
						value="{{ $styleValue( 'sizing', 'height' ) }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.height', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="{{ __( 'auto' ) }}"
					/>
				</div>
			</div>
		</div>
	</div>
@endif

{{-- Typography Section --}}
@if ( in_array( 'typography', $supports, true ) )
	<div x-data="{ open: true }" class="border-b border-gray-200">
		<button
			type="button"
			@click="open = !open"
			class="flex w-full items-center justify-between px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-50"
		>
			{{ __( 'Typography' ) }}
			<svg
				:class="open && 'rotate-180'"
				class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
				fill="none"
				stroke="currentColor"
				viewBox="0 0 24 24"
			>
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
			</svg>
		</button>
		<div x-show="open" x-collapse class="space-y-3 px-3 pb-3">
			{{-- Font Family --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Font Family' ) }}</label>
				<select
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.typography.font_family', $event.target.value )"
					class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
				>
					<option value="" @selected( '' === $styleValue( 'typography', 'font_family' ) )>{{ __( 'Default' ) }}</option>
					<option value="sans-serif" @selected( 'sans-serif' === $styleValue( 'typography', 'font_family' ) )>Sans Serif</option>
					<option value="serif" @selected( 'serif' === $styleValue( 'typography', 'font_family' ) )>Serif</option>
					<option value="monospace" @selected( 'monospace' === $styleValue( 'typography', 'font_family' ) )>Monospace</option>
				</select>
			</div>

			{{-- Font Size / Font Weight --}}
			<div class="grid grid-cols-2 gap-2">
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Font Size' ) }}</label>
					<input
						type="text"
						value="{{ $styleValue( 'typography', 'font_size' ) }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.typography.font_size', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="16"
					/>
				</div>
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Font Weight' ) }}</label>
					@php $fontWeight = $styleValue( 'typography', 'font_weight' ); @endphp
					<select
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.typography.font_weight', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
					>
						<option value="" @selected( '' === $fontWeight )>{{ __( 'Default' ) }}</option>
						<option value="100" @selected( '100' === $fontWeight )>Thin</option>
						<option value="300" @selected( '300' === $fontWeight )>Light</option>
						<option value="400" @selected( '400' === $fontWeight )>Normal</option>
						<option value="500" @selected( '500' === $fontWeight )>Medium</option>
						<option value="600" @selected( '600' === $fontWeight )>Semibold</option>
						<option value="700" @selected( '700' === $fontWeight )>Bold</option>
						<option value="900" @selected( '900' === $fontWeight )>Black</option>
					</select>
				</div>
			</div>
		</div>
	</div>
@endif

{{-- Position Section --}}
@if ( in_array( 'position', $supports, true ) )
	<div x-data="{ open: false }" class="border-b border-gray-200">
		<button
			type="button"
			@click="open = !open"
			class="flex w-full items-center justify-between px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-50"
		>
			{{ __( 'Position' ) }}
			<svg
				:class="open && 'rotate-180'"
				class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
				fill="none"
				stroke="currentColor"
				viewBox="0 0 24 24"
			>
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
			</svg>
		</button>
		<div x-show="open" x-collapse class="space-y-3 px-3 pb-3">
			{{-- Position Type --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Position' ) }}</label>
				@php $positionType = $styleValue( 'position', 'type' ); @endphp
				<select
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.type', $event.target.value )"
					class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
				>
					<option value="" @selected( '' === $positionType )>{{ __( 'Default' ) }}</option>
					<option value="relative" @selected( 'relative' === $positionType )>Relative</option>
					<option value="absolute" @selected( 'absolute' === $positionType )>Absolute</option>
					<option value="fixed" @selected( 'fixed' === $positionType )>Fixed</option>
					<option value="sticky" @selected( 'sticky' === $positionType )>Sticky</option>
				</select>
			</div>

			{{-- Top / Bottom --}}
			<div class="grid grid-cols-2 gap-2">
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Top' ) }}</label>
					<input
						type="text"
						value="{{ $styleValue( 'position', 'top' ) }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.top', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="{{ __( 'auto' ) }}"
					/>
				</div>
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Bottom' ) }}</label>
					<input
						type="text"
						value="{{ $styleValue( 'position', 'bottom' ) }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.bottom', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="{{ __( 'auto' ) }}"
					/>
				</div>
			</div>

			{{-- Left / Right --}}
			<div class="grid grid-cols-2 gap-2">
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Left' ) }}</label>
					<input
						type="text"
						value="{{ $styleValue( 'position', 'left' ) }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.left', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="{{ __( 'auto' ) }}"
					/>
				</div>
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Right' ) }}</label>
					<input
						type="text"
						value="{{ $styleValue( 'position', 'right' ) }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.right', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="{{ __( 'auto' ) }}"
					/>
				</div>
			</div>
		</div>
	</div>
@endif

{{-- Colors Section --}}
@if ( in_array( 'colors', $supports, true ) )
	<div x-data="{ open: true }" class="border-b border-gray-200">
		<button
			type="button"
			@click="open = !open"
			class="flex w-full items-center justify-between px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-50"
		>
			{{ __( 'Colors' ) }}
			<svg
				:class="open && 'rotate-180'"
				class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
				fill="none"
				stroke="currentColor"
				viewBox="0 0 24 24"
			>
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
			</svg>
		</button>
		<div x-show="open" x-collapse class="space-y-3 px-3 pb-3">
			{{-- Text Color --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Text Color' ) }}</label>
				@php $textColor = $styleValue( 'colors', 'text_color' ); @endphp
				<div class="flex items-center gap-2">
					<input
						type="color"
						value="{{ $textColor ?: '#000000' }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.colors.text_color', $event.target.value )"
						class="h-8 w-8 cursor-pointer rounded border border-gray-300"
					/>
					<input
						type="text"
						value="{{ $textColor }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.colors.text_color', $event.target.value )"
						class="flex-1 rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="#000000"
					/>
				</div>
			</div>

			{{-- Background Color --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Background Color' ) }}</label>
				@php $bgColor = $styleValue( 'colors', 'background_color' ); @endphp
				<div class="flex items-center gap-2">
					<input
						type="color"
						value="{{ $bgColor ?: '#ffffff' }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.colors.background_color', $event.target.value )"
						class="h-8 w-8 cursor-pointer rounded border border-gray-300"
					/>
					<input
						type="text"
						value="{{ $bgColor }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.colors.background_color', $event.target.value )"
						class="flex-1 rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="#ffffff"
					/>
				</div>
			</div>
		</div>
	</div>
@endif

{{-- Borders Section --}}
@if ( in_array( 'borders', $supports, true ) )
	<div x-data="{ open: true }" class="border-b border-gray-200">
		<button
			type="button"
			@click="open = !open"
			class="flex w-full items-center justify-between px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 hover:bg-gray-50"
		>
			{{ __( 'Borders' ) }}
			<svg
				:class="open && 'rotate-180'"
				class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
				fill="none"
				stroke="currentColor"
				viewBox="0 0 24 24"
			>
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
			</svg>
		</button>
		<div x-show="open" x-collapse class="space-y-3 px-3 pb-3">
			{{-- Border Color --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Border Color' ) }}</label>
				@php $borderColor = $styleValue( 'borders', 'border_color' ); @endphp
				<div class="flex items-center gap-2">
					<input
						type="color"
						value="{{ $borderColor ?: '#000000' }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.borders.border_color', $event.target.value )"
						class="h-8 w-8 cursor-pointer rounded border border-gray-300"
					/>
					<input
						type="text"
						value="{{ $borderColor }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.borders.border_color', $event.target.value )"
						class="flex-1 rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="#000000"
					/>
				</div>
			</div>

			{{-- Border Width --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Width' ) }}</label>
				<input
					type="text"
					value="{{ $styleValue( 'borders', 'border_width' ) }}"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.borders.border_width', $event.target.value )"
					class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
					placeholder="0"
				/>
			</div>

			{{-- Radius / Style --}}
			<div class="grid grid-cols-2 gap-2">
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Radius' ) }}</label>
					<input
						type="text"
						value="{{ $styleValue( 'borders', 'border_radius' ) }}"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.borders.border_radius', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
						placeholder="0"
					/>
				</div>
				<div>
					<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Style' ) }}</label>
					@php $borderStyle = $styleValue( 'borders', 'border_style' ); @endphp
					<select
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.borders.border_style', $event.target.value )"
						class="w-full rounded border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
					>
						<option value="" @selected( '' === $borderStyle )>{{ __( 'Default' ) }}</option>
						<option value="solid" @selected( 'solid' === $borderStyle )>{{ __( 'Solid' ) }}</option>
						<option value="dashed" @selected( 'dashed' === $borderStyle )>{{ __( 'Dashed' ) }}</option>
						<option value="dotted" @selected( 'dotted' === $borderStyle )>{{ __( 'Dotted' ) }}</option>
						<option value="none" @selected( 'none' === $borderStyle )>{{ __( 'None' ) }}</option>
					</select>
				</div>
			</div>
		</div>
	</div>
@endif

{{-- Empty State --}}
@if ( empty( $supports ) )
	<div class="px-3 py-6 text-center">
		<p class="text-sm text-gray-500">
			{{ __( 'This block has no style options.' ) }}
		</p>
	</div>
@endif
