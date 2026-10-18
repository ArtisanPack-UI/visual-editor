{{--
	artisanpack/post-reading-time — estimated reading time (#943).

	`PostResolver` stamps `_resolvedReadingTime` (whole minutes) from the
	post's word count. Prints "{prefix}{minutes} {suffix}", e.g. "6 min read",
	and nothing outside a post context or for a post with no text.
--}}
@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$rawMinutes = $attributes['_resolvedReadingTime'] ?? null;
	$minutes    = is_numeric( $rawMinutes ) ? max( 0, (int) $rawMinutes ) : 0;

	$prefix = is_string( $attributes['prefix'] ?? null ) ? $attributes['prefix'] : '';
	$suffix = is_string( $attributes['suffix'] ?? null ) ? $attributes['suffix'] : 'min read';
	$line   = $prefix . $minutes . ( '' !== trim( $suffix ) ? ' ' . trim( $suffix ) : '' );
@endphp
@if ( $minutes > 0 )
<div{!! BlockSupports::wrapperAttrs( $attributes, [ 'wp-block-artisanpack-post-reading-time' ] ) !!}>{{ $line }}</div>
@endif
