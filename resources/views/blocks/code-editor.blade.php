@php
	$codeContent  = $content['content'] ?? '';
	$language     = $content['language'] ?? 'plain';
	$filename     = $content['filename'] ?? '';
	$showLineNums = $styles['showLineNumbers'] ?? true;
@endphp

<div class="ve-block ve-block-code ve-block-editing" data-language="{{ $language }}">
	@if ( $filename )
		<div class="ve-code-filename" style="padding: 0.5rem 1rem; background-color: #1e1e1e; color: #ccc; font-size: 0.75rem; border-bottom: 1px solid #333;">{{ $filename }}</div>
	@endif

	<pre
		style="margin: 0; padding: 1rem; background-color: #1e1e1e; color: #d4d4d4; overflow-x: auto; min-height: 3rem;"
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.block_code_placeholder' ) }}"
	><code class="language-{{ $language }}">{{ htmlspecialchars( $codeContent ) }}</code></pre>
</div>
