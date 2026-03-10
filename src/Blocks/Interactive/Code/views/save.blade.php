@php
	$codeContent    = $content['content'] ?? '';
	$language       = $content['language'] ?? 'plain';
	$filename       = $content['filename'] ?? '';
	$showLineNumbers = $styles['showLineNumbers'] ?? true;
	$highlightLines = $styles['highlightLines'] ?? '';
	$showCopyButton = $styles['showCopyButton'] ?? true;
	$anchor         = $content['anchor'] ?? null;
	$htmlId         = $content['htmlId'] ?? null;
	$className      = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$classes = 've-block ve-block-code';
	if ( $showLineNumbers ) {
		$classes .= ' ve-code-line-numbers';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
	@if ( $highlightLines ) data-highlight-lines="{{ $highlightLines }}" @endif
>
	@if ( $filename )
		<div class="ve-code-filename">{{ $filename }}</div>
	@endif
	<pre><code class="language-{{ $language }}">{{ $codeContent }}</code></pre>
	@if ( $showCopyButton )
		<button type="button" class="ve-code-copy-button" aria-label="{{ __( 'visual-editor::ve.copy_code' ) }}">
			{{ __( 'visual-editor::ve.copy' ) }}
		</button>
	@endif
</div>
