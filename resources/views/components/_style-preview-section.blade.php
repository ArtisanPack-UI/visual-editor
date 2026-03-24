{{--
 * Style Preview Section Component
 *
 * A collapsible section wrapper for the global styles preview panel.
 * Used to organize different preview categories (style overview,
 * template parts, patterns, and future block styles).
 *
 * @param string $title   The section heading text.
 * @param string $id      Unique identifier for Alpine toggle state.
 * @param bool   $open    Whether the section starts expanded (default true).
 * @param string $badge   Optional badge text (e.g. item count).
 * @param string $content The Blade view to include as section content.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	x-data="{ open: {{ $open ?? true ? 'true' : 'false' }} }"
	class="bg-white rounded-xl border border-gray-200 overflow-hidden"
>
	<button
		type="button"
		class="flex items-center justify-between w-full px-5 py-3 text-left hover:bg-gray-50 transition-colors"
		x-on:click="open = ! open"
		:aria-expanded="open"
		aria-controls="ve-preview-section-{{ $id }}"
	>
		<div class="flex items-center gap-2">
			<h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">
				{{ $title }}
			</h2>
			@if ( ! empty( $badge ) )
				<span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-medium text-gray-400 bg-gray-100 rounded-full min-w-[1.25rem]">
					{{ $badge }}
				</span>
			@endif
		</div>
		<svg
			class="w-4 h-4 text-gray-400 transition-transform duration-200"
			:class="open ? 'rotate-180' : ''"
			fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
			aria-hidden="true" focusable="false"
		>
			<path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
		</svg>
	</button>

	<div
		id="ve-preview-section-{{ $id }}"
		x-show="open"
		x-collapse
	>
		<div class="px-5 pb-5">
			@include( $content )
		</div>
	</div>
</div>
