<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\ContentResolver;

test( 'content resolver returns empty title by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getTitle() )->toBe( '' );
} );

test( 'content resolver returns empty body by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getBody() )->toBe( '' );
} );

test( 'content resolver returns empty excerpt by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getExcerpt() )->toBe( '' );
} );

test( 'content resolver returns empty date by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getDate() )->toBe( '' );
} );

test( 'content resolver returns empty modified date by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getModifiedDate() )->toBe( '' );
} );

test( 'content resolver returns empty featured image url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getFeaturedImageUrl() )->toBe( '' );
} );

test( 'content resolver returns empty featured image alt by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getFeaturedImageAlt() )->toBe( '' );
} );

test( 'content resolver returns empty permalink by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getPermalink() )->toBe( '' );
} );

test( 'content resolver returns empty author name by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getAuthorName() )->toBe( '' );
} );

test( 'content resolver returns empty author bio by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getAuthorBio() )->toBe( '' );
} );

test( 'content resolver returns empty author avatar url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getAuthorAvatarUrl() )->toBe( '' );
} );

test( 'content resolver returns empty author url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getAuthorUrl() )->toBe( '' );
} );

test( 'content resolver returns empty terms by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getTerms( 'category' ) )->toBe( [] );
} );

test( 'content resolver returns zero comments count by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentsCount() )->toBe( 0 );
} );

test( 'content resolver returns empty comments url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentsUrl() )->toBe( '' );
} );

test( 'content resolver returns zero word count by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getWordCount() )->toBe( 0 );
} );

test( 'content resolver sanitizes unsafe author avatar urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.author-avatar-url', function () {
			return 'javascript:alert(1)';
		} );

		expect( $resolver->getAuthorAvatarUrl() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.author-avatar-url' );
		}
	} else {
		expect( $resolver->getAuthorAvatarUrl() )->toBe( '' );
	}
} );

test( 'content resolver sanitizes unsafe author urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.author-url', function () {
			return 'javascript:alert(1)';
		} );

		expect( $resolver->getAuthorUrl() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.author-url' );
		}
	} else {
		expect( $resolver->getAuthorUrl() )->toBe( '' );
	}
} );

test( 'content resolver sanitizes unsafe comments urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.comments-url', function () {
			return 'javascript:alert(1)';
		} );

		expect( $resolver->getCommentsUrl() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.comments-url' );
		}
	} else {
		expect( $resolver->getCommentsUrl() )->toBe( '' );
	}
} );

test( 'content resolver sanitizes term urls in terms array', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.terms', function () {
			return [
				[ 'name' => 'Test', 'url' => 'javascript:alert(1)', 'slug' => 'test' ],
			];
		} );

		$terms = $resolver->getTerms( 'category' );
		expect( $terms[0]['url'] )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.terms' );
		}
	} else {
		expect( $resolver->getTerms( 'category' ) )->toBe( [] );
	}
} );

test( 'content resolver returns empty previous post url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getPreviousPostUrl() )->toBe( '' );
} );

test( 'content resolver returns empty previous post title by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getPreviousPostTitle() )->toBe( '' );
} );

test( 'content resolver returns empty next post url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getNextPostUrl() )->toBe( '' );
} );

test( 'content resolver returns empty next post title by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getNextPostTitle() )->toBe( '' );
} );

test( 'content resolver applies filter to previous post title', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.previous-post-title', function () {
			return 'Previous Post Title';
		} );

		expect( $resolver->getPreviousPostTitle() )->toBe( 'Previous Post Title' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.previous-post-title' );
		}
	} else {
		expect( $resolver->getPreviousPostTitle() )->toBe( '' );
	}
} );

test( 'content resolver applies filter to next post title', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.next-post-title', function () {
			return 'Next Post Title';
		} );

		expect( $resolver->getNextPostTitle() )->toBe( 'Next Post Title' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.next-post-title' );
		}
	} else {
		expect( $resolver->getNextPostTitle() )->toBe( '' );
	}
} );

test( 'content resolver sanitizes unsafe previous post urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.previous-post-url', function () {
			return 'javascript:alert(1)';
		} );

		expect( $resolver->getPreviousPostUrl() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.previous-post-url' );
		}
	} else {
		expect( $resolver->getPreviousPostUrl() )->toBe( '' );
	}
} );

test( 'content resolver sanitizes unsafe next post urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.next-post-url', function () {
			return 'javascript:alert(1)';
		} );

		expect( $resolver->getNextPostUrl() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.next-post-url' );
		}
	} else {
		expect( $resolver->getNextPostUrl() )->toBe( '' );
	}
} );

test( 'content resolver preserves valid previous post urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.previous-post-url', function () {
			return 'https://example.com/posts/previous-post';
		} );

		expect( $resolver->getPreviousPostUrl() )->toBe( 'https://example.com/posts/previous-post' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.previous-post-url' );
		}
	} else {
		expect( $resolver->getPreviousPostUrl() )->toBe( '' );
	}
} );

