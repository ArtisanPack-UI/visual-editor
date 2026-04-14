<?php

/**
 * BlockTree validation rule.
 *
 * Recursively validates that a value is an array of blocks shaped as
 * `{ clientId: string, name: string, attributes: object, innerBlocks: Block[] }`.
 * Enforces `clientId` uniqueness within the tree and bounds on depth and total
 * node count to keep validation cost predictable.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BlockTreeRule implements ValidationRule
{
	/**
	 * Maximum allowed depth of the block tree.
	 */
	public const MAX_DEPTH = 10;

	/**
	 * Maximum allowed total block nodes in the tree.
	 */
	public const MAX_NODES = 1000;

	public function validate( string $attribute, mixed $value, Closure $fail ): void
	{
		if ( ! is_array( $value ) ) {
			$fail( 'The :attribute must be an array of blocks.' );
			return;
		}

		$nodeCount   = 0;
		$seenClients = [];

		$error = $this->validateBlocks( $value, $attribute, 0, $nodeCount, $seenClients );

		if ( null !== $error ) {
			$fail( $error );
		}
	}

	/**
	 * Validates an array of blocks, returning the first error path encountered.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, mixed>   $blocks        The block array to validate.
	 * @param  string              $path          Dot-notated path for error reporting.
	 * @param  int                 $currentDepth  Current recursion depth (0 at the root).
	 * @param  int                 $nodeCount     Running total of nodes visited (by reference).
	 * @param  array<string, bool> $seenClients   Set of clientIds already encountered (by reference).
	 *
	 * @return string|null Error message when invalid, null when valid.
	 */
	protected function validateBlocks(
		array $blocks,
		string $path,
		int $currentDepth,
		int &$nodeCount,
		array &$seenClients
	): ?string {
		if ( $currentDepth >= self::MAX_DEPTH ) {
			return sprintf(
				'The %s exceeds the maximum block tree depth of %d.',
				$path,
				self::MAX_DEPTH
			);
		}

		if ( ! array_is_list( $blocks ) ) {
			return "The {$path} must be a list of blocks.";
		}

		foreach ( $blocks as $index => $block ) {
			$blockPath = "{$path}.{$index}";

			$nodeCount++;

			if ( $nodeCount > self::MAX_NODES ) {
				return sprintf(
					'The block tree exceeds the maximum of %d nodes.',
					self::MAX_NODES
				);
			}

			if ( ! is_array( $block ) ) {
				return "The {$blockPath} must be a block object.";
			}

			if ( ! isset( $block['clientId'] ) || ! is_string( $block['clientId'] ) || '' === $block['clientId'] ) {
				return "The {$blockPath}.clientId is required and must be a string.";
			}

			if ( isset( $seenClients[ $block['clientId'] ] ) ) {
				return "The {$blockPath}.clientId \"{$block['clientId']}\" is duplicated within the block tree.";
			}

			$seenClients[ $block['clientId'] ] = true;

			if ( ! isset( $block['name'] ) || ! is_string( $block['name'] ) || '' === $block['name'] ) {
				return "The {$blockPath}.name is required and must be a string.";
			}

			if ( ! array_key_exists( 'attributes', $block ) || ! is_array( $block['attributes'] ) ) {
				return "The {$blockPath}.attributes is required and must be an object.";
			}

			if ( ! array_key_exists( 'innerBlocks', $block ) || ! is_array( $block['innerBlocks'] ) ) {
				return "The {$blockPath}.innerBlocks is required and must be an array.";
			}

			$childError = $this->validateBlocks(
				$block['innerBlocks'],
				"{$blockPath}.innerBlocks",
				$currentDepth + 1,
				$nodeCount,
				$seenClients
			);

			if ( null !== $childError ) {
				return $childError;
			}
		}

		return null;
	}
}
