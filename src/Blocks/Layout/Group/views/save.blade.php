@php
	$tag            = $content['tag'] ?? 'div';
	$flexDirection  = $content['flexDirection'] ?? 'column';
	$flexWrap       = $content['flexWrap'] ?? 'nowrap';
	$justifyContent = $content['justifyContent'] ?? 'flex-start';
	$textColor      = $styles['textColor'] ?? null;
	$bgColor        = $styles['backgroundColor'] ?? null;
	$padding        = $styles['padding'] ?? null;
	$margin         = $styles['margin'] ?? null;
	$border         = $styles['border'] ?? [];
	$minHeight      = $styles['minHeight'] ?? '';
	$verticalAlign  = $styles['verticalAlignment'] ?? 'top';
	$gap            = $styles['gap'] ?? null;
	$useFlexbox     = $styles['useFlexbox'] ?? false;
	$fillHeight     = $styles['fillHeight'] ?? false;
	$innerSpacing   = $styles['innerSpacing'] ?? 'normal';
	$anchor         = $content['anchor'] ?? null;
	$htmlId         = $content['htmlId'] ?? null;
	$className      = $content['className'] ?? '';

	$elementId   = $htmlId ?: $anchor;
	$innerBlocks = $innerBlocks ?? [];

	$allowedTags = [ 'div', 'section', 'article', 'aside', 'main', 'header', 'footer' ];
	$tag         = in_array( $tag, $allowedTags ) ? $tag : 'div';

	$allowedDirections = [ 'column', 'row' ];
	$flexDirection     = in_array( $flexDirection, $allowedDirections ) ? $flexDirection : 'column';

	$allowedWraps = [ 'nowrap', 'wrap' ];
	$flexWrap     = in_array( $flexWrap, $allowedWraps ) ? $flexWrap : 'nowrap';

	$allowedJustify = [ 'flex-start', 'center', 'flex-end', 'space-between' ];
	$justifyContent = in_array( $justifyContent, $allowedJustify ) ? $justifyContent : 'flex-start';

	$alignMap = [
		'top'     => 'flex-start',
		'center'  => 'center',
		'bottom'  => 'flex-end',
		'stretch' => 'stretch',
	];

	$inlineStyles = "display: flex; flex-direction: {$flexDirection}; flex-wrap: {$flexWrap};";

	if ( 'row' === $flexDirection ) {
		$inlineStyles .= " justify-content: {$justifyContent};";
		$inlineStyles .= " align-items: " . ( $alignMap[ $verticalAlign ] ?? 'flex-start' ) . ";";
	} else {
		$inlineStyles .= " align-items: " . ( $alignMap[ $verticalAlign ] ?? 'flex-start' ) . ";";
	}

	// Validate CSS color values to prevent injection.
	$colorPattern = '/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*[\d\s,.%]+\)|hsla?\(\s*[\d\s,.%deg]+\)|[a-zA-Z]+)$/';
	$textColor    = $textColor && preg_match( $colorPattern, $textColor ) ? $textColor : null;
	$bgColor      = $bgColor && preg_match( $colorPattern, $bgColor ) ? $bgColor : null;

	if ( $textColor ) {
		$inlineStyles .= " color: {$textColor};";
	}

	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
	}

	// Determine gap: prefer useFlexbox spacing map over raw gap.
	if ( $useFlexbox ) {
		$spacingMap = [
			'none'   => '0',
			'small'  => '0.5rem',
			'normal' => '1rem',
			'medium' => '1.5rem',
			'large'  => '2rem',
		];
		$gapValue    = $spacingMap[ $innerSpacing ] ?? '1rem';
		$inlineStyles .= " gap: {$gapValue};";
	} elseif ( $gap && preg_match( '/^[\d.]+(px|rem|em|%|vh|vw)?$/', $gap ) ) {
		$inlineStyles .= " gap: {$gap};";
	}

	if ( $fillHeight ) {
		$inlineStyles .= " height: 100%;";
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

	if ( $minHeight && preg_match( '/^[\d.]+(px|rem|em|%|vh|vw)?$/', $minHeight ) ) {
		$inlineStyles .= " min-height: {$minHeight};";
	}

	$classes = "ve-block ve-block-group";
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<{{ $tag }}
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</{{ $tag }}>
