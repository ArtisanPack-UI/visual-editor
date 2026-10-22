{{--
	artisanpack/search-filters — GET form wrapper (#502).

	Drops a `<form method="get">` around the inner filter controls and
	pins them to a single post type via a hidden `post_type` input. The
	`action` attribute picks where the form submits (#961): the site
	search URL by default, the current page, or a same-site path. A
	host-stamped `_resolvedFormAction` wins over all three.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\SearchForm;

	$postType = strtolower( (string) ( $attributes['postType'] ?? 'post' ) );
	$postType = preg_replace( '/[^a-z0-9_-]/', '', $postType );

	$action = SearchForm::action( $attributes );
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-artisanpack-search-filters', 'ap-search-filters' ] ) !!}>
	<form class="ap-search-filters__form" method="get" action="{{ $action }}">
		<input type="hidden" name="post_type" value="{{ $postType }}" />
		{!! $innerBlocksHtml !!}
	</form>
</div>
