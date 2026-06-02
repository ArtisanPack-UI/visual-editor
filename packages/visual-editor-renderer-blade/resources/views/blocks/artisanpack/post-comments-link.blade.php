@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$count = isset( $attributes['_resolvedCommentCount'] ) && is_numeric( $attributes['_resolvedCommentCount'] ) ? (int) $attributes['_resolvedCommentCount'] : 0;
	$url   = UrlSanitizer::safe( isset( $attributes['_resolvedCommentsUrl'] ) && is_string( $attributes['_resolvedCommentsUrl'] ) ? $attributes['_resolvedCommentsUrl'] : '' );
	$label = isset( $attributes['_resolvedCommentsLabel'] ) && is_string( $attributes['_resolvedCommentsLabel'] )
		? $attributes['_resolvedCommentsLabel']
		: trans_choice( '{0} :count Comments|{1} :count Comment|[2,*] :count Comments', $count, [ 'count' => $count ] );
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-comments-link' ] ) !!}>
	@if ( '' !== $url )
		<a href="{{ $url }}">{{ $label }}</a>
	@else
		{{ $label }}
	@endif
</div>
