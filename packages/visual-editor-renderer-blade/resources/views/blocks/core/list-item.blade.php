@php
	$content = (string) ( $attributes['content'] ?? '' );
@endphp
<li>{!! $content !!}{!! $innerBlocksHtml !!}</li>
