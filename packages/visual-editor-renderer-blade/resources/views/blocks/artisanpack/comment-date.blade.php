@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$dateIso = isset( $attributes['_resolvedDate'] ) && is_string( $attributes['_resolvedDate'] ) ? $attributes['_resolvedDate'] : '';
	$date    = isset( $attributes['_resolvedDateFormatted'] ) && is_string( $attributes['_resolvedDateFormatted'] ) ? $attributes['_resolvedDateFormatted'] : '';
	$url     = UrlSanitizer::safe( isset( $attributes['_resolvedPermalink'] ) && is_string( $attributes['_resolvedPermalink'] ) ? $attributes['_resolvedPermalink'] : '' );

	$isLink = ! empty( $attributes['isLink'] );
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comment-date' ] ) !!}>
	@if ( $isLink && '' !== $url )
		<a href="{{ $url }}">
			@if ( '' !== $dateIso )
				<time datetime="{{ $dateIso }}">{{ $date }}</time>
			@else
				{{ $date }}
			@endif
		</a>
	@elseif ( '' !== $dateIso )
		<time datetime="{{ $dateIso }}">{{ $date }}</time>
	@else
		{{ $date }}
	@endif
</div>
