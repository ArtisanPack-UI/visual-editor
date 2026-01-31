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
	@php
		$stateOptions = [
			[ 'id' => 'default', 'name' => __( 'Default' ) ],
			[ 'id' => 'hover', 'name' => __( 'Hover' ) ],
			[ 'id' => 'focus', 'name' => __( 'Focus' ) ],
			[ 'id' => 'active', 'name' => __( 'Active' ) ],
		];
	@endphp
	<x-artisanpack-select
		:label="__( 'State' )"
		:options="$stateOptions"
		option-value="id"
		option-label="name"
		wire:model.live="activeState"
	/>
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
				<div class="grid grid-cols-2 gap-2">
					<x-artisanpack-input
						:label="__( 'Top' )"
						:value="$styleValue( 'sizing', 'padding_top' )"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.padding_top', $event.target.value )"
						placeholder="0"
					/>
					<x-artisanpack-input
						:label="__( 'Right' )"
						:value="$styleValue( 'sizing', 'padding_right' )"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.padding_right', $event.target.value )"
						placeholder="0"
					/>
					<x-artisanpack-input
						:label="__( 'Bottom' )"
						:value="$styleValue( 'sizing', 'padding_bottom' )"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.padding_bottom', $event.target.value )"
						placeholder="0"
					/>
					<x-artisanpack-input
						:label="__( 'Left' )"
						:value="$styleValue( 'sizing', 'padding_left' )"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.padding_left', $event.target.value )"
						placeholder="0"
					/>
				</div>
			</div>

			{{-- Margin --}}
			<div>
				<label class="mb-1 block text-xs font-medium text-gray-600">{{ __( 'Margin' ) }}</label>
				<div class="grid grid-cols-2 gap-2">
					<x-artisanpack-input
						:label="__( 'Top' )"
						:value="$styleValue( 'sizing', 'margin_top' )"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.margin_top', $event.target.value )"
						placeholder="0"
					/>
					<x-artisanpack-input
						:label="__( 'Right' )"
						:value="$styleValue( 'sizing', 'margin_right' )"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.margin_right', $event.target.value )"
						placeholder="0"
					/>
					<x-artisanpack-input
						:label="__( 'Bottom' )"
						:value="$styleValue( 'sizing', 'margin_bottom' )"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.margin_bottom', $event.target.value )"
						placeholder="0"
					/>
					<x-artisanpack-input
						:label="__( 'Left' )"
						:value="$styleValue( 'sizing', 'margin_left' )"
						wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.margin_left', $event.target.value )"
						placeholder="0"
					/>
				</div>
			</div>

			{{-- Width / Height --}}
			<div class="grid grid-cols-2 gap-2">
				<x-artisanpack-input
					:label="__( 'Width' )"
					:value="$styleValue( 'sizing', 'width' )"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.width', $event.target.value )"
					:placeholder="__( 'auto' )"
				/>
				<x-artisanpack-input
					:label="__( 'Height' )"
					:value="$styleValue( 'sizing', 'height' )"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.sizing.height', $event.target.value )"
					:placeholder="__( 'auto' )"
				/>
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
			@php
				$fontFamilyOptions = [
					[ 'id' => '', 'name' => __( 'Default' ) ],
					[ 'id' => 'sans-serif', 'name' => __( 'Sans Serif' ) ],
					[ 'id' => 'serif', 'name' => __( 'Serif' ) ],
					[ 'id' => 'monospace', 'name' => __( 'Monospace' ) ],
				];
			@endphp
			<x-artisanpack-select
				:label="__( 'Font Family' )"
				:options="$fontFamilyOptions"
				option-value="id"
				option-label="name"
				wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.typography.font_family', $event.target.value )"
				:selected="$styleValue( 'typography', 'font_family' )"
			/>

			{{-- Font Size / Font Weight --}}
			<div class="grid grid-cols-2 gap-2">
				<x-artisanpack-input
					:label="__( 'Font Size' )"
					:value="$styleValue( 'typography', 'font_size' )"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.typography.font_size', $event.target.value )"
					placeholder="16"
				/>
				@php
					$fontWeightOptions = [
						[ 'id' => '', 'name' => __( 'Default' ) ],
						[ 'id' => '100', 'name' => __( 'Thin' ) ],
						[ 'id' => '300', 'name' => __( 'Light' ) ],
						[ 'id' => '400', 'name' => __( 'Normal' ) ],
						[ 'id' => '500', 'name' => __( 'Medium' ) ],
						[ 'id' => '600', 'name' => __( 'Semibold' ) ],
						[ 'id' => '700', 'name' => __( 'Bold' ) ],
						[ 'id' => '900', 'name' => __( 'Black' ) ],
					];
				@endphp
				<x-artisanpack-select
					:label="__( 'Font Weight' )"
					:options="$fontWeightOptions"
					option-value="id"
					option-label="name"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.typography.font_weight', $event.target.value )"
					:selected="$styleValue( 'typography', 'font_weight' )"
				/>
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
			@php
				$positionTypeOptions = [
					[ 'id' => '', 'name' => __( 'Default' ) ],
					[ 'id' => 'relative', 'name' => __( 'Relative' ) ],
					[ 'id' => 'absolute', 'name' => __( 'Absolute' ) ],
					[ 'id' => 'fixed', 'name' => __( 'Fixed' ) ],
					[ 'id' => 'sticky', 'name' => __( 'Sticky' ) ],
				];
			@endphp
			<x-artisanpack-select
				:label="__( 'Position' )"
				:options="$positionTypeOptions"
				option-value="id"
				option-label="name"
				wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.type', $event.target.value )"
				:selected="$styleValue( 'position', 'type' )"
			/>

			{{-- Top / Right / Bottom / Left --}}
			<div class="grid grid-cols-2 gap-2">
				<x-artisanpack-input
					:label="__( 'Top' )"
					:value="$styleValue( 'position', 'top' )"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.top', $event.target.value )"
					:placeholder="__( 'auto' )"
				/>
				<x-artisanpack-input
					:label="__( 'Right' )"
					:value="$styleValue( 'position', 'right' )"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.right', $event.target.value )"
					:placeholder="__( 'auto' )"
				/>
				<x-artisanpack-input
					:label="__( 'Bottom' )"
					:value="$styleValue( 'position', 'bottom' )"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.bottom', $event.target.value )"
					:placeholder="__( 'auto' )"
				/>
				<x-artisanpack-input
					:label="__( 'Left' )"
					:value="$styleValue( 'position', 'left' )"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.position.left', $event.target.value )"
					:placeholder="__( 'auto' )"
				/>
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
			<x-artisanpack-colorpicker
				:label="__( 'Text Color' )"
				wire:model.live="styleTextColor"
			/>

			{{-- Background Color --}}
			<x-artisanpack-colorpicker
				:label="__( 'Background Color' )"
				wire:model.live="styleBackgroundColor"
			/>
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
			<x-artisanpack-colorpicker
				:label="__( 'Border Color' )"
				wire:model.live="styleBorderColor"
			/>

			{{-- Border Width --}}
			<x-artisanpack-input
				:label="__( 'Border Width' )"
				:value="$styleValue( 'borders', 'border_width' )"
				wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.borders.border_width', $event.target.value )"
				placeholder="0"
			/>

			{{-- Radius / Style --}}
			<div class="grid grid-cols-2 gap-2">
				<x-artisanpack-input
					:label="__( 'Radius' )"
					:value="$styleValue( 'borders', 'border_radius' )"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.borders.border_radius', $event.target.value )"
					placeholder="0"
				/>
				@php
					$borderStyleOptions = [
						[ 'id' => '', 'name' => __( 'Default' ) ],
						[ 'id' => 'solid', 'name' => __( 'Solid' ) ],
						[ 'id' => 'dashed', 'name' => __( 'Dashed' ) ],
						[ 'id' => 'dotted', 'name' => __( 'Dotted' ) ],
						[ 'id' => 'none', 'name' => __( 'None' ) ],
					];
				@endphp
				<x-artisanpack-select
					:label="__( 'Style' )"
					:options="$borderStyleOptions"
					option-value="id"
					option-label="name"
					wire:change="updateBlockSetting( 'styles.{{ $activeBreakpoint }}.{{ $activeState }}.borders.border_style', $event.target.value )"
					:selected="$styleValue( 'borders', 'border_style' )"
				/>
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
