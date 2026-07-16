<?php

/**
 * Editor-side Preview mode inputs, translated into a real
 * {@see VisibilityContext} by {@see VisibilityEvaluator::previewFrom()}.
 *
 * The Site Editor's Visibility Preview toggle serializes a snapshot of
 * these values into a query parameter (`_veVisPreview`) or a request
 * header so a live server render can evaluate the same rules the editor
 * would have. Any field left `null` here means "use the real request
 * value" — the preview only replaces the inputs the editor actively
 * mocked.
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

class PreviewContext
{
	/**
	 * @param  array<string, string>|null  $queryString
	 * @param  array<int, string>|null     $roles
	 */
	public function __construct(
		public readonly ?array $queryString = null,
		public readonly ?string $referrer = null,
		public readonly ?string $userAgent = null,
		public readonly ?bool $isAuthenticated = null,
		public readonly ?int $userId = null,
		public readonly ?string $userEmail = null,
		public readonly ?array $roles = null,
		public readonly ?CarbonImmutable $now = null,
	) {
	}

	/**
	 * Build a preview context from a decoded JSON payload. Unknown keys
	 * are ignored; malformed values reset to `null` so the real request
	 * value shows through.
	 *
	 * @param  array<string, mixed>  $payload
	 *
	 * @since 1.4.0
	 */
	public static function fromPayload( array $payload ): self
	{
		$queryString = null;
		if ( isset( $payload['queryString'] ) && is_array( $payload['queryString'] ) ) {
			$queryString = [];
			foreach ( $payload['queryString'] as $key => $value ) {
				if ( is_string( $key ) && ( is_string( $value ) || is_numeric( $value ) ) ) {
					$queryString[ $key ] = (string) $value;
				}
			}
		}

		$roles = null;
		if ( isset( $payload['roles'] ) && is_array( $payload['roles'] ) ) {
			$roles = [];
			foreach ( $payload['roles'] as $role ) {
				if ( is_string( $role ) && '' !== $role ) {
					$roles[] = $role;
				}
			}
		}

		$now = null;
		if ( isset( $payload['now'] ) && is_string( $payload['now'] ) && '' !== $payload['now'] ) {
			try {
				$now = CarbonImmutable::parse( $payload['now'] );
			} catch ( \Throwable $e ) {
				$now = null;
			}
		}

		return new self(
			queryString:     $queryString,
			referrer:        isset( $payload['referrer'] ) && is_string( $payload['referrer'] ) ? $payload['referrer'] : null,
			userAgent:       isset( $payload['userAgent'] ) && is_string( $payload['userAgent'] ) ? $payload['userAgent'] : null,
			isAuthenticated: array_key_exists( 'isAuthenticated', $payload ) ? (bool) $payload['isAuthenticated'] : null,
			userId:          isset( $payload['userId'] ) && is_numeric( $payload['userId'] ) ? (int) $payload['userId'] : null,
			userEmail:       isset( $payload['userEmail'] ) && is_string( $payload['userEmail'] ) ? $payload['userEmail'] : null,
			roles:           $roles,
			now:             $now,
		);
	}

	/**
	 * Convenience factory for tests.
	 *
	 * @since 1.4.0
	 */
	public static function withMockedNow( DateTimeInterface $now ): self
	{
		return new self( now: CarbonImmutable::instance( $now ) );
	}
}
