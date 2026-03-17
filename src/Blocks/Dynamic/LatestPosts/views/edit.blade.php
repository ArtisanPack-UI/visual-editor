@php
	$displayTemplate   = $content['displayTemplate'] ?? 'list';
	$numberOfPosts     = max( 1, min( 100, (int) ( $content['numberOfPosts'] ?? 5 ) ) );
	$orderBy           = $content['orderBy'] ?? 'date';
	$order             = $content['order'] ?? 'desc';
	$offset            = max( 0, (int) ( $content['offset'] ?? 0 ) );
	$showFeaturedImage = $content['showFeaturedImage'] ?? true;
	$showExcerpt       = $content['showExcerpt'] ?? true;
	$showDate          = $content['showDate'] ?? true;
	$showAuthor        = $content['showAuthor'] ?? false;
	$excerptLength     = max( 0, (int) ( $content['excerptLength'] ?? 25 ) );
	$gap               = $styles['gap'] ?? '1rem';
	$imageAspectRatio  = $styles['imageAspectRatio'] ?? '16/9';

	$columnsData = $content['columns'] ?? [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ];
	if ( is_array( $columnsData ) ) {
		$colMode     = $columnsData['mode'] ?? 'global';
		$desktopCols = ( 'responsive' === $colMode ) ? ( $columnsData['desktop'] ?? 3 ) : ( $columnsData['global'] ?? $columnsData['desktop'] ?? 3 );
		$tabletCols  = ( 'responsive' === $colMode ) ? ( $columnsData['tablet'] ?? 2 ) : $desktopCols;
		$mobileCols  = ( 'responsive' === $colMode ) ? ( $columnsData['mobile'] ?? 1 ) : $desktopCols;
	} else {
		$desktopCols = (int) $columnsData;
		$tabletCols  = $desktopCols;
		$mobileCols  = $desktopCols;
	}

	$sampleTitles = [
		'Getting Started with Laravel',
		'Advanced Eloquent Techniques',
		'Building APIs with Sanctum',
		'Tailwind CSS Best Practices',
		'Livewire Components Deep Dive',
		'Testing with Pest',
		'Database Migrations Guide',
		'Queue Workers Explained',
		'Blade Templates Mastery',
		'Deploy to Production',
	];

	$totalNeeded = $numberOfPosts + $offset;
	$allPosts    = [];
	for ( $i = 1; $i <= $totalNeeded; $i++ ) {
		$allPosts[] = [
			'title'   => $sampleTitles[ ( $i - 1 ) % count( $sampleTitles ) ],
			'excerpt' => __( 'visual-editor::ve.sample_post_excerpt' ),
			'date'    => now()->subDays( $i ),
			'dateStr' => now()->subDays( $i )->format( 'M j, Y' ),
			'author'  => __( 'visual-editor::ve.sample_author' ),
		];
	}

	if ( 'random' === $orderBy ) {
		shuffle( $allPosts );
	} elseif ( 'title' === $orderBy ) {
		usort( $allPosts, fn ( $a, $b ) => strcmp( $a['title'], $b['title'] ) );
		if ( 'desc' === $order ) {
			$allPosts = array_reverse( $allPosts );
		}
	} elseif ( 'date' === $orderBy && 'asc' === $order ) {
		$allPosts = array_reverse( $allPosts );
	}

	$samplePosts = array_slice( $allPosts, $offset, $numberOfPosts );
	$gridId      = 've-latest-posts-' . Illuminate\Support\Str::uuid()->toString();
@endphp

<div class="ve-block ve-block-latest-posts ve-block-editing ve-block-dynamic-preview">
	<nav aria-label="{{ __( 'visual-editor::ve.latest_posts' ) }}">
		@if ( empty( $samplePosts ) )
			<p style="color: #9ca3af; text-align: center; padding: 2rem;">{{ __( 'visual-editor::ve.no_posts_found' ) }}</p>
		@elseif ( 'list' === $displayTemplate )
			<ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: {{ $gap }};">
				@foreach ( $samplePosts as $post )
					<li style="display: flex; gap: 1rem; align-items: flex-start;">
						@if ( $showFeaturedImage )
							<div style="aspect-ratio: {{ $imageAspectRatio }}; width: 150px; min-width: 150px; background-color: #e5e7eb; border-radius: 4px;"></div>
						@endif
						<div>
							<span style="font-weight: 600; font-size: 1.1em;">{{ $post['title'] }}</span>
							@if ( $showDate || $showAuthor )
								<div style="font-size: 0.85em; color: #6b7280; margin-top: 0.25rem;">
									@if ( $showDate ) <time datetime="{{ $post['date']->toIso8601String() }}">{{ $post['dateStr'] }}</time> @endif
									@if ( $showDate && $showAuthor ) · @endif
									@if ( $showAuthor ) <span>{{ $post['author'] }}</span> @endif
								</div>
							@endif
							@if ( $showExcerpt )
								<p style="margin: 0.5rem 0 0; color: #374151; font-size: 0.9em; line-height: 1.5;">{{ Illuminate\Support\Str::words( $post['excerpt'], $excerptLength ) }}</p>
							@endif
						</div>
					</li>
				@endforeach
			</ul>
		@else
			<style>
				#{{ $gridId }} { display: grid; grid-template-columns: repeat({{ $desktopCols }}, 1fr); gap: {{ $gap }}; }
				@@media (max-width: 1024px) { #{{ $gridId }} { grid-template-columns: repeat({{ $tabletCols }}, 1fr); } }
				@@media (max-width: 640px) { #{{ $gridId }} { grid-template-columns: repeat({{ $mobileCols }}, 1fr); } }
			</style>
			<div id="{{ $gridId }}">
				@foreach ( $samplePosts as $post )
					<article style="{{ 'cards' === $displayTemplate ? 'border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;' : '' }}">
						@if ( $showFeaturedImage )
							<div style="aspect-ratio: {{ $imageAspectRatio }}; background-color: #e5e7eb; border-radius: 4px; {{ 'grid' === $displayTemplate ? 'margin-bottom: 0.75rem;' : '' }}"></div>
						@endif
						<div style="{{ 'cards' === $displayTemplate ? 'padding: 1rem;' : '' }}">
							<span style="font-weight: 600; display: block;">{{ $post['title'] }}</span>
							@if ( $showDate || $showAuthor )
								<div style="font-size: 0.85em; color: #6b7280; margin-top: 0.25rem;">
									@if ( $showDate ) <time datetime="{{ $post['date']->toIso8601String() }}">{{ $post['dateStr'] }}</time> @endif
									@if ( $showDate && $showAuthor ) · @endif
									@if ( $showAuthor ) <span>{{ $post['author'] }}</span> @endif
								</div>
							@endif
							@if ( $showExcerpt )
								<p style="margin: 0.5rem 0 0; color: #374151; font-size: 0.9em; line-height: 1.5;">{{ Illuminate\Support\Str::words( $post['excerpt'], $excerptLength ) }}</p>
							@endif
						</div>
					</article>
				@endforeach
			</div>
		@endif
	</nav>
</div>
