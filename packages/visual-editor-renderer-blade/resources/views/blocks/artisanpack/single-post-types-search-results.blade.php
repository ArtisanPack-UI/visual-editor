{{--
	artisanpack/single-post-types-search-results — conditional section (#502).

	Emits the wrapper + inner blocks only when the section applies to the
	current request. The host can short-circuit the request lookup by
	stamping `_resolvedActive` as a boolean. Otherwise the renderer
	matches the configured `postType` against `?post_type=…`, treating
	the value `all` as the no-parameter case.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$postType = strtolower( (string) ( $attributes['postType'] ?? 'all' ) );
	$postType = preg_replace( '/[^a-z0-9_-]/', '', $postType );

	if ( array_key_exists( '_resolvedActive', $attributes ) ) {
		$isActive = (bool) $attributes['_resolvedActive'];
	} else {
		$requested = '';
		if ( function_exists( 'request' ) ) {
			$raw       = request()->query( 'post_type', null );
			$requested = is_scalar( $raw ) ? (string) $raw : '';
		}
		// Normalize the incoming query value the same way the saved
		// `$postType` was sanitized so a request for `?post_type=Post`
		// still matches a block configured for `post`.
		$requested = strtolower( $requested );
		$requested = preg_replace( '/[^a-z0-9_-]/', '', $requested );

		if ( '' === $requested ) {
			$isActive = 'all' === $postType;
		} else {
			$isActive = $requested === $postType;
		}
	}
@endphp
@if ( $isActive )
	<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'ap-single-post-types-search-results' ] ) !!}>
		{!! $innerBlocksHtml !!}
	</div>
@endif
