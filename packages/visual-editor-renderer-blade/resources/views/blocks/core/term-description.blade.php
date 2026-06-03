@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$description = isset( $attributes['_resolvedTermDescription'] ) && is_string( $attributes['_resolvedTermDescription'] )
		? $attributes['_resolvedTermDescription']
		: '';
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-term-description' ] ) !!}>@if ( '' !== $description )<p>{!! $description !!}</p>@endif</div>
