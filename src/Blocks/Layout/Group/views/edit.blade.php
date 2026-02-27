@php
	$tag           = $content['tag'] ?? 'div';
	$flexDirection = $content['flexDirection'] ?? 'column';
	$flexWrap      = $content['flexWrap'] ?? 'nowrap';
	$bgColor       = $styles['backgroundColor'] ?? null;
	$padding       = $styles['padding'] ?? null;
	$margin        = $styles['margin'] ?? null;
	$border        = $styles['border'] ?? [];
	$minHeight     = $styles['minHeight'] ?? '';
	$verticalAlign = $styles['verticalAlignment'] ?? 'top';
	$innerBlocks   = $innerBlocks ?? [];

	$allowedTags = [ 'div', 'section', 'article', 'aside', 'main' ];
	$tag         = in_array( $tag, $allowedTags ) ? $tag : 'div';

	$allowedDirections = [ 'column', 'row' ];
	$flexDirection     = in_array( $flexDirection, $allowedDirections ) ? $flexDirection : 'column';

	$allowedWraps = [ 'nowrap', 'wrap' ];
	$flexWrap     = in_array( $flexWrap, $allowedWraps ) ? $flexWrap : 'nowrap';

	$alignMap = [
		'top'     => 'flex-start',
		'center'  => 'center',
		'bottom'  => 'flex-end',
		'stretch' => 'stretch',
	];

	$inlineStyles = "display: flex; flex-direction: {$flexDirection}; flex-wrap: {$flexWrap};";
	$inlineStyles .= " justify-content: " . ( $alignMap[ $verticalAlign ] ?? 'flex-start' ) . ";";

	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
	}

	if ( is_array( $padding ) ) {
		$top    = $padding['top'] ?? '0';
		$right  = $padding['right'] ?? '0';
		$bottom = $padding['bottom'] ?? '0';
		$left   = $padding['left'] ?? '0';
		$inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
	}

	if ( is_array( $margin ) ) {
		$top    = $margin['top'] ?? '0';
		$bottom = $margin['bottom'] ?? '0';
		$inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
	}

	if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
		$bWidth = ( $border['width'] ?? '0' ) . ( $border['widthUnit'] ?? 'px' );
		$bStyle = $border['style'] ?? 'solid';
		$bColor = $border['color'] ?? 'currentColor';
		$inlineStyles .= " border: {$bWidth} {$bStyle} {$bColor};";

		$bRadius = $border['radius'] ?? '0';
		if ( $bRadius && '0' !== $bRadius ) {
			$bRadiusUnit = $border['radiusUnit'] ?? 'px';
			$inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
		}
	}

	if ( $minHeight ) {
		$inlineStyles .= " min-height: {$minHeight};";
	}
@endphp

<{{ $tag }}
	class="ve-block ve-block-group ve-block-editing"
	style="{{ $inlineStyles }}"
	data-placeholder="{{ __( 'visual-editor::ve.block_group_placeholder' ) }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</{{ $tag }}>
