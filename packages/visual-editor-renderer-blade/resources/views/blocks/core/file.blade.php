@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$href         = UrlSanitizer::safe( (string) ( $attributes['href'] ?? '' ) );
	$fileName     = (string) ( $attributes['fileName'] ?? '' );
	$textLinkHref = UrlSanitizer::safe( (string) ( $attributes['textLinkHref'] ?? $href ) );
	$download     = (string) ( $attributes['downloadButtonText'] ?? __( 'Download' ) );
	$showDownload = ! isset( $attributes['showDownloadButton'] ) || ! empty( $attributes['showDownloadButton'] );
	$linkLabel    = '' !== $fileName ? $fileName : $href;

	// Mirrors the editor save: the button points at the file-name link for
	// its accessible description, but only when that link has a name.
	$fileId        = (string) ( $attributes['fileId'] ?? '' );
	$describedById = '' !== $fileName && '' !== $fileId ? $fileId : '';
	$linkIdAttr    = '' !== $describedById ? ' id="' . e( $describedById ) . '"' : '';
	$describedAttr = '' !== $describedById ? ' aria-describedby="' . e( $describedById ) . '"' : '';
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-file' ] ) !!}>
	@if ( '' !== $href )
		<a{!! $linkIdAttr !!} href="{{ '' !== $textLinkHref ? $textLinkHref : $href }}">{{ $linkLabel }}</a>
		@if ( $showDownload )
			<a href="{{ $href }}" class="wp-block-file__button wp-element-button" download{!! $describedAttr !!}>{{ $download }}</a>
		@endif
	@endif
</div>
