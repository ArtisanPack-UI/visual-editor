@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

	$isUserLoggedIn = isset( $attributes['_resolvedIsUserLoggedIn'] )
		&& true === $attributes['_resolvedIsUserLoggedIn'];
	// Run the resolved URL through the same sanitizer the React / Vue
	// renderers apply so a stored block tree can't smuggle a
	// `javascript:` / `data:` href onto the rendered page, and so the
	// empty-URL guard below stays in lockstep with the parity contract.
	$url = UrlSanitizer::safe(
		isset( $attributes['_resolvedLoginoutUrl'] ) && is_string( $attributes['_resolvedLoginoutUrl'] )
			? $attributes['_resolvedLoginoutUrl']
			: ''
	);
	$label = isset( $attributes['_resolvedLoginoutLabel'] ) && is_string( $attributes['_resolvedLoginoutLabel'] )
		? $attributes['_resolvedLoginoutLabel']
		: ( $isUserLoggedIn ? __( 'Log out' ) : __( 'Log in' ) );
	$displayLoginAsForm = isset( $attributes['displayLoginAsForm'] ) && true === $attributes['displayLoginAsForm'];
	$loginFormHtml = isset( $attributes['_resolvedLoginFormHtml'] ) && is_string( $attributes['_resolvedLoginFormHtml'] )
		? $attributes['_resolvedLoginFormHtml']
		: '';
	$showForm = ! $isUserLoggedIn && $displayLoginAsForm && '' !== $loginFormHtml;

	// Mirror upstream's class contract: `logged-in` / `logged-out` flag
	// + the form modifier when the inline form is shown. The resolver
	// pre-computes the same string for parity with React / Vue, but we
	// fall back to the boolean flag when the resolver was skipped so
	// hosts that stamp `_resolvedIsUserLoggedIn` themselves still get
	// the right wrapper class.
	$resolvedClass = isset( $attributes['_resolvedLoginoutClass'] ) && is_string( $attributes['_resolvedLoginoutClass'] )
		? $attributes['_resolvedLoginoutClass']
		: ( $isUserLoggedIn ? 'logged-in' : ( $showForm ? 'logged-out has-login-form' : 'logged-out' ) );
	$baseClasses = array_values( array_filter( preg_split( '/\s+/', $resolvedClass ) ?: [] ) );
@endphp
<div{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}>
@if ( $showForm )
{!! $loginFormHtml !!}
@elseif ( '' === $url )
{{-- React + Vue parity: when the sanitized URL is empty (resolver
     skipped or the host stamped a disallowed scheme), emit the label
     in a plain text node so the wrapper still tells consumers what
     would have been a link without rendering an inert `href=""`. --}}
{{ $label }}
@else
<a href="{{ $url }}">{{ $label }}</a>
@endif
</div>
