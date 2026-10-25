@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\SocialLinkServices;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	// #1023 — one Social Icons service. Mirrors WordPress's
	// `render_block_core_social_link()`: an icon without a URL renders
	// nothing, a bare address on the Mail service becomes a `mailto:`
	// link, a bare domain gets `https://`, and the parent's new-tab / show-text settings arrive as
	// `_veSocialLinks`. Colours, border and hover styles come from the
	// parent `<ul>` (see social-links.blade.php), so the `<li>` has no
	// inline style.
	$context = is_array( $attributes['_veSocialLinks'] ?? null ) ? $attributes['_veSocialLinks'] : [];

	$service = is_string( $attributes['service'] ?? null ) ? $attributes['service'] : '';
	$service = 1 === preg_match( '/^[a-z0-9-]+$/D', $service ) ? $service : '';
	$known   = '' !== $service && isset( SocialLinkServices::SERVICES[ $service ] );
	$glyph   = SocialLinkServices::get( $service );

	$url = trim( is_string( $attributes['url'] ?? null ) ? $attributes['url'] : '' );

	// The React and Vue renderers use the same address pattern, so every
	// renderer agrees on which bare addresses become `mailto:` links.
	if ( 'mail' === $service && '' !== $url && ! str_starts_with( strtolower( $url ), 'mailto:' ) && 1 === preg_match( '/^[^\s@]+@[^\s@]+\.[^\s@]+$/D', $url ) ) {
		$url = 'mailto:' . $url;
	}

	// A bare domain such as `twitter.com/acme` gets `https://`, as in
	// WordPress and the editor's `prependHTTPS()`. A scheme, fragment,
	// query or relative path is left alone.
	if ( '' !== $url && 1 !== preg_match( '/^(?:[a-z]+:|#|\?|\.|\/)/i', $url ) ) {
		$url = 'https://' . $url;
	}

	$href = UrlSanitizer::safe( $url );

	$label = trim( is_string( $attributes['label'] ?? null ) ? $attributes['label'] : '' );
	$text  = '' !== $label ? $label : ( $known ? __( $glyph['label'] ) : __( 'Social Icon' ) );

	$relTokens = preg_split( '/\s+/', trim( is_string( $attributes['rel'] ?? null ) ? $attributes['rel'] : '' ) ) ?: [];
	$newTab    = true === ( $context['openInNewTab'] ?? false );

	if ( $newTab ) {
		$relTokens[] = 'noopener';
		$relTokens[] = 'nofollow';
	}

	$rel = implode( ' ', array_values( array_unique( array_filter( $relTokens, static fn ( string $token ): bool => '' !== $token ) ) ) );

	$anchorAttrs = sprintf( ' href="%s" class="wp-block-social-link-anchor"', e( $href ) );

	if ( '' !== $rel ) {
		$anchorAttrs .= sprintf( ' rel="%s"', e( $rel ) );
	}

	if ( $newTab ) {
		$anchorAttrs .= ' target="_blank"';
	}

	$classes    = [ 'wp-social-link', 'wp-social-link-' . ( '' !== $service ? $service : 'chain' ), 'wp-block-social-link' ];
	$labelClass = 'wp-block-social-link-label' . ( true === ( $context['showLabels'] ?? false ) ? '' : ' screen-reader-text' );
@endphp
@if ( '' !== $href )
<li{!! BlockSupports::wrapperAttrs( $attributes, $classes ) !!}><a{!! $anchorAttrs !!}><svg xmlns="http://www.w3.org/2000/svg" viewBox="{{ $glyph['viewBox'] }}" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="{{ $glyph['path'] }}"></path></svg><span class="{{ $labelClass }}">{{ $text }}</span></a></li>
@endif
