@php
	$preContent      = $content['content'] ?? '';
	$fontFamily      = $styles['fontFamily'] ?? 'monospace';
	$fontSize        = $styles['fontSize'] ?? null;
	$textColor       = $styles['textColor'] ?? null;
	$bgColor         = $styles['backgroundColor'] ?? null;
	$showLineNumbers = $styles['showLineNumbers'] ?? false;

	$allowedFonts = [ 'monospace', 'ui-monospace', 'Courier New', 'Consolas', 'Menlo', 'Source Code Pro' ];
	$fontFamily   = in_array( $fontFamily, $allowedFonts, true ) ? $fontFamily : 'monospace';

	$classes = 've-block ve-block-preformatted ve-block-editing';
	if ( $showLineNumbers ) {
		$classes .= ' ve-pre-line-numbers';
	}

	$inlineStyles = "font-family: {$fontFamily}, monospace;";

	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= " color: {$textColor};";
	}

	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
	}

	if ( $fontSize ) {
		$fontSize = veSanitizeCssDimension( $fontSize );
		$inlineStyles .= " font-size: {$fontSize};";
	}
@endphp

<div class="{{ $classes }}">
	<pre
		style="{{ $inlineStyles }}"
		contenteditable="true"
		data-placeholder="{{ __( 'visual-editor::ve.block_preformatted_placeholder' ) }}"
	>{{ $preContent }}</pre>
</div>
