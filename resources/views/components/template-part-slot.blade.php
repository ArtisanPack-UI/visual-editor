{{--
 * Template Part Slot Component
 *
 * A visual placeholder in the template canvas representing a template
 * part area. Shows the assigned part content with an edit overlay,
 * or a picker when empty.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		showPicker: false,
		hovered: false,
	}"
	{{ $attributes->merge( [ 'class' => 'relative group border-2 border-dashed transition-colors' ] ) }}
	:class="{
		'border-primary/50 bg-primary/5': hovered && ! {{ Js::from( $isEditing ) }},
		'border-base-300': ! hovered && ! {{ Js::from( $isEditing ) }},
		'border-primary bg-primary/10': {{ Js::from( $isEditing ) }},
	}"
	x-on:mouseenter="hovered = true"
	x-on:mouseleave="hovered = false"
	role="region"
	aria-label="{{ $label ?? $areaLabel() }}"
	data-template-area="{{ $area }}"
>
	{{-- Area Label Badge --}}
	<div class="absolute top-0 left-3 -translate-y-1/2 z-10">
		<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-base-100 border border-base-300 text-base-content/70 shadow-sm">
			<svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
				@if ( 'header' === $area )
					<path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18" />
					<path stroke-linecap="round" stroke-linejoin="round" d="M3 9h18" />
				@elseif ( 'footer' === $area )
					<path stroke-linecap="round" stroke-linejoin="round" d="M3 15h18" />
					<path stroke-linecap="round" stroke-linejoin="round" d="M3 19.5h18" />
				@elseif ( 'sidebar' === $area )
					<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
				@else
					<path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
				@endif
			</svg>
			{{ $areaLabel() }}
		</span>
	</div>

	@if ( $hasAssignment() )
		{{-- Assigned Part Content --}}
		<div class="p-4 pt-5">
			<div class="text-sm text-base-content/60 mb-2">
				{{ $assignedName ?? $assignedSlug }}
			</div>

			{{-- Part content slot --}}
			{{ $slot }}
		</div>

		{{-- Hover Overlay with Actions --}}
		<div
			class="absolute inset-0 flex items-center justify-center gap-2 bg-base-100/80 backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity rounded"
			x-show="! {{ Js::from( $isEditing ) }}"
		>
			@if ( ! $isLocked )
				<button
					type="button"
					class="btn btn-primary btn-sm"
					x-on:click="$dispatch( 've-template-part-edit', { slug: {{ Js::from( $assignedSlug ) }} } )"
					aria-label="{{ __( 'visual-editor::ve.template_edit_part' ) }}"
				>
					<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
						<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
					</svg>
					{{ __( 'visual-editor::ve.template_edit_part' ) }}
				</button>
			@endif

			<button
				type="button"
				class="btn btn-ghost btn-sm"
				x-on:click="showPicker = true"
				aria-label="{{ __( 'visual-editor::ve.template_replace_part' ) }}"
			>
				<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
				</svg>
				{{ __( 'visual-editor::ve.template_replace_part' ) }}
			</button>

			@if ( ! $isLocked )
				<button
					type="button"
					class="btn btn-ghost btn-sm text-error"
					x-on:click="$dispatch( 've-template-part-cleared', { area: {{ Js::from( $area ) }} } )"
					aria-label="{{ __( 'visual-editor::ve.template_clear_part' ) }}"
				>
					<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
						<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
					</svg>
				</button>
			@endif
		</div>
	@else
		{{-- Empty Slot: Part Picker --}}
		<div class="p-6 pt-7 flex flex-col items-center justify-center text-center min-h-[80px]">
			<p class="text-sm text-base-content/50 mb-3">
				{{ __( 'visual-editor::ve.template_choose_part', [ 'area' => $areaLabel() ] ) }}
			</p>

			<button
				type="button"
				class="btn btn-outline btn-sm"
				x-on:click="showPicker = ! showPicker"
				:aria-expanded="showPicker ? 'true' : 'false'"
			>
				<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
					<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
				</svg>
				{{ __( 'visual-editor::ve.template_add_part' ) }}
			</button>
		</div>
	@endif

	{{-- Part Picker Dropdown --}}
	<div
		x-show="showPicker"
		x-transition:enter="transition ease-out duration-150"
		x-transition:enter-start="opacity-0 scale-95"
		x-transition:enter-end="opacity-100 scale-100"
		x-transition:leave="transition ease-in duration-100"
		x-transition:leave-start="opacity-100 scale-100"
		x-transition:leave-end="opacity-0 scale-95"
		x-on:click.outside="showPicker = false"
		x-on:keydown.escape.window="showPicker = false"
		class="absolute left-3 right-3 bottom-full mb-1 z-20 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
		x-cloak
		role="listbox"
		aria-label="{{ __( 'visual-editor::ve.template_select_part' ) }}"
	>
		@if ( ! empty( $availableParts ) )
			@foreach ( $availableParts as $part )
				<button
					type="button"
					class="w-full text-left px-3 py-2 text-sm hover:bg-base-200 transition-colors flex items-center justify-between first:rounded-t-lg last:rounded-b-lg"
					x-on:click="$dispatch( 've-template-part-assigned', { area: {{ Js::from( $area ) }}, slug: {{ Js::from( $part['slug'] ?? '' ) }} } ); showPicker = false;"
					role="option"
				>
					<div>
						<div class="font-medium">{{ $part['name'] ?? $part['slug'] ?? '' }}</div>
						@if ( ! empty( $part['description'] ) )
							<div class="text-xs text-base-content/50 mt-0.5">{{ $part['description'] }}</div>
						@endif
					</div>
					@if ( ( $part['slug'] ?? '' ) === $assignedSlug )
						<svg class="w-4 h-4 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
						</svg>
					@endif
				</button>
			@endforeach
		@else
			<div class="px-3 py-4 text-sm text-base-content/50 text-center">
				{{ __( 'visual-editor::ve.template_no_parts_available' ) }}
			</div>
		@endif
	</div>

	@if ( function_exists( 'doAction' ) )
		@action('ap.visualEditor.templatePartSlot.rendered', $area)
	@endif
</div>
