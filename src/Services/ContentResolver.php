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
	 * Get the list of comments for the current content.
	 *
	 * Returns an array of comment data arrays, each containing at minimum
	 * id, author_name, author_avatar_url, author_url, content, date,
	 * reply_url, edit_url, and children (nested replies).
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getComments( array $context = [] ): array
	{
		$comments = veApplyFilters( 've.content.comments', [], $context );

		if ( ! is_array( $comments ) ) {
			return [];
		}

		return $comments;
	}

	/**
	 * Get the comment author name.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	public function getCommentAuthorName( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.comment-author-name', '', $context );
	}

	/**
	 * Get the comment author avatar URL.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	public function getCommentAuthorAvatarUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.comment-author-avatar-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the comment author URL.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	public function getCommentAuthorUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.comment-author-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the comment content/body text.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	public function getCommentContent( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.comment-content', '', $context );
	}

	/**
	 * Get the comment date.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string The date as an ISO 8601 string, or empty string.
	 */
	public function getCommentDate( array $context = [] ): string
	{
		return (string) veApplyFilters( 've.content.comment-date', '', $context );
	}

	/**
	 * Get the comment reply URL.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	public function getCommentReplyUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.comment-reply-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get the comment edit URL.
	 *
	 * Returns empty string if the current user cannot edit the comment.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Comment context with comment data.
	 *
	 * @return string
	 */
	public function getCommentEditUrl( array $context = [] ): string
	{
		$url = (string) veApplyFilters( 've.content.comment-edit-url', '', $context );

		return $this->sanitizeUrl( $url );
	}

	/**
	 * Get comments pagination data.
	 *
	 * Returns pagination metadata: total pages, current page,
	 * previous URL, next URL, and per-page count.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context.
	 *
	 * @return array{totalPages: int, currentPage: int, previousUrl: string, nextUrl: string, perPage: int}
	 */
	public function getCommentsPagination( array $context = [] ): array
	{
		$pagination = veApplyFilters( 've.content.comments-pagination', [
			'totalPages'  => 1,
			'currentPage' => 1,
			'previousUrl' => '',
			'nextUrl'     => '',
			'perPage'     => 20,
		], $context );

		if ( ! is_array( $pagination ) ) {
			return [
				'totalPages'  => 1,
				'currentPage' => 1,
				'previousUrl' => '',
				'nextUrl'     => '',
				'perPage'     => 20,
			];
		}

		$totalPages  = max( 1, (int) ( $pagination['totalPages'] ?? 1 ) );
		$perPage     = max( 1, (int) ( $pagination['perPage'] ?? 20 ) );
		$currentPage = min( $totalPages, max( 1, (int) ( $pagination['currentPage'] ?? 1 ) ) );

		return [
			'totalPages'  => $totalPages,
			'currentPage' => $currentPage,
			'previousUrl' => $this->sanitizeUrl( (string) ( $pagination['previousUrl'] ?? '' ) ),
			'nextUrl'     => $this->sanitizeUrl( (string) ( $pagination['nextUrl'] ?? '' ) ),
			'perPage'     => $perPage,
		];
	}

	/**
	 * Get all content fields as an array.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context (e.g. from query loop).
	 *
	 * @return array{title: string, body: string, excerpt: string, date: string, modifiedDate: string, featuredImageUrl: string, featuredImageAlt: string, permalink: string, authorName: string, authorBio: string, authorAvatarUrl: string, authorUrl: string, commentsCount: int, commentsUrl: string, wordCount: int, previousPostUrl: string, previousPostTitle: string, nextPostUrl: string, nextPostTitle: string, comments: array<int, array<string, mixed>>, commentsPagination: array{totalPages: int, currentPage: int, previousUrl: string, nextUrl: string, perPage: int}}
	 */
	public function toArray( array $context = [] ): array
	{
		return [
			'title'              => $this->getTitle( $context ),
			'body'               => $this->getBody( $context ),
			'excerpt'            => $this->getExcerpt( $context ),
			'date'               => $this->getDate( $context ),
			'modifiedDate'       => $this->getModifiedDate( $context ),
			'featuredImageUrl'   => $this->getFeaturedImageUrl( $context ),
			'featuredImageAlt'   => $this->getFeaturedImageAlt( $context ),
			'permalink'          => $this->getPermalink( $context ),
			'authorName'         => $this->getAuthorName( $context ),
			'authorBio'          => $this->getAuthorBio( $context ),
			'authorAvatarUrl'    => $this->getAuthorAvatarUrl( $context ),
			'authorUrl'          => $this->getAuthorUrl( $context ),
			'commentsCount'      => $this->getCommentsCount( $context ),
			'commentsUrl'        => $this->getCommentsUrl( $context ),
			'wordCount'          => $this->getWordCount( $context ),
			'previousPostUrl'    => $this->getPreviousPostUrl( $context ),
			'previousPostTitle'  => $this->getPreviousPostTitle( $context ),
			'nextPostUrl'        => $this->getNextPostUrl( $context ),
			'nextPostTitle'      => $this->getNextPostTitle( $context ),
			'comments'           => $this->getComments( $context ),
			'commentsPagination' => $this->getCommentsPagination( $context ),
		];
	}

	/**
	 * Get query results based on the provided query parameters.
	 *
	 * Resolves via `ve.query.results` filter. Applications register
	 * filter callbacks to execute the query against their models and
	 * return a standardised result array.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context      Optional context (e.g. page context for inherit).
	 * @param array<string, mixed> $queryParams  Query parameters (queryType, perPage, orderBy, etc.).
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function getQueryResults( array $context = [], array $queryParams = [] ): array
	{
		$defaults = [
			'items' => [],
			'total' => 0,
		];

		$result = veApplyFilters( 've.query.results', $defaults, $context, $queryParams );

		if ( ! is_array( $result ) || ! isset( $result['items'], $result['total'] ) ) {
			return $defaults;
		}

		return $result;
	}

	/**
	 * Get query pagination data.
	 *
	 * Resolves via `ve.query.pagination` filter. Returns pagination
	 * metadata for the current query loop context.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context.
	 *
	 * @return array{totalPages: int, currentPage: int, previousUrl: string, nextUrl: string}
	 */
	public function getQueryPagination( array $context = [] ): array
	{
		$defaults = [
			'totalPages'  => 1,
			'currentPage' => 1,
			'previousUrl' => '',
			'nextUrl'     => '',
		];

		$result = veApplyFilters( 've.query.pagination', $defaults, $context );

		if ( ! is_array( $result ) ) {
			return $defaults;
		}

		return [
			'totalPages'  => (int) ( $result['totalPages'] ?? 1 ),
			'currentPage' => (int) ( $result['currentPage'] ?? 1 ),
			'previousUrl' => $this->sanitizeUrl( (string) ( $result['previousUrl'] ?? '' ) ),
			'nextUrl'     => $this->sanitizeUrl( (string) ( $result['nextUrl'] ?? '' ) ),
		];
	}

	/**
	 * Get the contextual query title.
	 *
	 * Resolves via `ve.query.title` filter. Returns a title string
	 * appropriate for the current query context (e.g. "Search results
	 * for: X", "Category: Technology").
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context    Optional context.
	 * @param string               $prefixType The prefix type (archive, search).
	 * @param bool                 $showPrefix Whether to include the prefix.
	 *
	 * @return string
	 */
	public function getQueryTitle( array $context = [], string $prefixType = 'archive', bool $showPrefix = true ): string
	{
		return (string) veApplyFilters( 've.query.title', '', $context, $prefixType, $showPrefix );
	}

	/**
	 * Get the total number of query results.
	 *
	 * Resolves via `ve.query.total` filter. Returns the count of
	 * results for the current query context.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $context Optional context.
	 *
	 * @return int
	 */
	public function getQueryTotal( array $context = [] ): int
	{
		return (int) veApplyFilters( 've.query.total', 0, $context );
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
