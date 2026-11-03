{{--
	core/html — emits the block's saved markup verbatim.

	Matches Gutenberg, whose `core/html` save is a bare `<RawHTML>`: no
	wrapper element, no `className`/`customClassName` support, nothing
	added around the author's bytes. A wrapper here would break the one
	thing the block exists for — pasting markup that has to land exactly
	as written.

	`content` is recovered by `BlockAttributeSourceResolver`'s `raw`
	matcher from the block's whole inner HTML (the manifest declares
	`content` with `source: "raw"`), so it is already the saved markup
	rather than a text value needing escaping.

	Deliberately NOT sanitized here: this partial sits inside the trust
	boundary documented on `BlockMarkupHydrator`. Authors without the
	unfiltered-HTML ability have their markup filtered when it's saved
	(`BlockHtmlGuard`, #1062), so what reaches this partial is theme
	files, patterns, or markup a trusted user wrote. Sanitizing here would
	silently mangle the valid `<script>`/`<iframe>`/`<svg>` payloads those
	users reach for this block to write.
--}}
@php
	$content = (string) ( $attributes['content'] ?? '' );
@endphp
{!! $content !!}
