@php
	$justification  = $content['justification'] ?? 'left';
	$orientation    = $content['orientation'] ?? 'horizontal';
	$flexWrap       = $content['flexWrap'] ?? true;
	$gap            = $styles['gap'] ?? '0.5rem';
	$stackOnMobile  = $styles['stackOnMobile'] ?? false;
	$anchor         = $content['anchor'] ?? null;
	$htmlId         = $content['htmlId'] ?? null;
	$className      = $content['className'] ?? '';
	$innerBlocks    = $innerBlocks ?? [];

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$justifyMap = [
		'left'          => 'flex-start',
		'center'        => 'center',
		'right'         => 'flex-end',
		'space-between' => 'space-between',
	];

	$justifyValue   = $justifyMap[ $justification ] ?? 'flex-start';
	$directionValue = 'vertical' === $orientation ? 'column' : 'row';

	$gapValue = veSanitizeCssDimension( $gap, '0.5rem' );

	$inlineStyles = "display: flex; flex-direction: {$directionValue}; justify-content: {$justifyValue}; gap: {$gapValue};";
	if ( $flexWrap && 'horizontal' === $orientation ) {
		$inlineStyles .= ' flex-wrap: wrap;';
	}

	$classes = 've-block ve-block-buttons';
	if ( $stackOnMobile ) {
		$classes .= ' ve-buttons-stack-mobile';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
	role="group"
	aria-label="{{ __( 'visual-editor::ve.block_button-group_name' ) }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
