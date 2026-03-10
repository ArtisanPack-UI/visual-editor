{{--
 * Responsive Range Control Component
 *
 * A range control with a global/responsive toggle. In global mode,
 * a single slider controls all breakpoints. In responsive mode,
 * separate sliders appear for desktop, tablet, and mobile.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$desktopSvg   = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25A2.25 2.25 0 0 1 5.25 3h13.5A2.25 2.25 0 0 1 21 5.25Z" /></svg>';
	$tabletSvg    = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.5a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>';
	$mobileSvg    = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" /></svg>';
	$responsiveSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>';
@endphp

<div
	id="{{ $uuid }}"
	x-data="{
		mode: {{ Js::from( $value['mode'] ?? 'global' ) }},
		globalVal: {{ Js::from( $value['global'] ?? $value['desktop'] ?? $min ) }},
		desktopVal: {{ Js::from( $value['desktop'] ?? $min ) }},
		tabletVal: {{ Js::from( $value['tablet'] ?? $min ) }},
		mobileVal: {{ Js::from( $value['mobile'] ?? $min ) }},
		toggleMode() {
			this.mode = this.mode === 'global' ? 'responsive' : 'global'
			this.dispatch()
		},
		dispatch() {
			$dispatch( 've-responsive-range-change', {
				values: {
					mode: this.mode,
					global: this.globalVal,
					desktop: this.desktopVal,
					tablet: this.tabletVal,
					mobile: this.mobileVal
				}
			} )
		}
	}"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-2' ] ) }}
	role="group"
	@if ( $label )
		aria-label="{{ $label }}"
	@endif
>
	{{-- Header: label + responsive toggle --}}
	@if ( $label )
		<div class="flex items-center justify-between">
			<label class="text-xs font-medium text-gray-600">
				{{ $label }}
			</label>
			<button
				type="button"
				class="p-1 rounded transition-colors"
				:class="mode === 'responsive' ? 'bg-primary/20 text-primary' : 'text-base-content/50 hover:text-base-content/80'"
				x-on:click="toggleMode()"
				:title="mode === 'global' ? '{{ __( 'visual-editor::ve.use_responsive' ) }}' : '{{ __( 'visual-editor::ve.use_global' ) }}'"
				:aria-label="mode === 'global' ? '{{ __( 'visual-editor::ve.use_responsive' ) }}' : '{{ __( 'visual-editor::ve.use_global' ) }}'"
				:aria-pressed="mode === 'responsive' ? 'true' : 'false'"
			>
				{!! $responsiveSvg !!}
			</button>
		</div>
	@endif

	{{-- Global mode: single slider --}}
	<div x-show="mode === 'global'" x-cloak>
		<div class="flex items-center gap-2">
			<input
				type="range"
				class="range range-sm range-primary flex-1"
				x-model.number="globalVal"
				x-on:input="dispatch()"
				min="{{ $min }}"
				max="{{ $max }}"
				step="{{ $step }}"
			/>
			<input
				type="number"
				class="input input-bordered input-sm w-16 text-center"
				x-model.number="globalVal"
				x-on:input="dispatch()"
				min="{{ $min }}"
				max="{{ $max }}"
				step="{{ $step }}"
			/>
		</div>
	</div>

	{{-- Responsive mode: per-breakpoint sliders --}}
	<div x-show="mode === 'responsive'" x-cloak class="flex flex-col gap-2">
		{{-- Desktop --}}
		<div class="flex items-center gap-2">
			<span class="shrink-0 text-base-content/60" title="{{ __( 'visual-editor::ve.desktop' ) }}">{!! $desktopSvg !!}</span>
			<input
				type="range"
				class="range range-sm range-primary flex-1"
				x-model.number="desktopVal"
				x-on:input="dispatch()"
				min="{{ $min }}"
				max="{{ $max }}"
				step="{{ $step }}"
				aria-label="{{ __( 'visual-editor::ve.desktop' ) }}"
			/>
			<input
				type="number"
				class="input input-bordered input-sm w-16 text-center"
				x-model.number="desktopVal"
				x-on:input="dispatch()"
				min="{{ $min }}"
				max="{{ $max }}"
				step="{{ $step }}"
			/>
		</div>

		{{-- Tablet --}}
		<div class="flex items-center gap-2">
			<span class="shrink-0 text-base-content/60" title="{{ __( 'visual-editor::ve.tablet' ) }}">{!! $tabletSvg !!}</span>
			<input
				type="range"
				class="range range-sm range-primary flex-1"
				x-model.number="tabletVal"
				x-on:input="dispatch()"
				min="{{ $min }}"
				max="{{ $max }}"
				step="{{ $step }}"
				aria-label="{{ __( 'visual-editor::ve.tablet' ) }}"
			/>
			<input
				type="number"
				class="input input-bordered input-sm w-16 text-center"
				x-model.number="tabletVal"
				x-on:input="dispatch()"
				min="{{ $min }}"
				max="{{ $max }}"
				step="{{ $step }}"
			/>
		</div>

		{{-- Mobile --}}
		<div class="flex items-center gap-2">
			<span class="shrink-0 text-base-content/60" title="{{ __( 'visual-editor::ve.mobile' ) }}">{!! $mobileSvg !!}</span>
			<input
				type="range"
				class="range range-sm range-primary flex-1"
				x-model.number="mobileVal"
				x-on:input="dispatch()"
				min="{{ $min }}"
				max="{{ $max }}"
				step="{{ $step }}"
				aria-label="{{ __( 'visual-editor::ve.mobile' ) }}"
			/>
			<input
				type="number"
				class="input input-bordered input-sm w-16 text-center"
				x-model.number="mobileVal"
				x-on:input="dispatch()"
				min="{{ $min }}"
				max="{{ $max }}"
				step="{{ $step }}"
			/>
		</div>
	</div>

	@if ( $hint )
		<div class="fieldset-label">{{ $hint }}</div>
	@endif
</div>
