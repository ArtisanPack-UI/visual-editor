@php
	$headingLevels = array_map( 'intval', $content['headingLevels'] ?? [ 2, 3 ] );
	$maxDepth      = max( 1, min( 6, (int) ( $content['maxDepth'] ?? 6 ) ) );
	$headingLevels = array_filter( $headingLevels, fn ( $l ) => $l >= 1 && $l <= $maxDepth );
	$headingLevels = array_values( $headingLevels );
	$listStyle     = $content['listStyle'] ?? 'numbered';
	$hierarchical  = $content['hierarchical'] ?? true;
	$tocTitle      = $content['title'] ?? __( 'visual-editor::ve.table_of_contents' );
	$collapsible   = $content['collapsible'] ?? false;

	$sampleData = [
		[ 'level' => 2, 'text' => __( 'visual-editor::ve.sample_heading_introduction' ) ],
		[ 'level' => 3, 'text' => __( 'visual-editor::ve.sample_heading_overview' ) ],
		[ 'level' => 3, 'text' => __( 'visual-editor::ve.sample_heading_getting_started' ) ],
		[ 'level' => 2, 'text' => __( 'visual-editor::ve.sample_heading_features' ) ],
		[ 'level' => 3, 'text' => __( 'visual-editor::ve.sample_heading_configuration' ) ],
		[ 'level' => 2, 'text' => __( 'visual-editor::ve.sample_heading_conclusion' ) ],
	];

	$filteredHeadings = array_filter( $sampleData, fn ( $h ) => in_array( $h['level'], $headingLevels, true ) );
	$filteredHeadings = array_values( $filteredHeadings );

	$listTag   = 'numbered' === $listStyle ? 'ol' : 'ul';
	$listClass = 'plain' === $listStyle ? 'style="list-style: none; padding-left: 0;"' : 'style="padding-left: 1.5rem;"';
@endphp

<div class="ve-block ve-block-table-of-contents ve-block-editing ve-block-dynamic-preview">
	<nav aria-label="{{ __( 'visual-editor::ve.table_of_contents' ) }}" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 1.25rem;">
		@if ( $collapsible )
			<details open>
				<summary style="font-weight: 600; font-size: 1.1em; cursor: pointer; margin-bottom: 0.75rem;">{{ $tocTitle ?: __( 'visual-editor::ve.table_of_contents' ) }}</summary>
		@else
			@if ( $tocTitle )
				<h2 style="font-weight: 600; font-size: 1.1em; margin: 0 0 0.75rem;">{{ $tocTitle }}</h2>
			@endif
		@endif

		@if ( $hierarchical && count( $filteredHeadings ) > 0 )
			@php
				$minLevel = min( array_column( $filteredHeadings, 'level' ) );
			@endphp
			<{{ $listTag }} {!! $listClass !!}>
				@foreach ( $filteredHeadings as $heading )
					@php
						$indent  = $heading['level'] - $minLevel;
						$anchor  = Illuminate\Support\Str::slug( $heading['text'] );
					@endphp
					@if ( $indent > 0 )
						<li style="margin-left: {{ $indent * 1.5 }}rem;">
					@else
						<li>
					@endif
						<a href="#{{ $anchor }}" style="color: #2563eb; text-decoration: none;">{{ $heading['text'] }}</a>
					</li>
				@endforeach
			</{{ $listTag }}>
		@else
			<{{ $listTag }} {!! $listClass !!}>
				@foreach ( $filteredHeadings as $heading )
					@php $anchor = Illuminate\Support\Str::slug( $heading['text'] ); @endphp
					<li>
						<a href="#{{ $anchor }}" style="color: #2563eb; text-decoration: none;">{{ $heading['text'] }}</a>
					</li>
				@endforeach
			</{{ $listTag }}>
		@endif

		@if ( $collapsible )
			</details>
		@endif
	</nav>
</div>
