@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$citation = (string) ( $attributes['citation'] ?? '' );
@endphp
<blockquote{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-quote' ] ) !!}>
	{!! $innerBlocksHtml !!}
	@if ( '' !== trim( $citation ) )
		<cite>{!! $citation !!}</cite>
	@endif
</blockquote>
