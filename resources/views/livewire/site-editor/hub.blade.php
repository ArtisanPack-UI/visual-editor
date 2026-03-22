{{--
 * Site Editor Hub Page
 *
 * Card-based dashboard showing sections for Global Styles,
 * Templates, Template Parts, and Patterns.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Livewire\SiteEditor
 *
 * @since      1.0.0
 --}}

<div class="min-h-screen bg-base-200">
	<div class="max-w-5xl mx-auto px-6 py-12">
		{{-- Header --}}
		<div class="mb-10">
			<h1 class="text-3xl font-bold text-base-content">
				{{ __( 'visual-editor::ve.hub_welcome' ) }}
			</h1>
			<p class="mt-2 text-base-content/60">
				{{ __( 'visual-editor::ve.hub_welcome_description' ) }}
			</p>
		</div>

		{{-- Cards grid --}}
		<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
			@foreach ( $cards as $card )
				<a
					href="{{ $card['url'] ?? '#' }}"
					class="group flex gap-4 p-6 bg-base-100 rounded-xl border border-base-300 shadow-sm hover:shadow-md hover:border-primary/30 transition-all"
				>
					{{-- Icon --}}
					<div class="shrink-0 flex items-center justify-center w-14 h-14 rounded-lg bg-primary/10 text-primary group-hover:bg-primary/20 transition-colors">
						{!! $card['icon'] ?? '' !!}
					</div>

					{{-- Content --}}
					<div class="flex-1 min-w-0">
						<div class="flex items-center gap-2">
							<h2 class="text-lg font-semibold text-base-content group-hover:text-primary transition-colors">
								{{ $card['label'] ?? '' }}
							</h2>
							@if ( isset( $card['count'] ) && null !== $card['count'] )
								<span class="text-xs text-base-content/50 tabular-nums">
									{{ trans_choice( 'visual-editor::ve.hub_items_count', $card['count'], [ 'count' => $card['count'] ] ) }}
								</span>
							@endif
						</div>
						<p class="mt-1 text-sm text-base-content/60">
							{{ $card['description'] ?? '' }}
						</p>
					</div>

					{{-- Chevron --}}
					<div class="shrink-0 flex items-center text-base-content/30 group-hover:text-primary/60 transition-colors">
						<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
							<path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
						</svg>
					</div>
				</a>
			@endforeach
		</div>
	</div>
</div>
