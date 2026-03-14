@php
	$rows            = $content['rows'] ?? [];
	$hasHeaderRow    = $content['hasHeaderRow'] ?? false;
	$hasHeaderColumn = $content['hasHeaderColumn'] ?? false;
	$hasFooterRow    = $content['hasFooterRow'] ?? false;
	$caption         = $content['caption'] ?? '';
	$striped         = $styles['striped'] ?? false;
	$bordered        = $styles['bordered'] ?? true;
	$fixedLayout     = $styles['fixedLayout'] ?? false;
	$headerBgColor   = $styles['headerBackgroundColor'] ?? null;
	$stripeColor     = $styles['stripeColor'] ?? null;
	$borderColor     = $styles['borderColor'] ?? null;
	$textColor       = $styles['textColor'] ?? null;
	$bgColor         = $styles['backgroundColor'] ?? null;
	$padding         = $styles['padding'] ?? null;
	$margin          = $styles['margin'] ?? null;
	$border          = $styles['border'] ?? [];
	$anchor          = $content['anchor'] ?? null;
	$htmlId          = $content['htmlId'] ?? null;
	$className       = $content['className'] ?? '';

	$elementId = veSanitizeHtmlId( $htmlId ?: $anchor );

	$classes = 've-block ve-block-table';
	if ( $striped ) {
		$classes .= ' ve-table-striped';
	}
	if ( $bordered ) {
		$classes .= ' ve-table-bordered';
	}
	if ( $fixedLayout ) {
		$classes .= ' ve-table-fixed';
	}
	if ( $className ) {
		$classes .= " {$className}";
	}

	$inlineStyles = '';

	$textColor = veSanitizeCssColor( $textColor );
	if ( $textColor ) {
		$inlineStyles .= "color: {$textColor};";
	}

	$bgColor = veSanitizeCssColor( $bgColor );
	if ( $bgColor ) {
		$inlineStyles .= " background-color: {$bgColor};";
	}

	$borderColor = veSanitizeCssColor( $borderColor );
	if ( $borderColor ) {
		$inlineStyles .= " --ve-table-border-color: {$borderColor};";
	}

	$stripeColor = veSanitizeCssColor( $stripeColor );
	if ( $stripeColor ) {
		$inlineStyles .= " --ve-table-stripe-color: {$stripeColor};";
	}

	if ( is_array( $padding ) ) {
		$top    = veSanitizeCssDimension( $padding['top'] ?? '0' );
		$right  = veSanitizeCssDimension( $padding['right'] ?? '0' );
		$bottom = veSanitizeCssDimension( $padding['bottom'] ?? '0' );
		$left   = veSanitizeCssDimension( $padding['left'] ?? '0' );
		$inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
	}

	if ( is_array( $margin ) ) {
		$top    = veSanitizeCssDimension( $margin['top'] ?? '0' );
		$bottom = veSanitizeCssDimension( $margin['bottom'] ?? '0' );
		$inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
	}

	if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
		$bWidth     = veSanitizeCssDimension( $border['width'] ?? '0' );
		$bWidthUnit = veSanitizeCssUnit( $border['widthUnit'] ?? 'px' );
		$bStyle     = veSanitizeBorderStyle( $border['style'] ?? 'solid' );
		$bColor     = veSanitizeCssColor( $border['color'] ?? 'currentColor', 'currentColor' );
		$inlineStyles .= " border: {$bWidth}{$bWidthUnit} {$bStyle} {$bColor};";

		$bRadius = $border['radius'] ?? '0';
		if ( $bRadius && '0' !== $bRadius ) {
			$bRadius     = veSanitizeCssDimension( $bRadius );
			$bRadiusUnit = veSanitizeCssUnit( $border['radiusUnit'] ?? 'px' );
			$inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
		}
	}

	$totalRows      = count( $rows );
	$headerRows     = $hasHeaderRow ? [ $rows[0] ?? [] ] : [];
	$footerRows     = $hasFooterRow && $totalRows > 1 ? [ $rows[ $totalRows - 1 ] ] : [];
	$bodyStartIndex = $hasHeaderRow ? 1 : 0;
	$bodyEndIndex   = $hasFooterRow && $totalRows > 1 ? $totalRows - 1 : $totalRows;
	$bodyRows       = array_slice( $rows, $bodyStartIndex, $bodyEndIndex - $bodyStartIndex );
