@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$content = (string) ( $attributes['content'] ?? '' );
@endphp
<pre{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-preformatted' ] ) !!}>{!! $content !!}</pre>
