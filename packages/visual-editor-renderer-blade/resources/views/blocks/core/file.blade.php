@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$href         = UrlSanitizer::safe( (string) ( $attributes['href'] ?? '' ) );
	$fileName     = (string) ( $attributes['fileName'] ?? '' );
	$textLinkHref = UrlSanitizer::safe( (string) ( $attributes['textLinkHref'] ?? $href ) );
	$download     = (string) ( $attributes['downloadButtonText'] ?? 'Download' );
	$showDownload = ! isset( $attributes['showDownloadButton'] ) || ! empty( $attributes['showDownloadButton'] );
	$linkLabel    = '' !== $fileName ? $fileName : $href;

	$classes = [ 'wp-block-file' ];

	if ( ! empty( $attributes['className'] ) ) {
		$classes[] = $attributes['className'];
	}
@endphp
<div class="{{ implode( ' ', array_map( 'trim', $classes ) ) }}">
	@if ( '' !== $href )
		<a href="{{ '' !== $textLinkHref ? $textLinkHref : $href }}">{{ $linkLabel }}</a>
		@if ( $showDownload )
			<a href="{{ $href }}" class="wp-block-file__button" download>{{ $download }}</a>
		@endif
	@endif
</div>
