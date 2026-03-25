<?php

/**
 * Content Resolver Service.
 *
 * Resolves content fields (title, content, excerpt, date, featured image)
 * from the current content context. Supports query loop context and
 * current page/route context via filter hooks for developer customization.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

/**
 * Service for resolving content fields from context and filter hooks.
 *
 * Provides a unified interface for accessing content title, body, excerpt,
 * dates, featured image, and permalink. Each value follows a resolution
 * cascade:
 *   1. Query loop context (when inside a query loop block)
 *   2. Current page/route context
 *   3. Filter hook override (`ve.content.*`)
 *
 * Applications register filter callbacks to provide data from their own
 * models (Post, Page, Article, etc.) since the visual editor is
 * model-agnostic.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      2.0.0
 */
class ContentResolver
{
	/**
	 * Allowed URL schemes for content URLs.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, string>
	 */
	private const SAFE_SCHEMES = [ '', 'http', 'https' ];

	/**
	 * Get the content title.
	 *
	 * Resolution order:
	 *   1. Query loop context via `ve.content.title` filter
	 *   2. Current page/route context via `ve.content.title` filter
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getTitle( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.title', '', $context );
	}

	/**
	 * Get the content body (full block content).
	 *
	 * Resolution order:
	 *   1. Query loop context via `ve.content.body` filter
	 *   2. Current page/route context via `ve.content.body` filter
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getBody( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.body', '', $context );
	}

	/**
	 * Get the content excerpt.
	 *
	 * Returns a manual excerpt if available, otherwise generates one
	 * from the content body. The application provides the excerpt
	 * via filter hooks.
	 *
	 * Resolution order:
	 *   1. Query loop context via `ve.content.excerpt` filter
	 *   2. Current page/route context via `ve.content.excerpt` filter
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getExcerpt( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.excerpt', '', $context );
	}

	/**
	 * Get the content publish date.
	 *
	 * Returns the publish date as a Carbon instance string or empty string.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string The date as an ISO 8601 string, or empty string.
	 */
	public function getDate( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.date', '', $context );
	}

	/**
	 * Get the content last-modified date.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string The date as an ISO 8601 string, or empty string.
	 */
	public function getModifiedDate( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.modified-date', '', $context );
	}

	/**
	 * Get the content featured image URL.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string The image URL, or empty string if none.
	 */
	public function getFeaturedImageUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.featured-image-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the alt text for the content featured image.
	 *
	 * Falls back to the content title for accessibility.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getFeaturedImageAlt( array $context = [] ): string
	{
		$alt = (string) veApplyFilters( 've.content.featured-image-alt', '', $context );

		if ( '' === $alt ) {
			$alt = $this->getTitle( $context );
		}

		return $alt;
	}

