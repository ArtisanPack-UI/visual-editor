@php
	$justification  = $content['justification'] ?? 'left';
	$orientation    = $content['orientation'] ?? 'horizontal';
	$flexWrap       = $content['flexWrap'] ?? true;
	$gap            = $styles['gap'] ?? '0.5rem';
	$stackOnMobile  = $styles['stackOnMobile'] ?? false;
	$innerBlocks    = $innerBlocks ?? [];

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

	$classes = 've-block ve-block-buttons ve-block-editing';
	if ( $stackOnMobile ) {
		$classes .= ' ve-buttons-stack-mobile';
	}
@endphp

<div
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
	role="group"
	aria-label="{{ __( 'visual-editor::ve.block_button-group_name' ) }}"
	data-justification="{{ $justification }}"
	data-orientation="{{ $orientation }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
