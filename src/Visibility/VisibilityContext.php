<?php

/**
 * Runtime context evaluated against every block's visibility rules.
 *
 * Built once per request in {@see \ArtisanPackUI\VisualEditor\Visibility\VisibilityEvaluator}
 * and reused for the full block-tree walk so a single request never
 * pays for repeated user-agent parsing / role lookups. Preview mode
 * substitutes a {@see PreviewContext} for the real request context so
 * editors can mock every input the panels expose (viewport, query
 * strings, referrer, user agent, auth state, mock "now").
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class VisibilityContext
{
	/**
	 * @param  array<string, string>  $queryString  Flattened query-string pairs.
	 * @param  array<int, string>     $roles        Authenticated user's role slugs.
	 */
	public function __construct(
		public readonly array $queryString = [],
		public readonly string $referrer = '',
		public readonly string $userAgent = '',
		public readonly bool $isAuthenticated = false,
		public readonly ?int $userId = null,
		public readonly ?string $userEmail = null,
		public readonly array $roles = [],
		public readonly ?CarbonImmutable $now = null,
		public readonly bool $isPreview = false,
	) {
	}

	/**
	 * Return `now()` in the given timezone, defaulting to the request-scoped
	 * `now`. Preview mode passes a fixed `now` so schedule rules evaluate
	 * against the editor's mock time instead of the real clock.
	 *
	 * @since 1.4.0
	 */
	public function nowIn( string $timezone ): CarbonImmutable
	{
		$now = $this->now ?? CarbonImmutable::now();

		return $now->setTimezone( $timezone );
	}

	/**
	 * Fluent copy-with for tests + the preview pipeline. Rebuilds the
	 * DTO with a subset of fields overridden.
	 *
	 * @param  array<string, mixed>  $overrides
	 *
	 * @since 1.4.0
	 */
	public function with( array $overrides ): self
	{
		return new self(
			queryString:     $overrides['queryString']     ?? $this->queryString,
			referrer:        $overrides['referrer']        ?? $this->referrer,
			userAgent:       $overrides['userAgent']       ?? $this->userAgent,
			isAuthenticated: $overrides['isAuthenticated'] ?? $this->isAuthenticated,
			userId:          $overrides['userId']          ?? $this->userId,
			userEmail:       $overrides['userEmail']       ?? $this->userEmail,
			roles:           $overrides['roles']           ?? $this->roles,
			now:             $overrides['now']             ?? $this->now,
			isPreview:       $overrides['isPreview']       ?? $this->isPreview,
		);
	}

	/**
	 * Convenience factory that normalizes a `DateTimeInterface` into the
	 * `CarbonImmutable` the rules expect.
	 *
	 * @since 1.4.0
	 */
	public static function withMockedNow( DateTimeInterface $now ): CarbonImmutable
	{
		return CarbonImmutable::instance( $now );
	}
}
