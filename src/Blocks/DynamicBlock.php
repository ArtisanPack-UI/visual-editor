<?php

/**
 * Abstract base class for server-rendered (dynamic) blocks.
 *
 * Host applications and packages extend this class when a block's markup is
 * produced on the server at render time — product carousels, latest-posts
 * lists, pricing grids, and so on. The editor calls {@see render()} via the
 * generic preview endpoint; the Blade/React/Vue frontend renderers call the
 * same method when hydrating a saved block tree for public display.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Stringable;

abstract class DynamicBlock
{
	/**
	 * Fully-qualified block name (e.g. `acme/latest-posts`).
	 *
	 * Must match the `name` field of the paired block.json so the client-side
	 * registration and server-side renderer resolve to the same block.
	 *
	 * @since 1.0.0
	 */
	abstract public function name(): string;

	/**
	 * Render the block markup for the given attributes.
	 *
	 * Implementations may return a Blade view, a plain HTML string, or any
	 * {@see Stringable} — the preview endpoint coerces the result to a string
	 * before returning it to the editor.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attrs  Normalized block attributes.
	 *
	 * @return View|Stringable|string
	 */
	abstract public function render( array $attrs );

	/**
	 * Extract searchable text from the block's attributes.
	 *
	 * The default implementation walks all string attribute values and joins
	 * them with a single space. Override per-block when the block persists
	 * IDs, slugs, or other non-display data that should not leak into search.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attrs  Normalized block attributes.
	 */
	public function searchableText( array $attrs ): string
	{
		$collected = [];

		array_walk_recursive( $attrs, static function ( $value ) use ( &$collected ): void {
			if ( is_string( $value ) ) {
				$trimmed = trim( $value );

				if ( '' !== $trimmed ) {
					$collected[] = $trimmed;
				}
			}
		} );

		return implode( ' ', $collected );
	}

	/**
	 * Validate and normalize block attributes before rendering.
	 *
	 * The default is a no-op — the incoming attributes are returned unchanged.
	 * Blocks that require specific shapes (integers, enums, bounded lists)
	 * should override this method and throw {@see \InvalidArgumentException}
	 * when the payload is unacceptable.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attrs  Raw attributes from the request.
	 *
	 * @return array<string, mixed>
	 */
	public function validateAttrs( array $attrs ): array
	{
		return $attrs;
	}

	/**
	 * Gate access to the block preview for the current request's user.
	 *
	 * Returning `false` instructs the preview endpoint to respond with 403.
	 * The default allows every authenticated user the surrounding middleware
	 * stack has already let through.
	 *
	 * @since 1.0.0
	 *
	 * @param  Authenticatable|null  $user   Currently authenticated user, if any.
	 * @param  array<string, mixed>  $attrs  Validated block attributes.
	 */
	public function authorize( ?Authenticatable $user, array $attrs ): bool
	{
		return true;
	}
}
