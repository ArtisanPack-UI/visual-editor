{{--
 * Site Editor Layout Component
 *
 * The persistent layout shell for the site editor, providing a left
 * sidebar with navigation and a main content area for each section.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	{{ $attributes->merge( [ 'class' => 'flex h-screen bg-base-200' ] ) }}
	aria-label="{{ __( 'visual-editor::ve.site_editor' ) }}"
>
	{{-- Left sidebar navigation --}}
	<nav
		class="flex flex-col shrink-0 border-r border-base-300 bg-base-100 overflow-y-auto"
		style="width: {{ $sidebarWidth }}"
		aria-label="{{ __( 'visual-editor::ve.site_editor_navigation' ) }}"
	>
		{{-- Back to hub --}}
		<div class="flex items-center gap-2 px-4 py-3 border-b border-base-300">
			<a
				href="{{ $hubUrl() }}"
				class="flex items-center gap-2 text-sm font-medium text-base-content/70 hover:text-base-content transition-colors"
				aria-label="{{ __( 'visual-editor::ve.back_to_hub' ) }}"
			>
				<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
					<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
				</svg>
				<span>{{ $sectionTitle ?: __( 'visual-editor::ve.site_editor' ) }}</span>
			</a>
		</div>

		{{-- Navigation items --}}
		<ul class="flex-1 py-2 px-2 space-y-1" role="list">
			@foreach ( $navItems as $item )
				<li>
					<a
						href="{{ $item['url'] ?? '#' }}"
						class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
							{{ ( $item['slug'] ?? '' ) === $activeSection
								? 'bg-primary/10 text-primary font-medium'
								: 'text-base-content/70 hover:bg-base-200 hover:text-base-content' }}"
						@if ( ( $item['slug'] ?? '' ) === $activeSection )
							aria-current="page"
						@endif
					>
						@if ( ! empty( $item['icon'] ) )
							<span class="shrink-0 w-5 h-5" aria-hidden="true">{!! $item['icon'] !!}</span>
						@endif
						<span class="flex-1">{{ $item['label'] ?? '' }}</span>
						@if ( isset( $item['count'] ) )
							<span class="text-xs text-base-content/50 tabular-nums">{{ $item['count'] }}</span>
						@endif
					</a>
				</li>
			@endforeach
		</ul>

		{{-- Context-specific action button --}}
		@if ( $actionUrl && $actionLabel )
			<div class="p-3 border-t border-base-300">
				<a
					href="{{ $actionUrl }}"
					class="flex items-center justify-center gap-2 w-full px-4 py-2 text-sm font-medium text-primary-content bg-primary rounded-lg hover:bg-primary/90 transition-colors"
				>
					<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
					</svg>
					{{ $actionLabel }}
				</a>
			</div>
		@endif
	</nav>

	{{-- Main content area --}}
	<main class="flex-1 min-w-0 overflow-y-auto">
		{{ $slot }}
	</main>
</div>
