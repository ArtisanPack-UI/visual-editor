@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	// Always a flex layout (block.json disallows switching), as in
	// `comments-pagination.blade.php`: the layout pair, a `wp-container-*`
	// scope for its justification, wrap and Block spacing, and WordPress's
	// layout modifier classes. With no saved justification or orientation,
	// `query-pagination.css` keeps its previous / numbers / next grid (#956).
	$baseClasses = array_merge(
		[ 'wp-block-query-pagination' ],
		LayoutSupport::pair( 'query-pagination', 'is-layout-flex' ),
		LayoutSupport::containerClasses( $attributes, 'query-pagination', 'is-layout-flex' ),
		LayoutSupport::modifierClasses( $attributes )
	);
@endphp
{{-- artisanpack/query-pagination — Phase I-Block-Fork query family (#521).
	Pagination wrapper around the next / numbers / previous children.

	Translation key intentionally avoids the bare word "Pagination":
	Laravel ships `vendor/laravel/framework/src/Illuminate/Translation/lang/en/pagination.php`
	which the JSON translator's case-insensitive fallback matches on
	`__( 'Pagination' )`, returning the file's array (`previous` /
	`next`) instead of a string and crashing `e()` downstream. --}}
<nav{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!} aria-label="{{ __( 'Pagination navigation' ) }}">
	{!! $innerBlocksHtml !!}
</nav>