@endphp

<div
	class="{{ $classes }}"
	@if ( $elementId ) id="{{ $elementId }}" @endif
	@if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
	<div class="ve-table-responsive">
		<table>
			@if ( $caption )
				<caption>{{ $caption }}</caption>
			@endif

			@php $sanitizedHeaderBgColor = veSanitizeCssColor( $headerBgColor ); @endphp
			@if ( ! empty( $headerRows ) )
				<thead @if ( $sanitizedHeaderBgColor ) style="background-color: {{ $sanitizedHeaderBgColor }};" @endif>
					@foreach ( $headerRows as $row )
						<tr>
							@foreach ( $row as $cell )
								<th
									scope="col"
									@if ( ( $cell['colSpan'] ?? 1 ) > 1 ) colspan="{{ $cell['colSpan'] }}" @endif
									@if ( ( $cell['rowSpan'] ?? 1 ) > 1 ) rowspan="{{ $cell['rowSpan'] }}" @endif
									@php $alignment = $cell['alignment'] ?? 'left'; @endphp
									@if ( 'left' !== $alignment ) style="text-align: {{ in_array( $alignment, [ 'left', 'center', 'right', 'justify' ], true ) ? $alignment : 'left' }};" @endif
								>{!! kses( $cell['content'] ?? '' ) !!}</th>
							@endforeach
						</tr>
					@endforeach
				</thead>
			@endif

			<tbody>
				@foreach ( $bodyRows as $row )
					<tr>
						@foreach ( $row as $cellIndex => $cell )
							@if ( $hasHeaderColumn && 0 === $cellIndex )
								<th
									scope="row"
									@if ( ( $cell['colSpan'] ?? 1 ) > 1 ) colspan="{{ $cell['colSpan'] }}" @endif
									@if ( ( $cell['rowSpan'] ?? 1 ) > 1 ) rowspan="{{ $cell['rowSpan'] }}" @endif
									@php $alignment = $cell['alignment'] ?? 'left'; @endphp
									@if ( 'left' !== $alignment ) style="text-align: {{ in_array( $alignment, [ 'left', 'center', 'right', 'justify' ], true ) ? $alignment : 'left' }};" @endif
								>{!! kses( $cell['content'] ?? '' ) !!}</th>
							@else
								<td
									@if ( ( $cell['colSpan'] ?? 1 ) > 1 ) colspan="{{ $cell['colSpan'] }}" @endif
									@if ( ( $cell['rowSpan'] ?? 1 ) > 1 ) rowspan="{{ $cell['rowSpan'] }}" @endif
									@php $alignment = $cell['alignment'] ?? 'left'; @endphp
									@if ( 'left' !== $alignment ) style="text-align: {{ in_array( $alignment, [ 'left', 'center', 'right', 'justify' ], true ) ? $alignment : 'left' }};" @endif
								>{!! kses( $cell['content'] ?? '' ) !!}</td>
							@endif
						@endforeach
					</tr>
				@endforeach
			</tbody>

			@if ( ! empty( $footerRows ) )
				<tfoot>
					@foreach ( $footerRows as $row )
						<tr>
							@foreach ( $row as $cell )
								<td
									@if ( ( $cell['colSpan'] ?? 1 ) > 1 ) colspan="{{ $cell['colSpan'] }}" @endif
									@if ( ( $cell['rowSpan'] ?? 1 ) > 1 ) rowspan="{{ $cell['rowSpan'] }}" @endif
									@php $alignment = $cell['alignment'] ?? 'left'; @endphp
									@if ( 'left' !== $alignment ) style="text-align: {{ in_array( $alignment, [ 'left', 'center', 'right', 'justify' ], true ) ? $alignment : 'left' }};" @endif
								>{!! kses( $cell['content'] ?? '' ) !!}</td>
							@endforeach
						</tr>
					@endforeach
				</tfoot>
			@endif
		</table>
	</div>
</div>