test( 'content resolver preserves valid next post urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.next-post-url', function () {
			return 'https://example.com/posts/next-post';
		} );

		expect( $resolver->getNextPostUrl() )->toBe( 'https://example.com/posts/next-post' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.next-post-url' );
		}
	} else {
		expect( $resolver->getNextPostUrl() )->toBe( '' );
	}
} );

test( 'content resolver to array returns all fields', function (): void {
	$resolver = new ContentResolver();
	$array    = $resolver->toArray();

	expect( $array )->toHaveKeys( [
		'title',
		'body',
		'excerpt',
		'date',
		'modifiedDate',
		'featuredImageUrl',
		'featuredImageAlt',
		'permalink',
		'authorName',
		'authorBio',
		'authorAvatarUrl',
		'authorUrl',
		'commentsCount',
		'commentsUrl',
		'wordCount',
		'previousPostUrl',
		'previousPostTitle',
		'nextPostUrl',
		'nextPostTitle',
	] );
} );

test( 'content resolver passes context to filters', function (): void {
	$resolver = new ContentResolver();
	$context  = [ 'model_id' => 42, 'model_type' => 'post' ];

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.title', function ( $default, $ctx ) {
			if ( is_array( $ctx ) && 42 === ( $ctx['model_id'] ?? null ) ) {
				return 'Filtered Title';
			}

			return $default;
		} );

		expect( $resolver->getTitle( $context ) )->toBe( 'Filtered Title' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.title' );
		}
	} else {
		expect( $resolver->getTitle( $context ) )->toBe( '' );
	}
} );

test( 'content resolver sanitizes unsafe permalink urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.permalink', function () {
			return 'javascript:alert(1)';
		} );

		expect( $resolver->getPermalink() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.permalink' );
		}
	} else {
		expect( $resolver->getPermalink() )->toBe( '' );
	}
} );

test( 'content resolver sanitizes unsafe featured image urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.featured-image-url', function () {
			return 'data:text/html,<script>alert(1)</script>';
		} );

		expect( $resolver->getFeaturedImageUrl() )->toBe( '' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.featured-image-url' );
		}
	} else {
		expect( $resolver->getFeaturedImageUrl() )->toBe( '' );
	}
} );

test( 'content resolver preserves valid http permalink urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.permalink', function () {
			return 'http://example.com/posts/1';
		} );

		expect( $resolver->getPermalink() )->toBe( 'http://example.com/posts/1' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.permalink' );
		}
	} else {
		expect( $resolver->getPermalink() )->toBe( '' );
	}
} );

test( 'content resolver returns empty comments array by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getComments() )->toBe( [] );
} );

test( 'content resolver returns empty comment author name by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentAuthorName() )->toBe( '' );
} );

test( 'content resolver returns empty comment author avatar url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentAuthorAvatarUrl() )->toBe( '' );
} );

test( 'content resolver returns empty comment author url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentAuthorUrl() )->toBe( '' );
} );

test( 'content resolver returns empty comment content by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentContent() )->toBe( '' );
} );

test( 'content resolver returns empty comment date by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentDate() )->toBe( '' );
} );

test( 'content resolver returns empty comment reply url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentReplyUrl() )->toBe( '' );
} );

test( 'content resolver returns empty comment edit url by default', function (): void {
	$resolver = new ContentResolver();

	expect( $resolver->getCommentEditUrl() )->toBe( '' );
} );

test( 'content resolver returns default comments pagination by default', function (): void {
	$resolver   = new ContentResolver();
	$pagination = $resolver->getCommentsPagination();

	expect( $pagination['totalPages'] )->toBe( 1 )
		->and( $pagination['currentPage'] )->toBe( 1 )
		->and( $pagination['previousUrl'] )->toBe( '' )
		->and( $pagination['nextUrl'] )->toBe( '' )
		->and( $pagination['perPage'] )->toBe( 20 );
} );

test( 'content resolver to array includes comment fields', function (): void {
	$resolver = new ContentResolver();
	$array    = $resolver->toArray();

	expect( $array )->toHaveKeys( [ 'comments', 'commentsPagination' ] )
		->and( $array['comments'] )->toBe( [] )
		->and( $array['commentsPagination'] )->toBeArray();
} );

test( 'content resolver preserves valid https featured image urls', function (): void {
	$resolver = new ContentResolver();

	if ( function_exists( 'addFilter' ) ) {
		addFilter( 've.content.featured-image-url', function () {
			return 'https://example.com/images/hero.jpg';
		} );

		expect( $resolver->getFeaturedImageUrl() )->toBe( 'https://example.com/images/hero.jpg' );

		if ( function_exists( 'removeAllFilters' ) ) {
			removeAllFilters( 've.content.featured-image-url' );
		}
	} else {
		expect( $resolver->getFeaturedImageUrl() )->toBe( '' );
	}
} );
