<?php

/**
 * Sends a page only the CSS it needs (#1032).
 *
 * `<x-ve-blocks-styles />` prints every block stylesheet plus about 100 KB of
 * inline tokens and global styles. This class works on the finished HTML,
 * where it does two things:
 *
 *  1. Removes the `frontend/*` stylesheets nothing in the body can match. A
 *     stylesheet stays when its {@see self::STYLESHEET_MARKERS} appear in the
 *     body. Reading the output rather than the block types the renderer saw
 *     also covers markup the renderer never touched this request (a cached
 *     fragment, a page-cache hit, hand-written theme markup using a block's
 *     classes) and classes no block type predicts (the inline-icon format,
 *     the flex and photo-grid supports).
 *  2. Moves the head's inline `<style data-ve-*>` run into one fingerprinted
 *     file, through {@see GeneratedStylesheets}.
 *
 * Both steps fail open. A stray marker in the body (a class name in a script
 * or a query string) keeps a stylesheet, and any problem leaves the inline
 * styles where they are.
 *
 * The block library's `style.css` and `theme.css` always stay, since they
 * cover every core block. Hosts keep a conditional stylesheet with the
 * `ap.visualEditor.requiredStylesheets` filter, or turn either step off under
 * `artisanpack.visual-editor.frontend_styles`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class FrontendStylesOptimizer
{
	/**
	 * Tag attribute of each conditional stylesheet, and the class fragments
	 * that mean a page needs it. Every selector in a stylesheet must contain
	 * one of its markers; `FrontendStylesOptimizerTest` enforces that.
	 *
	 * @since 1.19.0
	 *
	 * @var array<string, array<int, string>>
	 */
	public const STYLESHEET_MARKERS = [
		'data-ve-accordion'        => [ 'ap-accordion' ],
		'data-ve-tabs'             => [ 'ap-tab' ],
		'data-ve-grid'             => [ 'ap-grid' ],
		'data-ve-marquee'          => [ 'ap-marquee' ],
		'data-ve-social-icons'     => [ 'wp-social-link', 'wp-block-artisanpack-social-links', 'ap-social-share-content', 'ap-author-social-icons' ],
		'data-ve-inline-icon'      => [ 'ap-inline-icon' ],
		'data-ve-breadcrumbs'      => [ 'ap-breadcrumbs' ],
		'data-ve-query-pagination' => [ 'wp-block-query-pagination' ],
		'data-ve-flex-layout'      => [ 'ap-flex', 'ap-gap-', 'ap-items-', 'ap-justify-', 'ap-content-', 'ap-self-', 'ap-grow-', 'ap-shrink-', 'ap-order-', 'ap-basis-' ],
		'data-ve-photo-grid'       => [ 'has-photo-grid' ],
		'data-ve-post-template'    => [ 'wp-block-post-template', 'ap-related-posts' ],
		'data-ve-post-variant'     => [ 'wp-block-post-template', 'ap-related-posts' ],
		'data-ve-masonry'          => [ 'is-layout-masonry', 'ap-grid-layout-masonry', 'ap-masonry' ],
	];

	/**
	 * Inline tags that move into the generated file, in the order the
	 * component prints them.
	 *
	 * @since 1.19.0
	 *
	 * @var array<int, string>
	 */
	public const COMPILED_STYLE_TAGS = [
		'data-ve-theme-tokens',
		'data-ve-spacing-presets',
		'data-ve-default-tokens',
		'data-ve-default-styles',
		'data-ve-responsive-stacking',
		'data-ve-layout-baseline',
		'data-ve-global-styles',
	];

	public function __construct( protected GeneratedStylesheets $stylesheets )
	{
	}

	/**
	 * Trim a finished HTML document.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $html  The response body.
	 *
	 * @return string The document, or `$html` unchanged when it has no `</head>`.
	 */
	public function optimize( string $html ): string
	{
		if ( 1 !== preg_match( '/<\/head\s*>/i', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$offset = (int) $match[0][1];
		$head   = substr( $html, 0, $offset );
		$body   = substr( $html, $offset );

		if ( $this->enabled( 'conditional' ) ) {
			$head = $this->removeUnusedStylesheets( $head, $body );
		}

		if ( $this->enabled( 'compiled_file' ) ) {
			$head = $this->moveInlineStylesToFile( $head );
		}

		return $head . $body;
	}

	/**
	 * Whether a step is on. Both default to on.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $step  `conditional` or `compiled_file`.
	 */
	protected function enabled( string $step ): bool
	{
		return false !== filter_var(
			config( 'artisanpack.visual-editor.frontend_styles.' . $step, true ),
			FILTER_VALIDATE_BOOLEAN,
			FILTER_NULL_ON_FAILURE,
		);
	}

	/**
	 * Drop each conditional stylesheet whose markers aren't in the body.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $head  Document up to `</head>`.
	 * @param  string  $body  The rest of the document.
	 */
	protected function removeUnusedStylesheets( string $head, string $body ): string
	{
		/**
		 * Filters the stylesheets that stay on every page, whatever the body
		 * contains. For CSS a page needs before its markup exists, such as a
		 * block loaded later by Livewire.
		 *
		 * @since 1.19.0
		 *
		 * @param array<int, string> $required Tag attributes from {@see self::STYLESHEET_MARKERS}.
		 */
		$required = (array) applyFilters( 'ap.visualEditor.requiredStylesheets', [] );

		foreach ( self::STYLESHEET_MARKERS as $attribute => $markers ) {
			if ( in_array( $attribute, $required, true ) || $this->containsAny( $body, $markers ) ) {
				continue;
			}

			$quoted = preg_quote( $attribute, '/' );

			// `(?=[\s>\/=])` keeps `data-ve-grid` from matching a longer
			// attribute. The flex rules print as a `<style>` when the host
			// moves its breakpoints (#895).
			$head = preg_replace(
				[
					'/<link\b[^>]*\s' . $quoted . '(?=[\s>\/=])[^>]*>\s*/i',
					'/<style\s+' . $quoted . '\s*>.*?<\/style>\s*/is',
				],
				'',
				$head,
			) ?? $head;
		}

		return $head;
	}

	/**
	 * Whether the haystack contains any of the needles.
	 *
	 * @since 1.19.0
	 *
	 * @param  string              $haystack  Text to search.
	 * @param  array<int, string>  $needles   Fragments to look for.
	 */
	protected function containsAny( string $haystack, array $needles ): bool
	{
		foreach ( $needles as $needle ) {
			if ( str_contains( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace each uninterrupted run of compiled `<style>` tags with a link
	 * to one generated file. Only adjacent tags merge: a tag a host printed
	 * after its own stylesheet (`@stack('ve-global-styles')`) keeps its place
	 * in the cascade.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $head  Document up to `</head>`.
	 */
	protected function moveInlineStylesToFile( string $head ): string
	{
		$tag = '<style\s+(?:' . implode( '|', array_map( static fn ( string $attribute ): string => preg_quote( $attribute, '/' ), self::COMPILED_STYLE_TAGS ) ) . ')\s*>(.*?)<\/style>';

		$replaced = preg_replace_callback(
			'/(?:' . $tag . '\s*)+/is',
			function ( array $run ) use ( $tag ): string {
				preg_match_all( '/' . $tag . '/is', $run[0], $tags );

				$url = $this->stylesheets->urlFor( implode( "\n", $tags[1] ) );

				return null === $url
					? $run[0]
					: sprintf( '<link rel="stylesheet" href="%s" data-ve-compiled-styles>' . "\n", e( $url ) );
			},
			$head,
		);

		// PCRE gives up on a pathological document; keep the inline styles.
		return $replaced ?? $head;
	}
}
