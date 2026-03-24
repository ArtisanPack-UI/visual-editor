{{--
 * Style Preview Context: Default
 *
 * Style overview showing color swatches, typography samples,
 * and spacing scale visualisation. Uses CSS custom properties for
 * real-time updates. Included inside a collapsible section wrapper.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div class="space-y-5">
	{{-- Color swatches preview --}}
	<div>
		<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
			{{ __( 'visual-editor::ve.global_styles_preview_colors' ) }}
		</h3>
		<div class="grid grid-cols-4 gap-3 sm:grid-cols-6" x-data>
			<template x-for="entry in Alpine.store( 'editor' )?.globalStyles?.palette || []" :key="entry.slug">
				<div class="flex flex-col items-center gap-1.5">
					<div
						class="w-12 h-12 rounded-lg border border-gray-200 shadow-sm"
						x-bind:style="'background-color:' + entry.color"
						x-bind:title="entry.name"
					></div>
					<span class="text-[10px] text-gray-400 truncate max-w-[60px]" x-text="entry.name"></span>
				</div>
			</template>
		</div>
	</div>

	{{-- Typography preview --}}
	<div>
		<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
			{{ __( 'visual-editor::ve.global_styles_preview_typography' ) }}
		</h3>
		<div class="space-y-3 text-gray-900">
			<h1 style="font-family: var(--ve-font-heading, inherit); font-size: var(--ve-text-h1-font-size, 2.25rem); font-weight: var(--ve-text-h1-font-weight, 800); line-height: var(--ve-text-h1-line-height, 1.2);">
				{{ __( 'visual-editor::ve.global_styles_preview_heading_1' ) }}
			</h1>
			<h2 style="font-family: var(--ve-font-heading, inherit); font-size: var(--ve-text-h2-font-size, 1.875rem); font-weight: var(--ve-text-h2-font-weight, 700); line-height: var(--ve-text-h2-line-height, 1.3);">
				{{ __( 'visual-editor::ve.global_styles_preview_heading_2' ) }}
			</h2>
			<h3 style="font-family: var(--ve-font-heading, inherit); font-size: var(--ve-text-h3-font-size, 1.5rem); font-weight: var(--ve-text-h3-font-weight, 600); line-height: var(--ve-text-h3-line-height, 1.4);">
				{{ __( 'visual-editor::ve.global_styles_preview_heading_3' ) }}
			</h3>
			<p style="font-family: var(--ve-font-body, inherit); font-size: var(--ve-text-body-font-size, 1rem); line-height: var(--ve-text-body-line-height, 1.6);">
				{{ __( 'visual-editor::ve.global_styles_preview_body_text' ) }}
			</p>
			<p style="font-family: var(--ve-font-body, inherit); font-size: var(--ve-text-small-font-size, 0.875rem); line-height: var(--ve-text-small-line-height, 1.5);" class="text-gray-400">
				{{ __( 'visual-editor::ve.global_styles_preview_small_text' ) }}
			</p>
		</div>
	</div>

	{{-- Spacing preview --}}
	<div>
		<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
			{{ __( 'visual-editor::ve.global_styles_preview_spacing' ) }}
		</h3>
		<div class="space-y-2" x-data>
			<template x-for="step in Alpine.store( 'editor' )?.globalStyles?.spacing?.scale || []" :key="step.slug">
				<div class="flex items-center gap-3">
					<span class="w-8 text-xs text-gray-400 text-right tabular-nums" x-text="step.slug"></span>
					<div
						class="h-4 rounded bg-blue-100 border border-blue-200"
						x-bind:style="'width:' + step.value"
					></div>
					<span class="text-xs text-gray-300 tabular-nums" x-text="step.value"></span>
				</div>
			</template>
		</div>
	</div>
</div>
