@php
	$codeContent    = $content['content'] ?? '';
	$language       = $content['language'] ?? 'plain';
	$filename       = $content['filename'] ?? '';
	$showLineNumbers = $styles['showLineNumbers'] ?? true;

	$classes = 've-block ve-block-code ve-block-editing';
	if ( $showLineNumbers ) {
		$classes .= ' ve-code-line-numbers';
	}
@endphp

<div class="{{ $classes }}">
	@if ( $filename )
		<div
			class="ve-code-filename"
			contenteditable="true"
			data-placeholder="{{ __( 'visual-editor::ve.code_filename' ) }}"
		>{{ $filename }}</div>
	@endif
	<pre><code
		class="language-{{ $language }}"
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.code_content' ) }}"
	>{{ $codeContent }}</code></pre>
</div>
