@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$level = isset( $attributes['level'] ) && is_numeric( $attributes['level'] ) ? (int) $attributes['level'] : 2;
	$level = max( 1, min( 6, $level ) );
	$tag   = 'h' . $level;

	$showCount = ! isset( $attributes['showCommentsCount'] ) || ! empty( $attributes['showCommentsCount'] );
	$count     = isset( $attributes['_resolvedCommentCount'] ) && is_numeric( $attributes['_resolvedCommentCount'] ) ? (int) $attributes['_resolvedCommentCount'] : 0;
	$title     = isset( $attributes['_resolvedCommentsTitle'] ) && is_string( $attributes['_resolvedCommentsTitle'] )
		? $attributes['_resolvedCommentsTitle']
		: trans_choice( '{0} No Comments|{1} 1 Comment|[2,*] :count Comments', $count, [ 'count' => $count ] );

	if ( ! $showCount ) {
		$title = __( 'Comments' );
	}

	$align   = isset( $attributes['textAlign'] ) && is_string( $attributes['textAlign'] ) ? $attributes['textAlign'] : '';
	$classes = [ 'wp-block-post-comments-title' ];
	if ( '' !== $align ) {
		$classes[] = 'has-text-align-' . $align;
	}
@endphp
<{!! $tag !!}{!! BlockSupports::wrapperAttrs( $attributes, $classes ) !!}>{{ $title }}</{!! $tag !!}>
