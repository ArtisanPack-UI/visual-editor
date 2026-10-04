<?php

/**
 * Size limits for block trees sent to AI agents.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.12.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Ai\Support;

use ArtisanPackUI\Ai\Exceptions\FeatureError;

/**
 * Bounds the block trees and text the AI features forward to the
 * model (#828).
 *
 * The HTTP form requests and the agents both run the same checks, so
 * the `ai/*` endpoints and the `AiTools` Livewire listeners reject the
 * same oversized payloads. Limits come from
 * `artisanpack.visual-editor.ai.payload_limits`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.12.1
 */
class BlockPayloadLimiter
{
	/**
	 * Fallback limits used when the config key is missing or invalid.
	 *
	 * @since 1.12.1
	 *
	 * @var array{ max_blocks: int, max_depth: int, max_bytes: int, max_text_chars: int }
	 */
	public const DEFAULTS = [
		'max_blocks'     => 1000,
		'max_depth'      => 12,
		'max_bytes'      => 131072,
		'max_text_chars' => 20000,
	];

	/**
	 * Resolve the active limits. Non-positive or non-numeric config
	 * values fall back to {@see self::DEFAULTS}.
	 *
	 * @since 1.12.1
	 *
	 * @return array{ max_blocks: int, max_depth: int, max_bytes: int, max_text_chars: int }
	 */
	public static function limits(): array
	{
		$configured = config( 'artisanpack.visual-editor.ai.payload_limits', [] );
		$configured = is_array( $configured ) ? $configured : [];
		$limits     = self::DEFAULTS;

		foreach ( self::DEFAULTS as $key => $default ) {
			$value = $configured[ $key ] ?? null;
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$limits[ $key ] = (int) $value;
			}
		}

		return $limits;
	}

	/**
	 * Return a message describing why the tree is over the block-count
	 * or nesting-depth limit, or `null` when it is within both.
	 *
	 * @since 1.12.1
	 *
	 * @param  array<int|string, mixed>  $blocks  Block tree.
	 *
	 * @return string|null
	 */
	public static function treeViolation( array $blocks ): ?string
	{
		$limits = self::limits();
		$count  = 0;
		$depth  = self::measure( $blocks, 1, $limits, $count );

		if ( $depth > $limits['max_depth'] ) {
			return __( 'Blocks are nested more than :max levels deep.', [ 'max' => $limits['max_depth'] ] );
		}

		if ( $count > $limits['max_blocks'] ) {
			return __( 'The document has more than :max blocks.', [ 'max' => $limits['max_blocks'] ] );
		}

		return null;
	}

	/**
	 * Throw when the tree is over the block-count or nesting-depth limit.
	 *
	 * @since 1.12.1
	 *
	 * @param  array<int|string, mixed>  $blocks      Block tree.
	 * @param  string                    $featureKey  Feature key for the error.
	 *
	 * @throws FeatureError When a limit is exceeded.
	 *
	 * @return void
	 */
	public static function assertTreeWithinLimits( array $blocks, string $featureKey ): void
	{
		$violation = self::treeViolation( $blocks );

		if ( null !== $violation ) {
			throw FeatureError::forFeature( $featureKey, $violation );
		}
	}

	/**
	 * Throw when the serialized payload is larger than `max_bytes`.
	 *
	 * @since 1.12.1
	 *
	 * @param  string  $serialized  JSON about to be sent to the model.
	 * @param  string  $featureKey  Feature key for the error.
	 *
	 * @throws FeatureError When the payload is too large.
	 *
	 * @return void
	 */
	public static function assertSerializedWithinLimit( string $serialized, string $featureKey ): void
	{
		$max = self::limits()['max_bytes'];

		if ( strlen( $serialized ) > $max ) {
			throw FeatureError::forFeature(
				$featureKey,
				__( 'The document is too large to check (over :max bytes).', [ 'max' => $max ] ),
			);
		}
	}

	/**
	 * Throw when text sent to the model is longer than `max_text_chars`.
	 *
	 * @since 1.12.1
	 *
	 * @param  string  $text        Text about to be sent to the model.
	 * @param  string  $featureKey  Feature key for the error.
	 *
	 * @throws FeatureError When the text is too long.
	 *
	 * @return void
	 */
	public static function assertTextWithinLimit( string $text, string $featureKey ): void
	{
		$max = self::limits()['max_text_chars'];

		if ( mb_strlen( $text ) > $max ) {
			throw FeatureError::forFeature(
				$featureKey,
				__( 'The text is too long (over :max characters).', [ 'max' => $max ] ),
			);
		}
	}

	/**
	 * Laravel validation closure for a block-tree request field.
	 *
	 * @since 1.12.1
	 *
	 * @return callable(string, mixed, callable): void
	 */
	public static function rule(): callable
	{
		return static function ( string $attribute, mixed $value, callable $fail ): void {
			if ( ! is_array( $value ) ) {
				return;
			}

			$violation = self::treeViolation( $value );

			if ( null !== $violation ) {
				$fail( $violation );
			}
		};
	}

	/**
	 * Count blocks and return the deepest level reached. Stops walking
	 * once either limit is passed, so a hostile payload can't force a
	 * full traversal.
	 *
	 * @since 1.12.1
	 *
	 * @param  array<int|string, mixed>                                   $blocks  Blocks at this level.
	 * @param  int                                                        $depth   Current level (top level is 1).
	 * @param  array{ max_blocks: int, max_depth: int, max_bytes: int, max_text_chars: int }  $limits  Active limits.
	 * @param  int                                                        $count   Running block count.
	 *
	 * @return int
	 */
	protected static function measure( array $blocks, int $depth, array $limits, int &$count ): int
	{
		if ( [] === $blocks ) {
			return $depth - 1;
		}

		$deepest = $depth;

		foreach ( $blocks as $block ) {
			$count++;

			if ( $count > $limits['max_blocks'] || $depth > $limits['max_depth'] ) {
				return $deepest;
			}

			if ( is_array( $block ) && is_array( $block['innerBlocks'] ?? null ) && [] !== $block['innerBlocks'] ) {
				$deepest = max( $deepest, self::measure( $block['innerBlocks'], $depth + 1, $limits, $count ) );
			}
		}

		return $deepest;
	}
}
