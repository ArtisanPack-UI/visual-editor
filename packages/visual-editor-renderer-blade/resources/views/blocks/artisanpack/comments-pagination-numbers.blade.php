@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$pages   = isset( $attributes['_resolvedPageNumbers'] ) && is_array( $attributes['_resolvedPageNumbers'] ) ? $attributes['_resolvedPageNumbers'] : [];
	$current = isset( $attributes['_resolvedCurrentPage'] ) && is_numeric( $attributes['_resolvedCurrentPage'] ) ? (int) $attributes['_resolvedCurrentPage'] : 1;
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-comments-pagination-numbers' ] ) !!}>
	@foreach ( $pages as $page )
		@php
			$number = isset( $page['number'] ) && is_numeric( $page['number'] ) ? (int) $page['number'] : 0;
			$href   = UrlSanitizer::safe( isset( $page['url'] ) && is_string( $page['url'] ) ? $page['url'] : '' );
		@endphp
		@if ( 0 !== $number )
			@if ( $number === $current )
				{{-- Only the actual current page gets aria-current="page". A
					non-current page with an empty href still renders as a
					plain span (no link target) but must not claim to be the
					current page. Mirrors the React / Vue renderer parity. --}}
				<span class="page-numbers current" aria-current="page">{{ $number }}</span>
			@elseif ( '' === $href )
				<span class="page-numbers">{{ $number }}</span>
			@else
				<a class="page-numbers" href="{{ $href }}">{{ $number }}</a>
			@endif
		@endif
	@endforeach
</div>
