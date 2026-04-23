<?php

/**
 * TemplateBlockTree validation rule.
 *
 * Validates the parsed block tree stored on a `wp_template` record. The
 * shape matches the post-editor's block tree — `{ name, attributes,
 * innerBlocks }` — but without the editor-only `clientId` requirement,
 * because theme-shipped templates are authored as flat JSON fixtures
 * that don't carry ephemeral editor ids.
 *
 * Shares the depth / node bounds with {@see BlockTreeRule} so any tree
 * that round-trips through the template endpoint can also round-trip
 * through the post endpoint once a `clientId` is assigned.
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

class TemplateBlockTreeRule implements ValidationRule
{
	public const MAX_DEPTH = BlockTreeRule::MAX_DEPTH;

	public const MAX_NODES = BlockTreeRule::MAX_NODES;

	public function validate( string $attribute, mixed $value, Closure $fail ): void
	{
		if ( ! is_array( $value ) ) {
			$fail( 'The :attribute must be an array of blocks.' );
			return;
		}

		$nodeCount = 0;

		$error = $this->validateBlocks( $value, $attribute, 0, $nodeCount );

		if ( null !== $error ) {
			$fail( $error );
		}
	}

	/**
	 * Recursively validates the block tree, returning the first error
	 * path encountered or null when the tree is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, mixed>  $blocks
	 */
	protected function validateBlocks(
		array $blocks,
		string $path,
		int $currentDepth,
		int &$nodeCount
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
				$nodeCount
			);

			if ( null !== $childError ) {
				return $childError;
			}
		}

		return null;
	}
}
