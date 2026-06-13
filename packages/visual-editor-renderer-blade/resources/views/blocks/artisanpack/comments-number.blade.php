@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$rawCount = $attributes['_resolvedCommentCount'] ?? 0;
	$count    = is_numeric( $rawCount ) ? (int) $rawCount : 0;

	if ( $count < 0 ) {
		$count = 0;
	}

	$singular = (string) ( $attributes['singularCommentText'] ?? 'Comment' );
	$plural   = (string) ( $attributes['pluralCommentText'] ?? 'Comments' );
	$label    = 1 === $count ? $singular : $plural;

	$line = trim( $count . ' ' . $label );

	$baseClasses = [ 'ap-comments-number' ];
@endphp
<p{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>{{ $line }}</p>
