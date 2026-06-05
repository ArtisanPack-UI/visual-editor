@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$level = (int) ( $attributes['level'] ?? 2 );
	$level = max( 1, min( 6, $level ) );
	$tag   = 'h' . $level;

	$content = (string) ( $attributes['content'] ?? '' );
@endphp
<{{ $tag }}{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-heading' ] ) !!}>{!! $content !!}</{{ $tag }}>
