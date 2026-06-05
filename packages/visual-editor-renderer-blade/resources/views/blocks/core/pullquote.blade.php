@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$value    = (string) ( $attributes['value'] ?? '' );
	$citation = (string) ( $attributes['citation'] ?? '' );
@endphp
<figure{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-pullquote' ] ) !!}>
	<blockquote>
		@if ( '' !== trim( $value ) )
			{!! $value !!}
		@endif
		@if ( '' !== trim( $citation ) )
			<cite>{!! $citation !!}</cite>
		@endif
	</blockquote>
</figure>
