@php
	$columnsData      = $content['columns'] ?? [ 'mode' => 'global', 'global' => 2 ];
	$layout           = $content['layout'] ?? 'equal';
	$gap              = $styles['gap'] ?? 'medium';
	$verticalAlign    = $styles['verticalAlignment'] ?? 'top';
	$stackOnMobile    = $styles['stackOnMobile'] ?? true;
	$anchor           = $content['anchor'] ?? null;
	$htmlId           = $content['htmlId'] ?? null;
	$className        = $content['className'] ?? '';
	$innerBlocks      = $innerBlocks ?? [];

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	// Parse responsive columns data.
	if ( is_array( $columnsData ) ) {
		$colMode    = $columnsData['mode'] ?? 'global';
		$colGlobal  = (int) ( $columnsData['global'] ?? $columnsData['desktop'] ?? 2 );
		$colDesktop = (int) ( $columnsData['desktop'] ?? $colGlobal );
		$colTablet  = (int) ( $columnsData['tablet'] ?? $colDesktop );
		$colMobile  = (int) ( $columnsData['mobile'] ?? 1 );
	} else {
		$colMode    = 'global';
		$colGlobal  = (int) $columnsData ?: 2;
		$colDesktop = $colGlobal;
		$colTablet  = $colGlobal;
		$colMobile  = 1;
	}

	// Use global value for all breakpoints when in global mode.
	if ( 'global' === $colMode ) {
		$colDesktop = $colGlobal;
		$colTablet  = $colGlobal;
		$colMobile  = $stackOnMobile ? 1 : $colGlobal;
	}

	$gapMap = [
		'none'   => '0',
		'small'  => '0.5rem',
		'medium' => '1rem',
		'large'  => '2rem',
	];

	$alignMap = [
		'top'     => 'flex-start',
		'center'  => 'center',
		'bottom'  => 'flex-end',
		'stretch' => 'stretch',
	];

	$gapValue   = $gapMap[ $gap ] ?? '1rem';
	$alignValue = $alignMap[ $verticalAlign ] ?? 'flex-start';

	// Generate a unique ID for responsive CSS if responsive mode is used.
	$needsResponsiveCss = 'responsive' === $colMode || $stackOnMobile;
	$cssId              = $elementId ?: 've-columns-' . md5( serialize( $columnsData ) );

	$classes = 've-block ve-block-columns';
	if ( $stackOnMobile ) {
		$classes .= ' ve-columns-stack-mobile';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}
@endphp

@if ( $needsResponsiveCss )
	<style>
		#{{ $cssId }} {
			display: flex;
			gap: {{ $gapValue }};
			align-items: {{ $alignValue }};
			flex-wrap: wrap;
		}
		#{{ $cssId }} > .ve-block-column {
			flex: 0 0 calc( {{ 100 / $colDesktop }}% - {{ $gapValue }} * {{ $colDesktop - 1 }} / {{ $colDesktop }} );
		}
		@media ( max-width: 1024px ) {
			#{{ $cssId }} > .ve-block-column {
				flex: 0 0 calc( {{ 100 / $colTablet }}% - {{ $gapValue }} * {{ $colTablet - 1 }} / {{ $colTablet }} );
			}
		}
		@media ( max-width: 640px ) {
			#{{ $cssId }} > .ve-block-column {
				flex: 0 0 calc( {{ 100 / $colMobile }}% - {{ $gapValue }} * {{ $colMobile - 1 }} / {{ $colMobile }} );
			}
		}
	</style>
@endif

<div
	class="{{ $classes }}"
	@if ( ! $needsResponsiveCss )
		style="display: flex; gap: {{ $gapValue }}; align-items: {{ $alignValue }};"
	@endif
	id="{{ $cssId }}"
	data-columns="{{ $colDesktop }}"
	data-layout="{{ $layout }}"
>
	@foreach ( $innerBlocks as $innerBlock )
		{!! $innerBlock !!}
	@endforeach
</div>
