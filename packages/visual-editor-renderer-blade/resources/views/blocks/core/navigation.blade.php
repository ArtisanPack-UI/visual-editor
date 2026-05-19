@php
	use ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;
	use ArtisanPackUI\VisualEditorRendererBlade\Support\ElementsSupport;

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

	// Elements-API support (Keystone #56). The dedicated Link color
	// picker writes to `style.elements.link.color.text` — a path
	// separate from `textColor` / `customTextColor` (which color the
	// nav wrapper itself, not its `<a>` descendants). Compile the
	// elements subtree into a per-block `wp-elements-{hash}` scoping
	// class + scoped inline `<style>` so the picked color reaches only
	// this nav block's links, not bleeding across other nav blocks on
	// the same page.
	$elementsSupport = ElementsSupport::compile( $attributes );

	if ( '' !== $elementsSupport['class'] ) {
		$baseClasses[] = $elementsSupport['class'];
	}

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

	// Overlay-specific color attributes (Keystone #54). The nav block
	// ships a parallel `overlayTextColor` / `overlayBackgroundColor`
	// (preset slugs) + `customOverlay*` (hex) family that targets the
	// responsive container, NOT the inline nav. Without this the
	// overlay inherits the inline nav's colors — a red nav on
	// desktop renders the mobile overlay red too even when the
	// author picked a dark overlay background.
	$overlayStyles = [];

	$overlayBgSlug = isset( $attributes['overlayBackgroundColor'] ) && is_string( $attributes['overlayBackgroundColor'] )
		? trim( $attributes['overlayBackgroundColor'] )
		: '';
	$overlayBgCustom = isset( $attributes['customOverlayBackgroundColor'] ) && is_string( $attributes['customOverlayBackgroundColor'] )
		? trim( $attributes['customOverlayBackgroundColor'] )
		: '';
	$overlayTextSlug = isset( $attributes['overlayTextColor'] ) && is_string( $attributes['overlayTextColor'] )
		? trim( $attributes['overlayTextColor'] )
		: '';
	$overlayTextCustom = isset( $attributes['customOverlayTextColor'] ) && is_string( $attributes['customOverlayTextColor'] )
		? trim( $attributes['customOverlayTextColor'] )
		: '';

	if ( '' !== $overlayBgSlug ) {
		$overlayClasses[] = 'has-' . $overlayBgSlug . '-background-color';
		$overlayClasses[] = 'has-background';
	} elseif ( '' !== $overlayBgCustom ) {
		$overlayStyles[]  = 'background-color: ' . $overlayBgCustom;
		$overlayClasses[] = 'has-background';
	}

	if ( '' !== $overlayTextSlug ) {
		$overlayClasses[] = 'has-' . $overlayTextSlug . '-color';
		$overlayClasses[] = 'has-text-color';
	} elseif ( '' !== $overlayTextCustom ) {
		$overlayStyles[]  = 'color: ' . $overlayTextCustom;
		$overlayClasses[] = 'has-text-color';
	}

	$overlayStyleAttr = [] === $overlayStyles ? '' : sprintf( ' style="%s"', e( implode( '; ', $overlayStyles ) ) );

	// Overlay template-part contents (Keystone #58). When the author
	// picks (or creates) an overlay via Gutenberg's overlay-template
	// dropdown, the chosen `template-part` slug lands on
	// `attributes.overlay`. Honor it on the front-end by rendering
	// that part's blocks INSIDE the responsive container instead of
	// the duplicate-of-the-inline-menu fallback — matching WP core's
	// behavior where the mobile drawer can show whatever the author
	// authored in the overlay (custom layout, search, social links,
	// CTA, etc.) rather than a copy of the desktop menu.
	//
	// Resolution goes through cms-framework's TemplatePartResolver
	// (the same path the editor uses), so theme-file overrides and
	// DB rows compose the same way they do in the canvas. Bail
	// gracefully on any of: cms-framework absent, slug unresolvable,
	// resolved record not in the navigation-overlay area, blocks
	// payload missing, or any thrown exception (partial install,
	// missing migrations). Each fallback path lands on the
	// inline-menu duplicate the bundled style.css's mobile rules
	// were already styled against.
	$overlayInnerHtml = null;

	if ( $wantsOverlay ) {
		$overlaySlug = isset( $attributes['overlay'] ) && is_string( $attributes['overlay'] )
			? trim( $attributes['overlay'] )
			: '';

		if ( '' !== $overlaySlug ) {
			$resolverClass = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver';

			if ( class_exists( $resolverClass ) ) {
				try {
					$resolved = app( $resolverClass )->resolve( $overlaySlug );

					if ( null !== $resolved && 'navigation-overlay' === ( $resolved->area ?? null ) ) {
						$overlayBlocks = is_array( $resolved->blocks ?? null ) ? $resolved->blocks : [];

						if ( [] !== $overlayBlocks ) {
							$overlayInnerHtml = app( \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::class )
								->render( $overlayBlocks );
						}
					}
				} catch ( \Throwable $e ) {
					\Illuminate\Support\Facades\Log::warning( 'Navigation overlay resolution failed', [
						'slug'      => $overlaySlug,
						'exception' => $e->getMessage(),
					] );
				}
			}
		}
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
@if ( '' !== $elementsSupport['style'] )
<style>{!! $elementsSupport['style'] !!}</style>
@endif
<nav{!! BlockSupports::wrapperAttrs( $attributes, $baseClasses ) !!}{!! $navAttrs !!}>
@if ( $wantsOverlay )
	<button type="button" aria-haspopup="dialog" aria-label="{{ $openLabel }}" class="wp-block-navigation__responsive-container-open" data-ap-nav-overlay-open="{{ $overlayId }}">
		{!! $hamburgerIcon !!}
	</button>
	<div class="{{ implode( ' ', $overlayClasses ) }}" id="{{ $overlayId }}" aria-hidden="true"{!! $overlayStyleAttr !!}>
		<div class="wp-block-navigation__responsive-close" tabindex="-1" data-ap-nav-overlay-backdrop>
			<div class="wp-block-navigation__responsive-dialog" aria-label="{{ $openLabel }}" aria-modal="true" role="dialog">
				<button type="button" aria-label="{{ $closeLabel }}" class="wp-block-navigation__responsive-container-close" data-ap-nav-overlay-close>
					{!! $closeIcon !!}
				</button>
				<div class="wp-block-navigation__responsive-container-content{{ null !== $overlayInnerHtml ? ' has-overlay-template' : '' }}" id="{{ $overlayId }}-content">
					{{-- Keystone #58: when an overlay template-part resolves,
					     render BOTH the regular menu (shown inline on
					     desktop / when the drawer is closed) AND the overlay
					     content (shown only while the drawer is open). The
					     `has-overlay-template` marker scopes the emitted
					     toggle CSS so navs WITHOUT an overlay keep their
					     menu visible in the open drawer. --}}
					<ul class="wp-block-navigation__container">{!! $innerBlocksHtml !!}</ul>
					@if ( null !== $overlayInnerHtml )
						<div class="wp-block-navigation__overlay-content">{!! $overlayInnerHtml !!}</div>
					@endif
				</div>
			</div>
		</div>
	</div>
@else
	<ul class="wp-block-navigation__container">{!! $innerBlocksHtml !!}</ul>
@endif
</nav>
@if ( $emitScript )
<style>
/*! Keystone #54 — nav overlay layout fix-ups. Emitted once per response. */
/* The bundled style.css reads `--navigation-layout-justification-setting`
 * and `--navigation-layout-justify` on the `<ul>`, each `<li>`, and the
 * page-list inside `is-menu-open`. When the parent nav has
 * `items-justified-right`, those vars resolve to `flex-end` and items
 * render flush against the right edge of the overlay (which is wrong —
 * an open mobile overlay should stack items full-width starting from
 * the inline-start edge, regardless of the desktop justification).
 *
 * Override the vars on the responsive container so the bundled rules
 * (whose specificity we don't reliably beat with class selectors)
 * read stretch / start values instead. Vars cascade to all descendants,
 * so a single declaration on the container fixes the `<ul>`, `<li>`,
 * `<a>`, and the page-list at once. */
.wp-block-navigation__responsive-container.is-menu-open{--navigation-layout-justification-setting:stretch;--navigation-layout-justify:flex-start;--navigation-layout-align:stretch;--wp--style--root--padding-top:1.5rem;--wp--style--root--padding-right:1.5rem;--wp--style--root--padding-bottom:1.5rem;--wp--style--root--padding-left:1.5rem;}
/*! Keystone #58 — overlay template-part swap. A nav whose `overlay`
 * attribute resolves renders BOTH the regular menu and the overlay
 * content (marked `has-overlay-template`). The overlay is hidden by
 * default (the inline desktop view shows the menu); once the drawer
 * opens (`is-menu-open`) the menu is swapped out for the overlay's
 * content. Scoped to `.has-overlay-template` so navs without an
 * overlay keep showing their menu in the open drawer. */
.wp-block-navigation__responsive-container-content.has-overlay-template .wp-block-navigation__overlay-content{display:none;}
.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-content.has-overlay-template .wp-block-navigation__container{display:none;}
.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation__responsive-container-content.has-overlay-template .wp-block-navigation__overlay-content{display:block;}
</style>
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
var c=x.closest('.wp-block-navigation__responsive-container');if(c)close(c);return;}
/* Backdrop click — only when the click target IS the backdrop itself, not a descendant. Without the identity check, every menu-link click bubbles up to the backdrop and triggers a close + preventDefault, breaking link navigation. */
var b=e.target.closest('[data-ap-nav-overlay-backdrop]');
if(b&&e.target===b){var c=b.closest('.wp-block-navigation__responsive-container');if(c)close(c);}});
document.addEventListener('keydown',function(e){if(e.key!=='Escape')return;
var c=document.querySelector('.wp-block-navigation__responsive-container.is-menu-open');if(c){e.preventDefault();close(c);}});})();
</script>
@endif
