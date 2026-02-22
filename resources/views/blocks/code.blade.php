@php
	$codeContent    = $content['content'] ?? '';
	$language       = $content['language'] ?? 'plain';
	$filename       = $content['filename'] ?? '';
	$showLineNums   = $styles['showLineNumbers'] ?? true;
	$highlightLines = $styles['highlightLines'] ?? '';
	$showCopyBtn    = $styles['showCopyButton'] ?? true;

	$lines          = explode( "\n", $codeContent );
	$highlightArray = [];

	if ( $highlightLines ) {
		foreach ( explode( ',', $highlightLines ) as $range ) {
			$range = trim( $range );
			if ( str_contains( $range, '-' ) ) {
				[ $start, $end ] = explode( '-', $range );
				for ( $i = (int) $start; $i <= (int) $end; $i++ ) {
					$highlightArray[] = $i;
				}
			} else {
				$highlightArray[] = (int) $range;
			}
		}
	}
@endphp

<div class="ve-block ve-block-code" data-language="{{ $language }}">
	@if ( $filename )
		<div class="ve-code-filename" style="padding: 0.5rem 1rem; background-color: #1e1e1e; color: #ccc; font-size: 0.75rem; border-bottom: 1px solid #333;">{{ $filename }}</div>
	@endif

	<div style="position: relative;">
		@if ( $showCopyBtn )
			<button
				class="ve-code-copy"
				type="button"
				style="position: absolute; top: 0.5rem; right: 0.5rem; padding: 0.25rem 0.5rem; font-size: 0.75rem; background: #333; color: #ccc; border: 1px solid #555; border-radius: 0.25rem; cursor: pointer;"
				data-code="{{ htmlspecialchars( $codeContent, ENT_QUOTES ) }}"
			>{{ __( 'visual-editor::ve.copy' ) }}</button>
		@endif

		<pre style="margin: 0; padding: 1rem; background-color: #1e1e1e; color: #d4d4d4; overflow-x: auto;"><code class="language-{{ $language }}">@foreach ( $lines as $index => $line )
@php $lineNum = $index + 1; $isHighlighted = in_array( $lineNum, $highlightArray ); @endphp
<span class="ve-code-line @if ( $isHighlighted ) ve-code-line-highlighted @endif" @if ( $isHighlighted ) style="background-color: rgba(255,255,0,0.1);" @endif>@if ( $showLineNums )<span class="ve-code-line-number" style="display: inline-block; width: 3em; text-align: right; padding-right: 1em; color: #666; user-select: none;">{{ $lineNum }}</span>@endif{{ htmlspecialchars( $line ) }}
</span>@endforeach</code></pre>
	</div>
</div>
