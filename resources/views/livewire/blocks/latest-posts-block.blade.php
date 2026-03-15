<div>
	<section aria-label="{{ __( 'visual-editor::ve.latest_posts' ) }}" class="ve-block ve-block-latest-posts">
		@if ( empty( $posts ) )
			<p class="ve-block-latest-posts-empty">
				{{ __( 'visual-editor::ve.no_posts_found' ) }}
			</p>
		@else
			@if ( 'list' === $displayTemplate )
				<ul class="ve-block-latest-posts-list" style="gap: {{ $gap }};">
					@foreach ( $posts as $post )
						<li class="ve-block-latest-posts-item" wire:key="post-{{ $post['id'] }}">
							@if ( $showFeaturedImage )
								@if ( $post['featured_image'] )
									<div class="ve-block-latest-posts-image" style="aspect-ratio: {{ $imageAspectRatio }};">
										<img src="{{ $post['featured_image'] }}" alt="{{ $post['title'] }}" loading="lazy" />
									</div>
								@else
									<div class="ve-block-latest-posts-image ve-block-latest-posts-image-placeholder" style="aspect-ratio: {{ $imageAspectRatio }}; width: 150px; min-width: 150px; background-color: var(--ve-placeholder-bg, #e5e7eb);"></div>
								@endif
							@endif
							<div class="ve-block-latest-posts-content">
								<a href="{{ $post['url'] }}" class="ve-block-latest-posts-title">
									{{ $post['title'] }}
								</a>
								@if ( $showDate || $showAuthor )
									<div class="ve-block-latest-posts-meta">
										@if ( $showDate )
											<time datetime="{{ $post['date_iso'] ?? '' }}">{{ $post['date'] }}</time>
										@endif
										@if ( $showAuthor )
											<span class="ve-block-latest-posts-author">{{ $post['author'] }}</span>
										@endif
									</div>
								@endif
								@if ( $showExcerpt )
									<p class="ve-block-latest-posts-excerpt">
										{{ \Illuminate\Support\Str::words( $post['excerpt'], $excerptLength ) }}
									</p>
								@endif
							</div>
						</li>
					@endforeach
				</ul>
			@elseif ( 'grid' === $displayTemplate )
				<div
					class="ve-block-latest-posts-grid"
					style="display: grid; grid-template-columns: repeat({{ $columnsCount }}, 1fr); gap: {{ $gap }};"
				>
					@foreach ( $posts as $post )
						<article class="ve-block-latest-posts-grid-item" wire:key="post-{{ $post['id'] }}">
							@if ( $showFeaturedImage && $post['featured_image'] )
								<div class="ve-block-latest-posts-image" style="aspect-ratio: {{ $imageAspectRatio }};">
									<img src="{{ $post['featured_image'] }}" alt="{{ $post['title'] }}" loading="lazy" />
								</div>
							@elseif ( $showFeaturedImage )
								<div class="ve-block-latest-posts-image ve-block-latest-posts-image-placeholder" style="aspect-ratio: {{ $imageAspectRatio }}; background-color: var(--ve-placeholder-bg, #e5e7eb);"></div>
							@endif
							<div class="ve-block-latest-posts-content">
								<a href="{{ $post['url'] }}" class="ve-block-latest-posts-title">
									{{ $post['title'] }}
								</a>
								@if ( $showDate || $showAuthor )
									<div class="ve-block-latest-posts-meta">
										@if ( $showDate )
											<time datetime="{{ $post['date_iso'] ?? '' }}">{{ $post['date'] }}</time>
										@endif
										@if ( $showAuthor )
											<span class="ve-block-latest-posts-author">{{ $post['author'] }}</span>
										@endif
									</div>
								@endif
								@if ( $showExcerpt )
									<p class="ve-block-latest-posts-excerpt">
										{{ \Illuminate\Support\Str::words( $post['excerpt'], $excerptLength ) }}
									</p>
								@endif
							</div>
						</article>
					@endforeach
				</div>
			@elseif ( 'cards' === $displayTemplate )
				<div
					class="ve-block-latest-posts-cards"
					style="display: grid; grid-template-columns: repeat({{ $columnsCount }}, 1fr); gap: {{ $gap }};"
				>
					@foreach ( $posts as $post )
						<article class="ve-block-latest-posts-card" wire:key="post-{{ $post['id'] }}">
							@if ( $showFeaturedImage && $post['featured_image'] )
								<div class="ve-block-latest-posts-card-image" style="aspect-ratio: {{ $imageAspectRatio }};">
									<img src="{{ $post['featured_image'] }}" alt="{{ $post['title'] }}" loading="lazy" />
								</div>
							@elseif ( $showFeaturedImage )
								<div class="ve-block-latest-posts-card-image ve-block-latest-posts-image-placeholder" style="aspect-ratio: {{ $imageAspectRatio }}; background-color: var(--ve-placeholder-bg, #e5e7eb);"></div>
							@endif
							<div class="ve-block-latest-posts-card-body">
								<a href="{{ $post['url'] }}" class="ve-block-latest-posts-title">
									{{ $post['title'] }}
								</a>
								@if ( $showDate || $showAuthor )
									<div class="ve-block-latest-posts-meta">
										@if ( $showDate )
											<time datetime="{{ $post['date_iso'] ?? '' }}">{{ $post['date'] }}</time>
										@endif
										@if ( $showAuthor )
											<span class="ve-block-latest-posts-author">{{ $post['author'] }}</span>
										@endif
									</div>
								@endif
								@if ( $showExcerpt )
									<p class="ve-block-latest-posts-excerpt">
										{{ \Illuminate\Support\Str::words( $post['excerpt'], $excerptLength ) }}
									</p>
								@endif
							</div>
						</article>
					@endforeach
				</div>
			@endif
		@endif
	</section>
</div>
