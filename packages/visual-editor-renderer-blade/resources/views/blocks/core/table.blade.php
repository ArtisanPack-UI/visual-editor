@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$baseClasses = [ 'wp-block-table' ];

	// `hasFixedLayout` defaults to true in block.json and has no source, so
	// a saved block only carries it in the delimiter when it is false.
	// Upstream save puts the class on the `<table>`, which is also where
	// the `.wp-block-table .has-fixed-layout` rule expects it (#971).
	$rawFixedLayout = $attributes['hasFixedLayout'] ?? null;

	if ( null === $rawFixedLayout ) {
		$hasFixedLayout = true;
	} elseif ( is_string( $rawFixedLayout ) ) {
		$hasFixedLayout = ! in_array( strtolower( trim( $rawFixedLayout ) ), [ '', 'false', '0' ], true );
	} else {
		$hasFixedLayout = (bool) $rawFixedLayout;
	}

	$caption = (string) ( $attributes['caption'] ?? '' );

	$sections = [
		'head' => isset( $attributes['head'] ) && is_array( $attributes['head'] ) ? $attributes['head'] : [],
		'body' => isset( $attributes['body'] ) && is_array( $attributes['body'] ) ? $attributes['body'] : [],
		'foot' => isset( $attributes['foot'] ) && is_array( $attributes['foot'] ) ? $attributes['foot'] : [],
	];

	$sectionTag = [
		'head' => 'thead',
		'body' => 'tbody',
		'foot' => 'tfoot',
	];

	$defaultCellTag = [
		'head' => 'th',
		'body' => 'td',
		'foot' => 'td',
	];

	$allowedAligns = [ 'left', 'center', 'right', 'justify' ];
	$allowedScopes = [ 'row', 'col', 'rowgroup', 'colgroup' ];

	// HTML's own ceilings for the span attributes (colspan 1000, rowspan 65534).
	$spanLimits = [
		'colspan' => 1000,
		'rowspan' => 65534,
	];

	$spanAttr = static function ( array $cell, string $name ) use ( $spanLimits ): string {
		$raw = $cell[ $name ] ?? null;

		if ( is_int( $raw ) ) {
			$raw = (string) $raw;
		}

		if ( ! is_string( $raw ) || 1 !== preg_match( '/^([0-9]+)$/D', $raw, $matches ) ) {
			return '';
		}

		$span = (int) $matches[1];

		if ( $span < 1 ) {
			return '';
		}

		return sprintf( ' %s="%d"', $name, min( $span, $spanLimits[ $name ] ) );
	};

	$renderCell = static function ( array $cell, string $defaultTag ) use ( $allowedAligns, $allowedScopes, $spanAttr ): string {
		$cellContent = (string) ( $cell['content'] ?? '' );
		$rawAlign    = isset( $cell['align'] ) ? strtolower( (string) $cell['align'] ) : '';
		$cellAlign   = in_array( $rawAlign, $allowedAligns, true ) ? $rawAlign : '';
		$tag         = isset( $cell['tag'] ) && in_array( $cell['tag'], [ 'td', 'th' ], true )
			? $cell['tag']
			: $defaultTag;

		$styleAttr = '' !== $cellAlign
			? sprintf( ' style="text-align: %s;"', $cellAlign )
			: '';

		$rawScope  = isset( $cell['scope'] ) && is_string( $cell['scope'] ) ? strtolower( $cell['scope'] ) : '';
		$scopeAttr = 'th' === $tag && in_array( $rawScope, $allowedScopes, true )
			? sprintf( ' scope="%s"', $rawScope )
			: '';

		return sprintf(
			'<%1$s%2$s%3$s%4$s%5$s>%6$s</%1$s>',
			$tag,
			$scopeAttr,
			$spanAttr( $cell, 'colspan' ),
			$spanAttr( $cell, 'rowspan' ),
			$styleAttr,
			$cellContent
		);
	};
@endphp
<figure{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	<table{!! $hasFixedLayout ? ' class="has-fixed-layout"' : '' !!}>
		@foreach ( $sections as $sectionKey => $rows )
			@if ( ! empty( $rows ) )
				<{{ $sectionTag[ $sectionKey ] }}>
					@foreach ( $rows as $row )
						@php
							$cells = isset( $row['cells'] ) && is_array( $row['cells'] ) ? $row['cells'] : [];
						@endphp
						<tr>
							@foreach ( $cells as $cell )
								{!! $renderCell( $cell, $defaultCellTag[ $sectionKey ] ) !!}
							@endforeach
						</tr>
					@endforeach
				</{{ $sectionTag[ $sectionKey ] }}>
			@endif
		@endforeach
	</table>
	@if ( '' !== trim( $caption ) )
		<figcaption>{!! $caption !!}</figcaption>
	@endif
</figure>
