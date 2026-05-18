@php
	use ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

	$overlayMenu = isset( $attributes['overlayMenu'] ) && is_string( $attributes['overlayMenu'] ) ? $attributes['overlayMenu'] : 'mobile';

	// `orientation` ships as a top-level attribute on older saves and
	// under `layout.orientation` on newer ones (Gutenberg's flex
	// layout). Check both, preferring the explicit attribute.
	$layout      = is_array( $attributes['layout'] ?? null ) ? $attributes['layout'] : [];
	$orientation = isset( $attributes['orientation'] ) && is_string( $attributes['orientation'] )
		? $attributes['orientation']
		: ( isset( $layout['orientation'] ) && is_string( $layout['orientation'] ) ? $layout['orientation'] : 'horizontal' );

	// Item justification — Gutenberg writes the modern flex-layout
	// path to `layout.justifyContent`. The legacy `itemsJustification`
	// attribute is still emitted by some older saves, so honor both
	// with the modern path winning when both are set (Keystone #52).
	$itemsJustify = isset( $layout['justifyContent'] ) && is_string( $layout['justifyContent'] )
		? $layout['justifyContent']
		: ( isset( $attributes['itemsJustification'] ) && is_string( $attributes['itemsJustification'] ) ? $attributes['itemsJustification'] : '' );

	$ariaLabel = isset( $attributes['ariaLabel'] ) && is_string( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : '';

	$baseClasses = [ 'wp-block-navigation' ];

	if ( 'horizontal' === $orientation ) {
		$baseClasses[] = 'is-horizontal';
	} elseif ( 'vertical' === $orientation ) {
		$baseClasses[] = 'is-vertical';
	}

	if ( '' !== $itemsJustify ) {
		$baseClasses[] = 'items-justified-' . $itemsJustify;
	}

	if ( 'never' !== $overlayMenu ) {
		$baseClasses[] = 'is-responsive';
	}

	// Nav-block-specific color attributes (Keystone #53). The shared
	// `BlockSupports::applyColor` handles `textColor` / `backgroundColor`
	// + `style.color.*`, but `core/navigation` uses its own legacy
	// attribute names — `customTextColor` / `customBackgroundColor`
	// for the inline-hex pickers, plus a parallel `overlay*` family
	// for the responsive overlay container. Translate the picks into
	// `has-{slug}-*` classes / inline styles directly so neither the
	// canvas nor the front-end is left with picker choices that
	// visually no-op.
	$navStyles = [];

	$customTextColor = isset( $attributes['customTextColor'] ) && is_string( $attributes['customTextColor'] )
		? trim( $attributes['customTextColor'] )
		: '';

	$customBackgroundColor = isset( $attributes['customBackgroundColor'] ) && is_string( $attributes['customBackgroundColor'] )
		? trim( $attributes['customBackgroundColor'] )
		: '';

	// `textColor` / `backgroundColor` (preset slugs) are already added
	// to the wrapper attrs by `BlockSupports::applyColor`. The block-
	// specific `custom*Color` attributes need inline-style handling
	// here. A preset slug WINS over a custom-hex if both are present
	// — mirrors WP core's nav block serializer.
	$textSlug = isset( $attributes['textColor'] ) && is_string( $attributes['textColor'] ) && '' !== $attributes['textColor'];

	if ( ! $textSlug && '' !== $customTextColor ) {
		$navStyles[]   = 'color: ' . $customTextColor;
		$baseClasses[] = 'has-text-color';
	}

	$backgroundSlug = isset( $attributes['backgroundColor'] ) && is_string( $attributes['backgroundColor'] ) && '' !== $attributes['backgroundColor'];

	if ( ! $backgroundSlug && '' !== $customBackgroundColor ) {
		$navStyles[]   = 'background-color: ' . $customBackgroundColor;
		$baseClasses[] = 'has-background';
	}

	$ariaLabel = isset( $attributes['ariaLabel'] ) && is_string( $attributes['ariaLabel'] ) ? $attributes['ariaLabel'] : '';

	$navAttrs = '';

	if ( '' !== $ariaLabel ) {
		$navAttrs .= sprintf( ' aria-label="%s"', e( $ariaLabel ) );
	}

	if ( [] !== $navStyles ) {
		$navAttrs .= sprintf( ' style="%s"', e( implode( '; ', $navStyles ) ) );
	}

	// Overlay rendering (Keystone #54). When `overlayMenu` is
	// `mobile` (default) or `always`, the nav block ships a
	// hamburger-toggle button + a `wp-block-navigation__responsive-
	// container` wrapper around the items. Renderer-blade's bundled
	// style.css already has the breakpoint media queries, the
	// `is-menu-open` transitions, and the layout rules — the toggle
	// JS at the bottom of this file flips the `is-menu-open` class
	// and `aria-hidden`. `overlayMenu: "never"` skips the overlay
	// entirely and renders the bare inline `<ul>` for the always-
	// inline case.
	$wantsOverlay = 'never' !== $overlayMenu;

	$overlayTracker = $wantsOverlay ? app( NavigationOverlayTracker::class ) : null;
	$overlayId      = $wantsOverlay ? $overlayTracker->nextOverlayId() : '';
	$emitScript     = $wantsOverlay && ! $overlayTracker->hasEmittedScript();

	if ( $emitScript ) {
		$overlayTracker->markScriptEmitted();
	}

	$overlayClasses = [ 'wp-block-navigation__responsive-container' ];

	if ( 'always' === $overlayMenu ) {
		$overlayClasses[] = 'is-always-overlay';
	}

	// Open-button label — `openSubmenusOnClick` is an unrelated
	// attribute; the open-menu button label has no dedicated attribute
	// in the block, so use a sensible default. WP core uses "Menu" —
	// matches the dialog's aria-label.
	$openLabel  = '' !== $ariaLabel ? $ariaLabel : 'Menu';
	$closeLabel = 'Close menu';

	// Hamburger icon mirrors the upstream nav block's three-line SVG.
	$hamburgerIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><rect x="4" y="7.5" width="16" height="1.5"/><rect x="4" y="15" width="16" height="1.5"/></svg>';
	$closeIcon     = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"/></svg>';
@endphp
<nav{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}{!! $navAttrs !!}>
@if ( $wantsOverlay )
	<button type="button" aria-haspopup="dialog" aria-label="{{ $openLabel }}" class="wp-block-navigation__responsive-container-open" data-ap-nav-overlay-open="{{ $overlayId }}">
		{!! $hamburgerIcon !!}
	</button>
	<div class="{{ implode( ' ', $overlayClasses ) }}" id="{{ $overlayId }}" aria-hidden="true">
		<div class="wp-block-navigation__responsive-close" tabindex="-1" data-ap-nav-overlay-close>
			<div class="wp-block-navigation__responsive-dialog" aria-label="{{ $openLabel }}" aria-modal="true" role="dialog">
				<button type="button" aria-label="{{ $closeLabel }}" class="wp-block-navigation__responsive-container-close" data-ap-nav-overlay-close>
					{!! $closeIcon !!}
				</button>
				<div class="wp-block-navigation__responsive-container-content" id="{{ $overlayId }}-content">
					<ul class="wp-block-navigation__container">{!! $innerBlocksHtml !!}</ul>
				</div>
			</div>
		</div>
	</div>
@else
	<ul class="wp-block-navigation__container">{!! $innerBlocksHtml !!}</ul>
@endif
</nav>
@if ( $emitScript )
<script>
/*! Keystone #54 — nav overlay toggle. Tiny inline controller; emitted once per response. */
(function(){if(window.__apNavOverlayInit)return;window.__apNavOverlayInit=true;
function open(c){c.classList.add('is-menu-open');c.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';
var d=c.querySelector('[role="dialog"]');if(d){var f=d.querySelector('button,[href],[tabindex]:not([tabindex="-1"])');if(f)f.focus();}}
function close(c){c.classList.remove('is-menu-open');c.setAttribute('aria-hidden','true');document.body.style.overflow='';
var t=document.querySelector('[data-ap-nav-overlay-open="'+c.id+'"]');if(t)t.focus();}
document.addEventListener('click',function(e){var o=e.target.closest('[data-ap-nav-overlay-open]');
if(o){e.preventDefault();var t=document.getElementById(o.getAttribute('data-ap-nav-overlay-open'));if(t)open(t);return;}
var x=e.target.closest('[data-ap-nav-overlay-close]');if(x){e.preventDefault();
var c=x.closest('.wp-block-navigation__responsive-container');if(c)close(c);}});
document.addEventListener('keydown',function(e){if(e.key!=='Escape')return;
var c=document.querySelector('.wp-block-navigation__responsive-container.is-menu-open');if(c){e.preventDefault();close(c);}});})();
</script>
@endif
