@php
	$tag           = $content['tag'] ?? 'div';
	$bgColor       = $styles['backgroundColor'] ?? null;
	$padding       = $styles['padding'] ?? null;
	$margin        = $styles['margin'] ?? null;
	$border        = $styles['border'] ?? false;
	$borderRadius  = $styles['borderRadius'] ?? '';
	$minHeight     = $styles['minHeight'] ?? '';
	$verticalAlign = $styles['verticalAlignment'] ?? 'top';
	$innerBlocks   = $innerBlocks ?? [];

	$allowedTags = [ 'div', 'section', 'article', 'aside', 'main' ];
	$tag         = in_array( $tag, $allowedTags ) ? $tag : 'div';

	$alignMap = [
		'top'     => 'flex-start',
		'center'  => 'center',
		'bottom'  => 'flex-end',
		'stretch' => 'stretch',
	];

	$inlineStyles = "display: flex; flex-direction: column;";
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

	if ( $border ) {
		$inlineStyles .= " border: 1px solid currentColor;";
	}

	if ( $borderRadius ) {
		$inlineStyles .= " border-radius: {$borderRadius};";
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
