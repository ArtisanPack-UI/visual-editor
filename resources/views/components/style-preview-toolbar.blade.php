{{--
 * Style Preview Toolbar Component
 *
 * Toolbar for the global styles preview panel providing responsive
 * viewport switching and before/after comparison toggle. Reads and
 * writes to the parent component's Alpine state (viewport, previewMode).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	{{ $attributes->merge( [ 'class' => 'flex items-center justify-between px-4 py-2 border-b border-gray-200 bg-white/80 backdrop-blur-sm' ] ) }}
>
	{{-- Left: Viewport switcher --}}
	<div
		class="flex items-center gap-0.5"
		role="radiogroup"
		aria-label="{{ __( 'visual-editor::ve.style_preview_viewport_label' ) }}"
		x-on:keydown.arrow-left.prevent="
			const viewports = [ 'desktop', 'tablet', 'mobile' ];
			const idx = viewports.indexOf( viewport );
			if ( idx > 0 ) { viewport = viewports[ idx - 1 ]; }
		"
		x-on:keydown.arrow-right.prevent="
			const viewports = [ 'desktop', 'tablet', 'mobile' ];
			const idx = viewports.indexOf( viewport );
			if ( idx < viewports.length - 1 ) { viewport = viewports[ idx + 1 ]; }
		"
	>
		{{-- Desktop --}}
		<button
			type="button"
			class="p-1.5 rounded-md transition-colors"
			:class="'desktop' === viewport ? 'bg-gray-200 text-gray-900' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'"
			x-on:click="viewport = 'desktop'"
			role="radio"
			:aria-checked="'desktop' === viewport"
			:tabindex="'desktop' === viewport ? 0 : -1"
			aria-label="{{ __( 'visual-editor::ve.device_desktop' ) }}"
		>
			<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
				<path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25A2.25 2.25 0 0 1 5.25 3h13.5A2.25 2.25 0 0 1 21 5.25Z" />
			</svg>
		</button>

		{{-- Tablet --}}
		<button
			type="button"
			class="p-1.5 rounded-md transition-colors"
			:class="'tablet' === viewport ? 'bg-gray-200 text-gray-900' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'"
			x-on:click="viewport = 'tablet'"
			role="radio"
			:aria-checked="'tablet' === viewport"
			:tabindex="'tablet' === viewport ? 0 : -1"
			aria-label="{{ __( 'visual-editor::ve.device_tablet' ) }}"
		>
			<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
				<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.5a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" />
			</svg>
		</button>

		{{-- Mobile --}}
		<button
			type="button"
			class="p-1.5 rounded-md transition-colors"
			:class="'mobile' === viewport ? 'bg-gray-200 text-gray-900' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100'"
			x-on:click="viewport = 'mobile'"
			role="radio"
			:aria-checked="'mobile' === viewport"
			:tabindex="'mobile' === viewport ? 0 : -1"
			aria-label="{{ __( 'visual-editor::ve.device_mobile' ) }}"
		>
			<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
				<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
			</svg>
		</button>
	</div>

	{{-- Right: Before/After toggle --}}
	<div class="flex items-center gap-2">
		<div
			class="flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5"
			role="radiogroup"
			aria-label="{{ __( 'visual-editor::ve.style_preview_compare_label' ) }}"
			x-on:keydown.arrow-left.prevent="switchToLivePreview(); $nextTick( () => $el.querySelector( '[aria-checked=true]' )?.focus() )"
			x-on:keydown.arrow-up.prevent="switchToLivePreview(); $nextTick( () => $el.querySelector( '[aria-checked=true]' )?.focus() )"
			x-on:keydown.arrow-right.prevent="switchToSavedPreview(); $nextTick( () => $el.querySelector( '[aria-checked=true]' )?.focus() )"
			x-on:keydown.arrow-down.prevent="switchToSavedPreview(); $nextTick( () => $el.querySelector( '[aria-checked=true]' )?.focus() )"
		>
			<button
				type="button"
				class="px-2 py-1 text-xs font-medium rounded-md transition-colors"
				:class="'live' === previewMode ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
				x-on:click="switchToLivePreview()"
				role="radio"
				:aria-checked="'live' === previewMode"
				:tabindex="'live' === previewMode ? 0 : -1"
			>{{ __( 'visual-editor::ve.style_preview_after' ) }}</button>

			<button
				type="button"
				class="px-2 py-1 text-xs font-medium rounded-md transition-colors"
				:class="'saved' === previewMode ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
				x-on:click="switchToSavedPreview()"
				role="radio"
				:aria-checked="'saved' === previewMode"
				:tabindex="'saved' === previewMode ? 0 : -1"
			>{{ __( 'visual-editor::ve.style_preview_before' ) }}</button>
		</div>
	</div>
</div>
