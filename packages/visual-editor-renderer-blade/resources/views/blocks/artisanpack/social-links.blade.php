@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\LayoutSupport;

	// #1023 — Social Icons. Mirrors WordPress's `core/social-links` save
	// markup so the bundled block-library CSS styles it unchanged. Every
	// icon's colours, border, padding and hover styles ride on the `<ul>` as
	// `--ap-social-links-*` properties (applied by social-icons.css), each
	// with a `has-icon-*` class that is only added when the sanitized
	// value survives. New tab and show text reach the icons through the
	// `_veSocialLinks` stamp in BlockRenderer.
	$size = (string) ( $attributes['size'] ?? '' );
	$size = in_array( $size, [ 'has-small-icon-size', 'has-normal-icon-size', 'has-large-icon-size', 'has-huge-icon-size' ], true ) ? $size : '';

	$borderStyle = is_string( $attributes['iconBorderStyle'] ?? null ) && in_array( $attributes['iconBorderStyle'], [ 'none', 'solid', 'dashed', 'dotted', 'double' ], true )
		? $attributes['iconBorderStyle']
		: '';

	$properties = [
		'--ap-social-links-color'              => $attributes['iconColorValue'] ?? null,
		'--ap-social-links-bg'                 => $attributes['iconBackgroundColorValue'] ?? null,
		'--ap-social-links-border-color'       => $attributes['iconBorderColorValue'] ?? null,
		'--ap-social-links-border-width'       => $attributes['iconBorderWidth'] ?? null,
		'--ap-social-links-border-style'       => $borderStyle,
		'--ap-social-links-radius'             => $attributes['iconBorderRadius'] ?? null,
		'--ap-social-links-padding'            => $attributes['iconPadding'] ?? null,
		'--ap-social-links-hover-color'        => $attributes['iconHoverColorValue'] ?? null,
		'--ap-social-links-hover-bg'           => $attributes['iconHoverBackgroundColorValue'] ?? null,
		'--ap-social-links-hover-border-color' => $attributes['iconHoverBorderColorValue'] ?? null,
	];

	$styles = [];

	foreach ( $properties as $property => $raw ) {
		$value = BlockSupports::safeCssValue( is_string( $raw ) ? $raw : '' );

		if ( null !== $value ) {
			$styles[ $property ] = $property . ': ' . $value;
		}
	}

	$has = static fn ( string ...$names ): bool => [] !== array_intersect( $names, array_keys( $styles ) );

	$baseClasses = array_merge(
		[ 'wp-block-artisanpack-social-links', 'wp-block-social-links' ],
		'' !== $size ? [ $size ] : [],
		true === ( $attributes['showLabels'] ?? false ) ? [ 'has-visible-labels' ] : [],
		$has( '--ap-social-links-color' ) ? [ 'has-icon-color' ] : [],
		$has( '--ap-social-links-bg' ) ? [ 'has-icon-background-color' ] : [],
		$has( '--ap-social-links-border-color', '--ap-social-links-border-width', '--ap-social-links-border-style' ) ? [ 'has-icon-border' ] : [],
		$has( '--ap-social-links-radius' ) ? [ 'has-icon-border-radius' ] : [],
		$has( '--ap-social-links-padding' ) ? [ 'has-icon-padding' ] : [],
		$has( '--ap-social-links-hover-color' ) ? [ 'has-icon-hover-color' ] : [],
		$has( '--ap-social-links-hover-bg' ) ? [ 'has-icon-hover-background-color' ] : [],
		$has( '--ap-social-links-hover-border-color' ) ? [ 'has-icon-hover-border-color' ] : [],
		LayoutSupport::pair( 'social-links', 'is-layout-flex' ),
		LayoutSupport::containerClasses( $attributes, 'social-links', 'is-layout-flex' ),
		LayoutSupport::modifierClasses( $attributes )
	);
@endphp
<ul{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses, array_values( $styles ) ) !!}>{!! $innerBlocksHtml !!}</ul>
