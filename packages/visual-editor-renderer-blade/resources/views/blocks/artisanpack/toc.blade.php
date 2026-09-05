@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	// `_resolvedItems` is stamped by TocResolver ahead of rendering (#760).
	// The list is already filtered by the block's min/max level range,
	// so the renderer just has to shape it into a nested list.
	$items = $attributes['_resolvedItems'] ?? [];

	if ( ! is_array( $items ) ) {
		$items = [];
	}

	$rawLabelLevel = $attributes['headingLevel'] ?? 2;
	$labelLevel    = is_numeric( $rawLabelLevel ) ? (int) round( (float) $rawLabelLevel ) : 2;

	if ( $labelLevel < 2 ) {
		$labelLevel = 2;
	} elseif ( $labelLevel > 6 ) {
		$labelLevel = 6;
	}

	$labelTag = 'h' . $labelLevel;

	$heading = (string) ( $attributes['heading'] ?? '' );
	$ordered = (bool) ( $attributes['ordered'] ?? false );
	$listTag = $ordered ? 'ol' : 'ul';

	/**
	 * Fold a flat, document-ordered list of headings into a nested tree
	 * keyed by depth. Every heading opens as many implicit parents as
	 * needed so a jump from H2 → H4 (skipping H3) still nests correctly.
	 *
	 * @param  array<int, array{level:int,text:string,anchor:string}>  $flat
	 *
	 * @return array<int, array{level:int,text:string,anchor:string,children:array<int,mixed>}>
	 */
	$buildTree = static function ( array $flat ): array {
		$root  = [];
		$stack = [ &$root ];
		// Track the level associated with each list currently on the
		// stack so a heading of level N knows how many parents to unwind.
		$levels = [ 0 ];

		foreach ( $flat as $entry ) {
			$level  = (int) ( $entry['level'] ?? 0 );
			$anchor = (string) ( $entry['anchor'] ?? '' );

			if ( '' === $anchor ) {
				continue;
			}

			while ( count( $levels ) > 1 && $levels[ count( $levels ) - 1 ] >= $level ) {
				array_pop( $stack );
				array_pop( $levels );
			}

			$node = [
				'level'    => $level,
				'text'     => (string) ( $entry['text'] ?? '' ),
				'anchor'   => $anchor,
				'children' => [],
			];

			$parent =& $stack[ count( $stack ) - 1 ];
			$parent[] = $node;

			$last =& $parent[ count( $parent ) - 1 ];
			$stack[]  = &$last['children'];
			$levels[] = $level;

			// Break the reference so the next iteration does not clobber
			// the pushed child list.
			unset( $parent, $last );
		}

		return $root;
	};

	$tree = $buildTree( $items );
@endphp
<nav{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-toc' ] ) !!} aria-label="{{ '' !== trim( strip_tags( $heading ) ) ? trim( strip_tags( $heading ) ) : 'Table of contents' }}">
	@if ( '' !== $heading )
		<{{ $labelTag }} class="ap-toc__heading">{!! $heading !!}</{{ $labelTag }}>
	@endif
	@if ( empty( $tree ) )
		<p class="ap-toc__placeholder">No headings found on this page yet.</p>
	@else
		@include( 'visual-editor-renderer-blade::blocks.artisanpack.toc-list', [
			'nodes'   => $tree,
			'listTag' => $listTag,
		] )
	@endif
</nav>
