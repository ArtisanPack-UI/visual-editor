@php
	$url         = $content['url'] ?? '';
	$alt         = $content['alt'] ?? '';
	$caption     = $content['caption'] ?? '';
	$link        = $content['link'] ?? '';
	$target      = $content['linkTarget'] ?? '_self';
	$size        = $styles['size'] ?? 'large';
	$alignment   = $styles['alignment'] ?? 'center';
	$rounded     = $styles['rounded'] ?? false;
	$shadow      = $styles['shadow'] ?? false;
	$objectFit   = $styles['objectFit'] ?? 'cover';
	$aspectRatio = $styles['aspectRatio'] ?? 'original';
	$imgWidth    = $styles['width'] ?? '';
	$imgHeight   = $styles['height'] ?? '';
	$anchor      = $content['anchor'] ?? null;
	$htmlId      = $content['htmlId'] ?? null;
	$className   = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

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

	$allowedObjectFit   = [ 'cover', 'contain', 'fill', 'none', 'scale-down' ];
	$safeObjectFit      = in_array( $objectFit, $allowedObjectFit, true ) ? $objectFit : 'cover';
	$safeAspectRatio    = preg_match( '/^\d+\s*\/\s*\d+$/', (string) $aspectRatio ) ? $aspectRatio : 'original';

	$imgStyle = "object-fit: {$safeObjectFit};";
	if ( 'original' !== $safeAspectRatio ) {
		$imgStyle .= " aspect-ratio: {$safeAspectRatio};";
	}
	if ( $imgWidth ) {
		$imgStyle .= ' width: ' . ( is_numeric( $imgWidth ) ? $imgWidth . 'px' : veSanitizeCssDimension( (string) $imgWidth, '' ) ) . ';';
	}
	if ( $imgHeight ) {
		$imgStyle .= ' height: ' . ( is_numeric( $imgHeight ) ? $imgHeight . 'px' : veSanitizeCssDimension( (string) $imgHeight, '' ) ) . ';';
	}
@endphp

<figure
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $url )
		@if ( $link )
			<a href="{{ $link }}" target="{{ $target }}"@if ( '_blank' === $target ) rel="noopener noreferrer"@endif>
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
