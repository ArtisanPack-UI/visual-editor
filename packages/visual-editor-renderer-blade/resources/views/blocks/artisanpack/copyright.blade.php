@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$validTypes = [ 'icon-text', 'icon-only', 'text-only' ];

	$copyrightType = (string) ( $attributes['copyrightType'] ?? 'icon-text' );

	if ( ! in_array( $copyrightType, $validTypes, true ) ) {
		$copyrightType = 'icon-text';
	}

	$copyrightText = trim( (string) ( $attributes['copyrightText'] ?? 'Copyright' ) );

	$currentYear = gmdate( 'Y' );

	if ( 'icon-only' === $copyrightType ) {
		$line = '© ' . $currentYear;
	} elseif ( 'text-only' === $copyrightType ) {
		$line = '' === $copyrightText ? $currentYear : $copyrightText . ' ' . $currentYear;
	} else {
		$line = '' === $copyrightText
			? '© ' . $currentYear
			: '© ' . $copyrightText . ' ' . $currentYear;
	}

	$baseClasses = [ 'ap-copyright' ];
@endphp
<p{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>{{ $line }}</p>
