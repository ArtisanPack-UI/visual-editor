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

	Deliberately NOT run through `kses()`: this partial sits inside the
	trust boundary documented on `BlockMarkupHydrator` — block markup is
	only ever rendered from sources the host already trusts (theme files
	on disk, patterns, editor-authored content that cleared the post
	editor's authorization). Sanitizing here would silently mangle the
	valid `<script>`/`<iframe>`/`<svg>` payloads authors reach for this
	block to write, while doing nothing for a host that is already
	rendering untrusted markup — that host has to sanitize BEFORE
	hydrating, as every other block partial's `{!! !!}` assumes.
--}}
@php
	$content = (string) ( $attributes['content'] ?? '' );
@endphp
{!! $content !!}
