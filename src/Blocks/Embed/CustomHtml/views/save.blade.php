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

	// Sanitize content when enabled: prefer kses() from artisanpack-ui/security,
	// fall back to strip_tags() to ensure no unsanitized HTML is emitted.
	if ( $sanitize ) {
		if ( function_exists( 'kses' ) ) {
			$htmlContent = kses( $htmlContent );
		} else {
			$htmlContent = strip_tags( $htmlContent, '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><code><pre><span><div><table><thead><tbody><tr><th><td><img><hr>' );
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
