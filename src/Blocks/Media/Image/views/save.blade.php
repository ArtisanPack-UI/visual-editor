@php
	$url       = $content['url'] ?? '';
	$alt       = $content['alt'] ?? '';
	$caption   = $content['caption'] ?? '';
	$link      = $content['link'] ?? '';
	$target    = $content['linkTarget'] ?? '_self';
	$size      = $styles['size'] ?? 'large';
	$alignment = $styles['alignment'] ?? 'center';
	$rounded   = $styles['rounded'] ?? false;
	$shadow    = $styles['shadow'] ?? false;
	$objectFit = $styles['objectFit'] ?? 'cover';
	$anchor    = $content['anchor'] ?? null;
	$htmlId    = $content['htmlId'] ?? null;
	$className = $content['className'] ?? '';

	$elementId = $htmlId ?: $anchor;

	$classes = "ve-block ve-block-image ve-block-image--{$size} text-{$alignment}";
	if ( $rounded ) {
		$classes .= ' ve-rounded';
	}
	if ( $shadow ) {
		$classes .= ' ve-shadow';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}

	$imgStyle = "object-fit: {$objectFit};";
@endphp

<figure
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $url )
		@if ( $link )
			<a href="{{ $link }}" target="{{ $target }}">
				<img src="{{ $url }}" alt="{{ $alt }}" style="{{ $imgStyle }}" />
			</a>
		@else
			<img src="{{ $url }}" alt="{{ $alt }}" style="{{ $imgStyle }}" />
		@endif
	@endif
	@if ( $caption )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
