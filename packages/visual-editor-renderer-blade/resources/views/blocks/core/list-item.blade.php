@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$content = (string) ( $attributes['content'] ?? '' );
@endphp
<li{!! BlockSupports::wrapperAttrs( $attributes ) !!}>{!! $content !!}{!! $innerBlocksHtml !!}</li>
