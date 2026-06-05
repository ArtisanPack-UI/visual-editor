@php
	$postId = isset( $attributes['postId'] ) ? (int) $attributes['postId'] : 0;
	$className = isset( $attributes['className'] ) && is_string( $attributes['className'] )
		? trim( $attributes['className'] )
		: '';

	$classes = array_filter( [ 'wp-block-post-template-item', $className ] );
	$classAttr = sprintf( 'class="%s"', e( implode( ' ', $classes ) ) );
	$idAttr = $postId > 0 ? sprintf( ' id="post-%d"', $postId ) : '';
@endphp
<li{!! $idAttr !!} {!! $classAttr !!}>
	{!! $innerBlocksHtml !!}
</li>
