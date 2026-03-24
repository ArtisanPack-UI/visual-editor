{{--
 * Style Preview: Patterns
 *
 * Renders active patterns grouped by category showing how global
 * style changes affect each pattern. Each pattern's block content
 * is pre-rendered via BlockRenderer.
 *
 * Expects $previewPatterns from the Livewire component as an array
 * grouped by category, each entry containing name, slug, and html.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div class="space-y-5">
	@foreach ( $previewPatterns as $category => $patterns )
		<div>
			<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
				{{ 'uncategorized' === $category ? __( 'visual-editor::ve.style_preview_category_uncategorized' ) : ucfirst( $category ) }}
			</h3>

			<div class="space-y-3">
				@foreach ( $patterns as $pattern )
					<div class="rounded-lg border border-gray-100 overflow-hidden">
						<div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-100">
							<span class="text-[11px] font-medium text-gray-500">{{ $pattern['name'] }}</span>
							<span class="text-[10px] text-gray-300">{{ $pattern['slug'] }}</span>
						</div>
						<div class="p-4 ve-preview-rendered">
							{!! $pattern['html'] !!}
						</div>
					</div>
				@endforeach
			</div>
		</div>
	@endforeach
</div>
