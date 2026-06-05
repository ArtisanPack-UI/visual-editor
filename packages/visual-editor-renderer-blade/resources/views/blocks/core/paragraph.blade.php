@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	/**
	 * core/paragraph — server-rendered partial.
	 *
	 * Kept in lockstep with the artisanpack/paragraph partial so mixed
	 * documents (during the V2 fork rollout window) render identically.
	 * BlockSupports::applyAlign emits both `align{value}` and
	 * `has-text-align-{value}` for left/center/right; the latter is the
	 * class theme stylesheets actually target for paragraphs.
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
