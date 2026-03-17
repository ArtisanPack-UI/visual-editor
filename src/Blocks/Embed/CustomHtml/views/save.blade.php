@php
	$htmlContent = $content['content'] ?? '';
	$sanitize    = $content['sanitize'] ?? true;
	$cssClass    = $content['cssClass'] ?? '';
	$anchor      = $content['anchor'] ?? null;
	$htmlId      = $content['htmlId'] ?? null;
	$className   = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$classes = 've-block ve-block-custom-html';
	if ( $cssClass ) {
		$classes .= " {$cssClass}";
	}
	if ( $className ) {
		$classes .= " {$className}";
	}

	// Sanitize content when enabled: prefer kses() from artisanpack-ui/security.
	// If kses() is unavailable, force the sandboxed iframe path instead
	// of strip_tags() which cannot prevent attribute-based XSS.
	if ( $sanitize ) {
		if ( function_exists( 'kses' ) ) {
			$htmlContent = kses( $htmlContent );
		} else {
			$sanitize = false;
		}
	}
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@if ( $sanitize )
		{!! $htmlContent !!}
	@else
		<iframe
			srcdoc="{{ e( $htmlContent ) }}"
			sandbox="allow-scripts"
			class="ve-custom-html-iframe"
			title="{{ __( 'visual-editor::ve.custom_html_preview_title' ) }}"
			style="width: 100%; border: 0;"
			loading="lazy"
		></iframe>
	@endif
</div>
