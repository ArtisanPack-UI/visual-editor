@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$taxonomy  = isset( $attributes['term'] ) && is_string( $attributes['term'] ) ? $attributes['term'] : '';
	$separator = isset( $attributes['separator'] ) && is_string( $attributes['separator'] ) ? $attributes['separator'] : ', ';
	$prefix    = isset( $attributes['prefix'] ) && is_string( $attributes['prefix'] ) ? $attributes['prefix'] : '';
	$suffix    = isset( $attributes['suffix'] ) && is_string( $attributes['suffix'] ) ? $attributes['suffix'] : '';

	$termsMap = isset( $attributes['_resolvedTermsByTaxonomy'] ) && is_array( $attributes['_resolvedTermsByTaxonomy'] )
		? $attributes['_resolvedTermsByTaxonomy']
		: [];

	$terms = '' !== $taxonomy && isset( $termsMap[ $taxonomy ] ) && is_array( $termsMap[ $taxonomy ] )
		? $termsMap[ $taxonomy ]
		: [];

	$items = [];
	foreach ( $terms as $term ) {
		if ( ! is_array( $term ) ) {
			continue;
		}

		$name = isset( $term['name'] ) && is_string( $term['name'] ) ? $term['name'] : '';
		$url  = isset( $term['url'] ) && is_string( $term['url'] ) ? UrlSanitizer::safe( $term['url'] ) : '';

		if ( '' === $name ) {
			continue;
		}

		if ( '' === $url ) {
			// Upstream `get_the_term_list` always produces anchors; we
			// degrade gracefully to plain text when the host can't
			// resolve a permalink for the term.
			$items[] = e( $name );
		} else {
			$items[] = sprintf( '<a href="%s">%s</a>', e( $url ), e( $name ) );
		}
	}

	$separatorMarkup = sprintf(
		'<span class="wp-block-post-terms__separator">%s</span>',
		e( $separator )
	);

	// Only attach the `taxonomy-{slug}` class when a taxonomy has actually
	// been selected — a bare `taxonomy-` (with no slug) is meaningless
	// styling noise.
	$baseClasses = [ 'wp-block-post-terms' ];
	if ( '' !== $taxonomy ) {
		$baseClasses[] = 'taxonomy-' . $taxonomy;
	}
@endphp
@if ( [] === $items )
	<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}></div>
@else
	<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>@if ( '' !== $prefix )<span class="wp-block-post-terms__prefix">{{ $prefix }}</span>@endif{!! implode( $separatorMarkup, $items ) !!}@if ( '' !== $suffix )<span class="wp-block-post-terms__suffix">{{ $suffix }}</span>@endif</div>
@endif
