@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$validSeparators = [ 'arrow-right', 'chevron-right', 'chevron-double-right', 'long-arrow-right' ];

	$separatorIcon = (string) ( $attributes['separatorIcon'] ?? 'chevron-right' );

	if ( ! in_array( $separatorIcon, $validSeparators, true ) ) {
		$separatorIcon = 'chevron-right';
	}

	$separatorPaths = [
		'arrow-right'          => 'M5 12h14m-6-6 6 6-6 6',
		'chevron-right'        => 'm9 6 6 6-6 6',
		'chevron-double-right' => 'm6 6 6 6-6 6m6-12 6 6-6 6',
		'long-arrow-right'     => 'M3 12h17m-5-5 5 5-5 5',
	];
	$separatorPath = $separatorPaths[ $separatorIcon ];

	$breadcrumbsSchema = (bool) ( $attributes['breadcrumbsSchema'] ?? true );

	$trailRaw = $attributes['_resolvedTrail'] ?? [];
	$trail    = [];

	if ( is_array( $trailRaw ) ) {
		foreach ( $trailRaw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$label = (string) ( $entry['label'] ?? '' );

			if ( '' === $label ) {
				continue;
			}

			$rawUrl       = isset( $entry['url'] ) ? (string) $entry['url'] : '';
			$sanitizedUrl = '' === $rawUrl ? '' : UrlSanitizer::safe( $rawUrl );

			$trail[] = [
				'label'   => $label,
				'url'     => '' === $sanitizedUrl ? null : $sanitizedUrl,
				'current' => ! empty( $entry['current'] ),
			];
		}
	}

	$ariaLabel = (string) ( $attributes['ariaLabel'] ?? 'Breadcrumb' );

	$baseClasses = [ 'ap-breadcrumbs' ];

	$listAttrs     = $breadcrumbsSchema ? ' itemscope itemtype="https://schema.org/BreadcrumbList"' : '';
	$listItemAttrs = $breadcrumbsSchema ? ' itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"' : '';
@endphp
<nav{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!} aria-label="{{ $ariaLabel }}">
	@if ( empty( $trail ) )
		<ol class="ap-breadcrumbs__list"{!! $listAttrs !!}></ol>
	@else
		<ol class="ap-breadcrumbs__list"{!! $listAttrs !!}>
			@foreach ( $trail as $index => $item )
				@php
					$isLast   = $index === count( $trail ) - 1;
					$position = $index + 1;
				@endphp
				<li class="ap-breadcrumbs__item"{!! $listItemAttrs !!}>
					@if ( $item['url'] && ! $item['current'] )
						<a class="ap-breadcrumbs__link" href="{{ $item['url'] }}"@if ( $breadcrumbsSchema ) itemprop="item"@endif>
							<span @if ( $breadcrumbsSchema ) itemprop="name"@endif>{{ $item['label'] }}</span>
						</a>
					@else
						<span class="ap-breadcrumbs__current" @if ( $item['current'] ) aria-current="page"@endif @if ( $breadcrumbsSchema ) itemprop="name"@endif>{{ $item['label'] }}</span>
					@endif
					@if ( $breadcrumbsSchema )
						<meta itemprop="position" content="{{ $position }}" />
					@endif
					@if ( ! $isLast )
						<span class="ap-breadcrumbs__separator" aria-hidden="true">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $separatorPath }}"></path></svg>
						</span>
					@endif
				</li>
			@endforeach
		</ol>
	@endif
</nav>
