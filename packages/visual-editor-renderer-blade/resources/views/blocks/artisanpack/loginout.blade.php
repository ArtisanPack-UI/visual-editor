@php
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$isUserLoggedIn = isset( $attributes['_resolvedIsUserLoggedIn'] )
		&& true === $attributes['_resolvedIsUserLoggedIn'];
	$url   = isset( $attributes['_resolvedLoginoutUrl'] ) && is_string( $attributes['_resolvedLoginoutUrl'] )
		? $attributes['_resolvedLoginoutUrl']
		: '';
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
@else
<a href="{{ $url }}">{{ $label }}</a>
@endif
</div>
