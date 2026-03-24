{{--
 * Style Preview: Template Parts
 *
 * Renders active template parts grouped by area (header, footer,
 * sidebar, custom) showing how global style changes affect each part.
 * Each part's block content is pre-rendered via BlockRenderer.
 *
 * Expects $previewParts from the Livewire component as an array
 * grouped by area, each entry containing name, slug, and html.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

@php
	$areaLabels = [
		'header'  => __( 'visual-editor::ve.style_preview_area_header' ),
		'footer'  => __( 'visual-editor::ve.style_preview_area_footer' ),
		'sidebar' => __( 'visual-editor::ve.style_preview_area_sidebar' ),
		'custom'  => __( 'visual-editor::ve.style_preview_area_custom' ),
	];
	$areaOrder = [ 'header', 'footer', 'sidebar', 'custom' ];
@endphp

<div class="space-y-5">
	@foreach ( $areaOrder as $area )
		@if ( isset( $previewParts[ $area ] ) )
			<div>
				<h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
					{{ $areaLabels[ $area ] ?? ucfirst( $area ) }}
				</h3>

				<div class="space-y-3">
					@foreach ( $previewParts[ $area ] as $part )
						<div class="rounded-lg border border-gray-100 overflow-hidden">
							<div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-b border-gray-100">
								<span class="text-[11px] font-medium text-gray-500">{{ $part['name'] }}</span>
								<span class="text-[10px] text-gray-300">{{ $part['slug'] }}</span>
							</div>
							<div class="p-4 ve-preview-rendered">
								{!! $part['html'] !!}
							</div>
						</div>
					@endforeach
				</div>
			</div>
		@endif
	@endforeach
</div>
