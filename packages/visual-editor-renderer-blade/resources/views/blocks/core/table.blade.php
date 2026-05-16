@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$baseClasses = [ 'wp-block-table' ];

	if ( ! empty( $attributes['hasFixedLayout'] ) ) {
		$baseClasses[] = 'has-fixed-layout';
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

	$renderCell = static function ( array $cell, string $defaultTag ) use ( $allowedAligns ): string {
		$cellContent = (string) ( $cell['content'] ?? '' );
		$rawAlign    = isset( $cell['align'] ) ? strtolower( (string) $cell['align'] ) : '';
		$cellAlign   = in_array( $rawAlign, $allowedAligns, true ) ? $rawAlign : '';
		$tag         = isset( $cell['tag'] ) && in_array( $cell['tag'], [ 'td', 'th' ], true )
			? $cell['tag']
			: $defaultTag;

		$styleAttr = '' !== $cellAlign
			? sprintf( ' style="text-align: %s;"', $cellAlign )
			: '';

		return sprintf( '<%1$s%2$s>%3$s</%1$s>', $tag, $styleAttr, $cellContent );
	};
@endphp
<figure{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
	<table>
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
