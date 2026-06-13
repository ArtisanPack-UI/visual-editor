@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$renderer = app( \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::class );

	$panelId = isset( $attributes['panelId'] ) ? (string) $attributes['panelId'] : '';
	// Escape once at assignment time so every downstream interpolation
	// (id / aria-controls / aria-labelledby / data-panel-id) stays safe
	// even when the persisted data didn't come from the editor's slugify.
	$panelIdSafe = htmlspecialchars( $panelId, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$panelIcon = isset( $attributes['panelIcon'] ) ? (string) $attributes['panelIcon'] : 'plus-minus';

	if ( ! in_array( $panelIcon, [ 'plus-minus', 'arrows' ], true ) ) {
		$panelIcon = 'plus-minus';
	}

	$panelIdAttr   = '' === $panelId ? '' : ' data-panel-id="' . $panelIdSafe . '"';
	$panelIconAttr = ' data-panel-icon="' . $panelIcon . '"';
	$controlId     = '' === $panelId ? '' : $panelIdSafe . '-control';

	$titleHtml = '';
	$bodyHtml  = '';

	foreach ( $innerBlocks as $child ) {
		if ( ! is_array( $child ) || ! isset( $child['name'] ) ) {
			continue;
		}

		$childInner = isset( $child['innerBlocks'] ) && is_array( $child['innerBlocks'] )
			? $renderer->render( $child['innerBlocks'] )
			: '';

		if ( 'artisanpack/accordion-title' === $child['name'] ) {
			$titleControlsAttr = '' === $panelId ? '' : ' aria-controls="' . $panelIdSafe . '"';
			$titleIdAttr       = '' === $controlId ? '' : ' id="' . $controlId . '"';
			$titleHtml = sprintf(
				'<div class="ap-accordion__title"><div class="ap-accordion__title-content" role="button" tabindex="0" aria-expanded="false"%s%s>%s</div><span class="ap-accordion__icon ap-accordion__icon--%s" aria-hidden="true"></span></div>',
				$titleIdAttr,
				$titleControlsAttr,
				$childInner,
				htmlspecialchars( $panelIcon, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
			);
			continue;
		}

		if ( 'artisanpack/accordion-body' === $child['name'] ) {
			$bodyIdAttr         = '' === $panelId ? '' : ' id="' . $panelIdSafe . '"';
			$bodyLabelledByAttr = '' === $controlId ? '' : ' aria-labelledby="' . $controlId . '"';
			$bodyHtml = sprintf(
				'<div class="ap-accordion__body"%s role="region"%s hidden>%s</div>',
				$bodyIdAttr,
				$bodyLabelledByAttr,
				$childInner
			);
			continue;
		}
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-accordion' ] ) !!}{!! $panelIdAttr !!}{!! $panelIconAttr !!}>
	{!! $titleHtml !!}
	{!! $bodyHtml !!}
</div>
