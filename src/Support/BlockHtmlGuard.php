<?php

/**
 * Save-time HTML filter for block trees.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

use Illuminate\Support\Facades\Gate;

/**
 * Enforces {@see UnfilteredHtmlAccess} on incoming block trees (#1062).
 *
 * A user with the ability saves markup as sent. For anyone else, every
 * string in a block that holds markup goes through
 * {@see BlockHtmlSanitizer}, at any depth and in any key: the renderers
 * read `attributes`, `attrs`, `innerHTML` and `innerContent`, and a
 * third-party partial can print any attribute raw.
 *
 * A string already stored on the record, outside the skipped keys, is kept
 * as it is. So an author without the ability can move or keep a Custom HTML
 * block an administrator wrote, but can't add or change markup, and content
 * saved before this filter existed only changes when someone edits it.
 *
 * Runs from the same places as
 * {@see \ArtisanPackUI\VisualEditor\Effects\CustomCssGuard}, and bound as a
 * scoped instance so vouched-for values never outlive the request.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class BlockHtmlGuard
{
	/**
	 * Keys left alone. Custom CSS has its own guard, and a selector can
	 * hold what looks like a tag. Image alt text and a Query Loop's search
	 * are plain text that every renderer escapes, and `List<String>` in
	 * one of them isn't markup.
	 *
	 * @since 1.20.0
	 *
	 * @var list<string>
	 */
	protected const SKIPPED_KEYS = [ 'alt', 'customCss', 'search' ];

	/**
	 * Block attributes every renderer prints escaped, by block name, left
	 * alone like {@see self::SKIPPED_KEYS} so `Use <Enter>` keeps its text.
	 * Only a block's own attribute counts: the same key elsewhere, such as
	 * a binding's `placeholder`, can end up printed raw.
	 *
	 * @since 1.20.0
	 *
	 * @var array<string, list<string>>
	 */
	protected const PLAIN_TEXT_ATTRIBUTES = [
		'artisanpack/search'       => [ 'placeholder' ],
		'artisanpack/search-field' => [ 'placeholder' ],
		'core/search'              => [ 'placeholder' ],
	];

	/**
	 * Values vouched for earlier in the request.
	 *
	 * @since 1.20.0
	 *
	 * @var array<string, bool>
	 */
	protected array $approved = [];

	public function __construct( protected BlockHtmlSanitizer $sanitizer )
	{
	}

	/**
	 * Filter an incoming block tree for the current user, and vouch for
	 * the markup in `$stored` so the model-level guard keeps it too, even
	 * on a new row seeded from a theme file.
	 *
	 * @since 1.20.0
	 *
	 * @param  mixed  $blocks  Incoming block array (anything else passes through).
	 * @param  mixed  $stored  The record's stored block array (or the theme file's
	 *                         blocks for a file→DB upsert), or null for a new record.
	 */
	public function filter( mixed $blocks, mixed $stored = null ): mixed
	{
		if ( is_array( $stored ) && ! Gate::allows( UnfilteredHtmlAccess::ABILITY ) ) {
			$this->collect( $stored, $this->approved );
		}

		return $this->filterAgainst( $blocks, null );
	}

	/**
	 * Filter an incoming block tree against a stored tree, plus whatever
	 * {@see self::filter()} has vouched for.
	 *
	 * @since 1.20.0
	 *
	 * @param  mixed  $blocks  Incoming block array (anything else passes through).
	 * @param  mixed  $stored  The record's stored block array, or null for a new record.
	 */
	public function filterAgainst( mixed $blocks, mixed $stored ): mixed
	{
		if ( ! is_array( $blocks ) || Gate::allows( UnfilteredHtmlAccess::ABILITY ) ) {
			return $blocks;
		}

		$allowed = $this->approved;

		if ( is_array( $stored ) ) {
			$this->collect( $stored, $allowed );
		}

		return $this->clean( $blocks, $allowed );
	}

	/**
	 * Collect every string holding markup from a stored tree, outside the
	 * skipped keys.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<mixed>  $value
	 * @param  array<string, bool>  $allowed
	 * @param  list<string>  $plainText  Keys of `$value` left alone, when it's a
	 *                                   block's attributes.
	 */
	protected function collect( array $value, array &$allowed, array $plainText = [] ): void
	{
		$plainTextAttributes = $this->plainTextAttributes( $value );

		foreach ( $value as $key => $item ) {
			// A skipped key is never filtered, so its value can't vouch for
			// the same string under another key.
			if ( $this->isSkipped( $key, $plainText ) ) {
				continue;
			}

			if ( is_array( $item ) ) {
				$this->collect( $item, $allowed, $this->isAttributeBag( $key ) ? $plainTextAttributes : [] );
			} elseif ( is_string( $item ) && BlockHtmlSanitizer::hasMarkup( $item ) ) {
				$allowed[ $item ] = true;
			}
		}
	}

	/**
	 * Sanitize every string in a tree that isn't in `$allowed`.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<mixed>  $value
	 * @param  array<string, bool>  $allowed
	 * @param  list<string>  $plainText  Keys of `$value` left alone, when it's a
	 *                                   block's attributes.
	 *
	 * @return array<mixed>
	 */
	protected function clean( array $value, array $allowed, array $plainText = [] ): array
	{
		$plainTextAttributes = $this->plainTextAttributes( $value );

		foreach ( $value as $key => $item ) {
			if ( $this->isSkipped( $key, $plainText ) ) {
				continue;
			}

			if ( is_array( $item ) ) {
				$value[ $key ] = $this->clean( $item, $allowed, $this->isAttributeBag( $key ) ? $plainTextAttributes : [] );
			} elseif ( is_string( $item ) && ! isset( $allowed[ $item ] ) ) {
				$value[ $key ] = $this->sanitizer->sanitize( $item );
			}
		}

		return $value;
	}

	/**
	 * Whether a key is left alone.
	 *
	 * @since 1.20.0
	 *
	 * @param  int|string    $key        The key.
	 * @param  list<string>  $plainText  The block's plain-text attributes, when
	 *                                   the key is one of its attributes.
	 */
	protected function isSkipped( int|string $key, array $plainText ): bool
	{
		return in_array( $key, self::SKIPPED_KEYS, true ) || in_array( $key, $plainText, true );
	}

	/**
	 * Whether a key holds a block's attributes, in either tree shape.
	 *
	 * @since 1.20.0
	 *
	 * @param  int|string  $key  The key.
	 */
	protected function isAttributeBag( int|string $key ): bool
	{
		return 'attributes' === $key || 'attrs' === $key;
	}

	/**
	 * The plain-text attributes of a block, or none when `$value` isn't one.
	 *
	 * @since 1.20.0
	 *
	 * @param  array<mixed>  $value  A node of the tree.
	 *
	 * @return list<string>
	 */
	protected function plainTextAttributes( array $value ): array
	{
		$name = $value['name'] ?? $value['blockName'] ?? null;

		return is_string( $name ) ? ( self::PLAIN_TEXT_ATTRIBUTES[ $name ] ?? [] ) : [];
	}
}
