@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
@endphp
{{-- artisanpack/query-pagination — Phase I-Block-Fork query family (#521).
	Pagination wrapper around the next / numbers / previous children.

	Translation key intentionally avoids the bare word "Pagination":
	Laravel ships `vendor/laravel/framework/src/Illuminate/Translation/lang/en/pagination.php`
	which the JSON translator's case-insensitive fallback matches on
	`__( 'Pagination' )`, returning the file's array (`previous` /
	`next`) instead of a string and crashing `e()` downstream. --}}
<nav{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-query-pagination' ] ) !!} aria-label="{{ __( 'Pagination navigation' ) }}">
	{!! $innerBlocksHtml !!}
</nav>
