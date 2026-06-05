<?php

/**
 * Closure-form dynamic block.
 *
 * Internal adapter that lets packages/apps register a server-rendered block
 * without defining a dedicated class — they pass the block name and an array
 * of callbacks to
 * {@see \ArtisanPackUI\VisualEditor\VisualEditor::registerDynamicBlock()},
 * and the registry wraps those callbacks in this class.
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

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

class ClosureDynamicBlock extends DynamicBlock
{
	/**
	 * @param  string                                          $blockName        Fully-qualified block name.
	 * @param  Closure(array<string, mixed>): mixed            $renderCallback   Render callback.
	 * @param  (Closure(array<string, mixed>): string)|null    $searchCallback   Optional searchable-text callback.
	 * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $validateCallback  Optional validator callback.
	 * @param  (Closure(Authenticatable|null, array<string, mixed>): bool)|null  $authorizeCallback  Optional authorization callback.
	 */
	public function __construct(
		protected string $blockName,
		protected Closure $renderCallback,
		protected ?Closure $searchCallback = null,
		protected ?Closure $validateCallback = null,
		protected ?Closure $authorizeCallback = null,
	) {
	}

	public function name(): string
	{
		return $this->blockName;
	}

	public function render( array $attrs )
	{
		return ( $this->renderCallback )( $attrs );
	}

	public function searchableText( array $attrs ): string
	{
		if ( null === $this->searchCallback ) {
			return parent::searchableText( $attrs );
		}

		return (string) ( $this->searchCallback )( $attrs );
	}

	public function validateAttrs( array $attrs ): array
	{
		if ( null === $this->validateCallback ) {
			return $attrs;
		}

		return ( $this->validateCallback )( $attrs );
	}

	public function authorize( ?Authenticatable $user, array $attrs ): bool
	{
		if ( null === $this->authorizeCallback ) {
			return true;
		}

		return (bool) ( $this->authorizeCallback )( $user, $attrs );
	}
}
