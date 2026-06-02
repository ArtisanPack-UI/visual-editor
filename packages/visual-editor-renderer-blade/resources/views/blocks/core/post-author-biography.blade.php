@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$bio = isset( $attributes['_resolvedAuthorBio'] ) && is_string( $attributes['_resolvedAuthorBio'] ) ? $attributes['_resolvedAuthorBio'] : '';
@endphp
<p{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-post-author-biography' ] ) !!}>{!! $bio !!}</p>
