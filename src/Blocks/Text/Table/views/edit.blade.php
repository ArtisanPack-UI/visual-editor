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
	$borderColor     = $styles['borderColor'] ?? null;

	$classes = 've-block ve-block-table ve-block-editing';
	if ( $striped ) {
		$classes .= ' ve-table-striped';
	}
	if ( $bordered ) {
		$classes .= ' ve-table-bordered';
	}
	if ( $fixedLayout ) {
		$classes .= ' ve-table-fixed';
	}

	$inlineStyles = '';
	$borderColor  = veSanitizeCssColor( $borderColor );
	if ( $borderColor ) {
		$inlineStyles .= "--ve-table-border-color: {$borderColor};";
	}

	$totalRows       = count( $rows );
	$headerRows      = $hasHeaderRow ? [ $rows[0] ?? [] ] : [];
	$footerRows      = $hasFooterRow && $totalRows > 1 ? [ $rows[ $totalRows - 1 ] ] : [];
	$bodyStartIndex  = $hasHeaderRow ? 1 : 0;
	$bodyEndIndex    = $hasFooterRow && $totalRows > 1 ? $totalRows - 1 : $totalRows;
	$bodyRows        = array_slice( $rows, $bodyStartIndex, $bodyEndIndex - $bodyStartIndex );
@endphp

<div class="{{ $classes }}" @if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif>
	<div class="ve-table-responsive">
		<table>
			@if ( $caption )
				<caption
					contenteditable="true"
					data-placeholder="{{ __( 'visual-editor::ve.table_caption_placeholder' ) }}"
				>{{ $caption }}</caption>
			@endif

			@php $sanitizedHeaderBgColor = veSanitizeCssColor( $headerBgColor ); @endphp
			@if ( ! empty( $headerRows ) )
				<thead @if ( $sanitizedHeaderBgColor ) style="background-color: {{ $sanitizedHeaderBgColor }};" @endif>
					@foreach ( $headerRows as $row )
						<tr>
							@foreach ( $row as $cellIndex => $cell )
								<th
									scope="col"
									contenteditable="true"
									data-placeholder="{{ __( 'visual-editor::ve.table_cell_placeholder' ) }}"
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
				@foreach ( $bodyRows as $rowIndex => $row )
					<tr>
						@foreach ( $row as $cellIndex => $cell )
							@if ( $hasHeaderColumn && 0 === $cellIndex )
								<th
									scope="row"
									contenteditable="true"
									data-placeholder="{{ __( 'visual-editor::ve.table_cell_placeholder' ) }}"
									@if ( ( $cell['colSpan'] ?? 1 ) > 1 ) colspan="{{ $cell['colSpan'] }}" @endif
									@if ( ( $cell['rowSpan'] ?? 1 ) > 1 ) rowspan="{{ $cell['rowSpan'] }}" @endif
									@php $alignment = $cell['alignment'] ?? 'left'; @endphp
									@if ( 'left' !== $alignment ) style="text-align: {{ in_array( $alignment, [ 'left', 'center', 'right', 'justify' ], true ) ? $alignment : 'left' }};" @endif
								>{!! kses( $cell['content'] ?? '' ) !!}</th>
							@else
								<td
									contenteditable="true"
									data-placeholder="{{ __( 'visual-editor::ve.table_cell_placeholder' ) }}"
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
							@foreach ( $row as $cellIndex => $cell )
								<td
									contenteditable="true"
									data-placeholder="{{ __( 'visual-editor::ve.table_cell_placeholder' ) }}"
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