	/**
	 * Get the content permalink.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string The URL, or empty string if none.
	 */
	public function getPermalink( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.permalink', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the content author name.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getAuthorName( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.author-name', '', $context );
	}

	/**
	 * Get the content author biography.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getAuthorBio( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.author-bio', '', $context );
	}

	/**
	 * Get the content author avatar URL.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getAuthorAvatarUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.author-avatar-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the content author archive/profile URL.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getAuthorUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.author-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the content taxonomy terms.
	 *
	 * Returns an array of term objects with name, url, and slug keys.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $taxonomy The taxonomy slug (e.g. 'category', 'tag').
	 * @param array<string, mixed> $context  Optional context (e.g. from query loop).
	 *
	 * @return array<int, array{name: string, url: string, slug: string}>
	 */
	public function getTerms( string $taxonomy, array $context = [] ): array
	{
		$terms = veApplyFilters( 've.content.terms', [], $context, $taxonomy );

		if ( ! is_array( $terms ) ) {
			return [];
		}

		return array_map( function ( $term ) {
			return [
				'name' => (string) ( $term['name'] ?? '' ),
				'url'  => $this->sanitizeUrl( (string) ( $term['url'] ?? '' ) ),
				'slug' => (string) ( $term['slug'] ?? '' ),
			];
		}, $terms );
	}

	/**
	 * Get the content comments count.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return int
	 */
	public function getCommentsCount( array $context = [] ): int
	{
		return (int) veApplyFilters( 've.content.comments-count', 0, $context );
	}

	/**
	 * Get the URL to the content comments section.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return string
	 */
	public function getCommentsUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.comments-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the content word count.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return int
	 */
	public function getWordCount( array $context = [] ): int
	{
		return (int) veApplyFilters( 've.content.word-count', 0, $context );
	}

	/**
	 * Get the previous post URL for navigation.
	 *
	 * Returns the URL of the previous content item based on publish date.
	 * Applications provide the URL via the `ve.content.previous-post-url` filter.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. taxonomy scope).
	 *
	 * @return string The URL, or empty string if none.
	 */
	public function getPreviousPostUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.previous-post-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the previous post title for navigation.
	 *
	 * Returns the title of the previous content item based on publish date.
	 * Applications provide the title via the `ve.content.previous-post-title` filter.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. taxonomy scope).
	 *
	 * @return string
	 */
	public function getPreviousPostTitle( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.previous-post-title', '', $context );
	}

	/**
	 * Get the next post URL for navigation.
	 *
	 * Returns the URL of the next content item based on publish date.
	 * Applications provide the URL via the `ve.content.next-post-url` filter.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. taxonomy scope).
	 *
	 * @return string The URL, or empty string if none.
	 */
	public function getNextPostUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.next-post-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the next post title for navigation.
	 *
	 * Returns the title of the next content item based on publish date.
	 * Applications provide the title via the `ve.content.next-post-title` filter.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. taxonomy scope).
	 *
	 * @return string
	 */
	public function getNextPostTitle( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.next-post-title', '', $context );
	}

	/**
	 * Get all content fields as an array.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return array{title: string, body: string, excerpt: string, date: string, modifiedDate: string, featuredImageUrl: string, featuredImageAlt: string, permalink: string, authorName: string, authorBio: string, authorAvatarUrl: string, authorUrl: string, commentsCount: int, commentsUrl: string, wordCount: int}
	 */
	public function toArray( array $context = [] ): array
	{
		return [
			'title'             => $this->getTitle( $context ),
			'body'              => $this->getBody( $context ),
			'excerpt'           => $this->getExcerpt( $context ),
			'date'              => $this->getDate( $context ),
			'modifiedDate'      => $this->getModifiedDate( $context ),
			'featuredImageUrl'  => $this->getFeaturedImageUrl( $context ),
			'featuredImageAlt'  => $this->getFeaturedImageAlt( $context ),
			'permalink'         => $this->getPermalink( $context ),
			'authorName'        => $this->getAuthorName( $context ),
			'authorBio'         => $this->getAuthorBio( $context ),
			'authorAvatarUrl'   => $this->getAuthorAvatarUrl( $context ),
			'authorUrl'         => $this->getAuthorUrl( $context ),
			'commentsCount'     => $this->getCommentsCount( $context ),
			'commentsUrl'       => $this->getCommentsUrl( $context ),
			'wordCount'         => $this->getWordCount( $context ),
			'previousPostUrl'   => $this->getPreviousPostUrl( $context ),
			'previousPostTitle' => $this->getPreviousPostTitle( $context ),
			'nextPostUrl'       => $this->getNextPostUrl( $context ),
			'nextPostTitle'     => $this->getNextPostTitle( $context ),
		];
	}

	/**
	 * Validate that a URL uses a safe scheme.
	 *
	 * Returns the URL unchanged if the scheme is safe (http, https,
	 * or relative). Returns empty string for unsafe schemes like
	 * javascript: or data:.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url The URL to validate.
	 *
	 * @return string
	 */
	private function sanitizeUrl( string $url ): string
	{
		if ( '' === $url ) {
			return '';
		}

		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		if ( in_array( $scheme, self::SAFE_SCHEMES, true ) ) {
			return $url;
		}

		return '';
	}
}
