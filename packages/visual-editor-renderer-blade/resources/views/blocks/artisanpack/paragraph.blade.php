@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	/**
	 * artisanpack/paragraph — server-rendered partial.
	 *
	 * Output matches the upstream `core/paragraph` Blade partial so
	 * documents containing either namespace render identically. The
	 * `wp-block-paragraph` class is preserved; `has-drop-cap` is added
	 * when the editor persisted `dropCap=true` and the text-align value
	 * does not disable it (center, right in LTR, left in RTL).
	 */

	$content   = ( string ) ( $attributes['content'] ?? '' );
	$dropCap   = ! empty( $attributes['dropCap'] );
	$direction = $attributes['direction'] ?? null;
	$textAlign = $attributes['style']['typography']['textAlign'] ?? ( $attributes['align'] ?? null );

	$extraClasses = [ 'wp-block-paragraph' ];

	// Upstream save.js disables the drop-cap when the text alignment
	// matches the reading-end side — `right` in LTR, `left` in RTL —
	// or when it's centered. The block's own `direction` attribute is
	// the per-block override; absent that we assume LTR.
	$readingEnd     = ( 'rtl' === $direction ) ? 'left' : 'right';
	$dropCapEnabled = $dropCap && 'center' !== $textAlign && $readingEnd !== $textAlign;

	if ( $dropCapEnabled ) {
		$extraClasses[] = 'has-drop-cap';
	}

	$dirAttr = ( 'ltr' === $direction || 'rtl' === $direction ) ? sprintf( ' dir="%s"', $direction ) : '';
@endphp
<p{!! BlockSupports::wrapperAttrs( $attributes, $extraClasses ) !!}{!! $dirAttr !!}>{!! $content !!}</p>
