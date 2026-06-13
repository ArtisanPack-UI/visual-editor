{{--
	artisanpack/search-filters — GET form wrapper (#502).

	Drops a `<form method="get">` around the inner filter controls and
	pins them to a single post type via a hidden `post_type` input. The
	host's stamped `_resolvedFormAction` (or the live request base URL)
	provides the form's action URL so the form posts back to the site
	root by default.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$postType = strtolower( (string) ( $attributes['postType'] ?? 'post' ) );
	$postType = preg_replace( '/[^a-z0-9_-]/', '', $postType );

	$rawAction = '';
	if ( array_key_exists( '_resolvedFormAction', $attributes ) ) {
		$rawAction = is_scalar( $attributes['_resolvedFormAction'] )
			? (string) $attributes['_resolvedFormAction']
			: '';
	} elseif ( function_exists( 'url' ) ) {
		$rawAction = url( '/' );
	}
	$action = UrlSanitizer::safe( $rawAction );

	if ( '' === $action ) {
		$action = '/';
	}
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-search-filters' ] ) !!}>
	<form class="ap-search-filters__form" method="get" action="{{ $action }}">
		<input type="hidden" name="post_type" value="{{ $postType }}" />
		{!! $innerBlocksHtml !!}
	</form>
</div>
