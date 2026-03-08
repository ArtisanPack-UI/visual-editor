@php
	$justification = $content['justification'] ?? 'left';
	$orientation   = $content['orientation'] ?? 'horizontal';
	$flexWrap      = $content['flexWrap'] ?? true;
	$innerBlocks   = $innerBlocks ?? [];

	$justifyMap = [
		'left'          => 'flex-start',
		'center'        => 'center',
		'right'         => 'flex-end',
		'space-between' => 'space-between',
	];

	$justifyValue  = $justifyMap[ $justification ] ?? 'flex-start';
	$directionValue = 'vertical' === $orientation ? 'column' : 'row';

	$inlineStyles = "display: flex; flex-direction: {$directionValue}; justify-content: {$justifyValue}; gap: 0.5rem;";
	if ( $flexWrap && 'horizontal' === $orientation ) {
		$inlineStyles .= ' flex-wrap: wrap;';
	}
@endphp

<div
	class="ve-block ve-block-buttons ve-block-editing"
	style="{{ $inlineStyles }}"
	data-justification="{{ $justification }}"
	data-orientation="{{ $orientation }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
