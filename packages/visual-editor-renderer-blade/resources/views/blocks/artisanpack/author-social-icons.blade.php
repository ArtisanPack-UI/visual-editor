@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\SocialIconRegistry;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$validStyles     = [ 'show-label-icon', 'show-icon', 'show-label' ];
	$validDirections = [ 'horizontal', 'vertical' ];
	$validStretches  = [ 'full-width', 'auto-width' ];

	$iconStyle = (string) ( $attributes['iconStyle'] ?? 'show-label-icon' );
	if ( ! in_array( $iconStyle, $validStyles, true ) ) {
		$iconStyle = 'show-label-icon';
	}

	$direction = (string) ( $attributes['iconsDirection'] ?? 'vertical' );
	if ( ! in_array( $direction, $validDirections, true ) ) {
		$direction = 'vertical';
	}

	$stretch = (string) ( $attributes['iconsStretch'] ?? 'full-width' );
	if ( ! in_array( $stretch, $validStretches, true ) ) {
		$stretch = 'full-width';
	}

	$rawRadius = $attributes['iconsBorderRadius'] ?? 0;
	$radius    = is_numeric( $rawRadius ) ? (int) $rawRadius : 0;
	if ( $radius < 0 ) {
		$radius = 0;
	} elseif ( $radius > 50 ) {
		$radius = 50;
	}

	$selected = $attributes['socialIcons'] ?? [];
	if ( ! is_array( $selected ) ) {
		$selected = [];
	}

	$selectedSlugs = [];
	foreach ( $selected as $entry ) {
		if ( is_string( $entry ) ) {
			$selectedSlugs[ $entry ] = true;
		}
	}

	$linksRaw = $attributes['_resolvedAuthorSocialLinks'] ?? [];
	$chips    = [];

	if ( is_array( $linksRaw ) ) {
		foreach ( $linksRaw as $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}

			$slug = isset( $link['slug'] ) && is_string( $link['slug'] ) ? $link['slug'] : '';
			$url  = isset( $link['url'] ) && is_string( $link['url'] ) ? $link['url'] : '';

			if ( '' === $slug || ! isset( $selectedSlugs[ $slug ] ) ) {
				continue;
			}

			$definition = SocialIconRegistry::author( $slug );

			if ( null === $definition ) {
				continue;
			}

			$safeUrl = 'mailto:' === substr( $url, 0, 7 ) ? $url : UrlSanitizer::safe( $url );

			if ( '' === $safeUrl ) {
				continue;
			}

			$chips[] = [
				'slug'  => $slug,
				'url'   => $safeUrl,
				'label' => $definition['label'],
				'path'  => $definition['path'],
			];
		}
	}

	$baseClasses = [
		'ap-author-social-icons',
		'ap-author-social-icons--' . $direction,
		'ap-author-social-icons--' . $stretch,
	];

	$styleParts = [];
	if ( $radius > 0 ) {
		$styleParts[] = 'border-radius:' . $radius . 'px';
	}

	$iconColor      = is_string( $attributes['iconColor'] ?? null ) ? trim( $attributes['iconColor'] ) : '';
	$iconHoverColor = is_string( $attributes['iconHoverColor'] ?? null ) ? trim( $attributes['iconHoverColor'] ) : '';
	$iconBgColor    = is_string( $attributes['iconBackgroundColor'] ?? null ) ? trim( $attributes['iconBackgroundColor'] ) : '';
	$iconHoverBg    = is_string( $attributes['iconHoverBackgroundColor'] ?? null ) ? trim( $attributes['iconHoverBackgroundColor'] ) : '';

	// Emit ONLY CSS custom properties — the base color / background
	// come from `var(--ap-social-color)` / `var(--ap-social-bg)` rules
	// in social-icons.css. Inline `color: X` would have higher
	// specificity than the `:hover { color: var(--ap-social-hover-color) }`
	// rule and silently break hover swaps.
	if ( '' !== $iconColor ) {
		$styleParts[] = '--ap-social-color:' . $iconColor;
	}
	if ( '' !== $iconBgColor ) {
		$styleParts[] = '--ap-social-bg:' . $iconBgColor;
	}
	if ( '' !== $iconHoverColor ) {
		$styleParts[] = '--ap-social-hover-color:' . $iconHoverColor;
	}
	if ( '' !== $iconHoverBg ) {
		$styleParts[] = '--ap-social-hover-bg:' . $iconHoverBg;
	}

	$styleAttr = empty( $styleParts ) ? '' : ' style="' . e( implode( ';', $styleParts ) ) . '"';

	$showIcon  = 'show-label' !== $iconStyle;
	$showLabel = 'show-icon' !== $iconStyle;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	@foreach ( $chips as $chip )
		<div class="ap-author-social-icons__item">
			<a class="ap-author-social-icons__chip {{ $chip['slug'] }}" href="{{ $chip['url'] }}" aria-label="{{ 'Author on ' . $chip['label'] }}"{!! $styleAttr !!}>
				@if ( $showIcon )
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" class="ap-author-social-icons__icon" fill="currentColor"><path d="{{ $chip['path'] }}"></path></svg>
				@endif
				@if ( $showLabel )
					<span class="ap-author-social-icons__label">{{ $chip['label'] }}</span>
				@endif
			</a>
		</div>
	@endforeach
</div>
