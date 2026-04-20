@php
	$content = (string) ( $attributes['content'] ?? '' );
@endphp
<pre class="wp-block-preformatted">{!! $content !!}</pre>
