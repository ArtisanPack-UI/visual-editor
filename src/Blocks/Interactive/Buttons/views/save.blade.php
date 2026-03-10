@php
	$justification = $content['justification'] ?? 'left';
	$orientation   = $content['orientation'] ?? 'horizontal';
	$flexWrap      = $content['flexWrap'] ?? true;
	$anchor        = $content['anchor'] ?? null;
	$htmlId        = $content['htmlId'] ?? null;
	$className     = $content['className'] ?? '';
	$innerBlocks   = $innerBlocks ?? [];

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$justifyMap = [
		'left'          => 'flex-start',
		'center'        => 'center',
		'right'         => 'flex-end',
		'space-between' => 'space-between',
	];

	$justifyValue   = $justifyMap[ $justification ] ?? 'flex-start';
	$directionValue = 'vertical' === $orientation ? 'column' : 'row';

	$inlineStyles = "display: flex; flex-direction: {$directionValue}; justify-content: {$justifyValue}; gap: 0.5rem;";
	if ( $flexWrap && 'horizontal' === $orientation ) {
		$inlineStyles .= ' flex-wrap: wrap;';
	}

	$classes = 've-block ve-block-buttons';
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

<div
	class="{{ $classes }}"
	style="{{ $inlineStyles }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
