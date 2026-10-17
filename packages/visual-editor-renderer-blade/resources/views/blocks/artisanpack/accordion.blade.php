@php
	use ArtisanPackUI\VisualEditor\Support\BlockShape;
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

	// Open on first paint: the title says so and the body isn't hidden.
	// `interactivity.js` seeds each panel from `aria-expanded`.
	$defaultOpen = ! empty( $attributes['defaultOpen'] );

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

		// The title and body are re-wrapped here, so their own supports
		// (className and the `ve-r-*` / `ve-fx-*` scopes with their CSS,
		// M-7) go on the re-wrapped elements, as their standalone
		// partials do. Their anchor is dropped: the body already carries
		// the panel id.
		$childAttributes = BlockShape::readAttrs( $child )[1];
		unset( $childAttributes['anchor'] );

		if ( 'artisanpack/accordion-title' === $child['name'] ) {
			$titleControlsAttr = '' === $panelId ? '' : ' aria-controls="' . $panelIdSafe . '"';
			$titleIdAttr       = '' === $controlId ? '' : ' id="' . $controlId . '"';
			$titleHtml = sprintf(
				'<div%s><div class="ap-accordion__title-content" role="button" tabindex="0" aria-expanded="%s"%s%s>%s</div><span class="ap-accordion__icon ap-accordion__icon--%s" aria-hidden="true"></span></div>',
				BlockSupports::wrapperAttrs( $childAttributes, [ 'wp-block-artisanpack-accordion-title', 'ap-accordion__title' ] ),
				$defaultOpen ? 'true' : 'false',
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
				'<div%s%s role="region"%s%s>%s</div>',
				BlockSupports::wrapperAttrs( $childAttributes, [ 'wp-block-artisanpack-accordion-body', 'ap-accordion__body' ] ),
				$bodyIdAttr,
				$bodyLabelledByAttr,
				$defaultOpen ? '' : ' hidden',
				$childInner
			);
			continue;
		}
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-artisanpack-accordion', 'ap-accordion' ] ) !!}{!! $panelIdAttr !!}{!! $panelIconAttr !!}>
	{!! $titleHtml !!}
	{!! $bodyHtml !!}
</div>
