<?php

/**
 * Context carried through one render pass of the binding resolver.
 *
 * Bundles the parent model the bindings should resolve against, optional
 * draft attribute overrides from the editor (so unsaved custom-field
 * edits are reflected in the preview), and a free-form `extras` bag for
 * third-party source drivers that need their own scoped data. The
 * resolver constructs a context per request:
 *
 * - Editor preview: parent model loaded fresh, draft snapshot applied.
 * - Frontend render: queried/host model passed through, no draft.
 *
 * The object is intentionally immutable — every mutator returns a new
 * instance so a shared resolver pass can safely fork the context for
 * inner blocks without leaking state.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings;

use Illuminate\Database\Eloquent\Model;

class BindingContext
{
	/**
	 * @param  Model|null            $model   Parent record bindings resolve against. Null
	 *                                        means "no context" — every binding falls back.
	 * @param  array<string, mixed>  $draft   Per-attribute overrides (column → value) the editor
	 *                                        applies for unsaved field edits.
	 * @param  array<string, mixed>  $extras  Free-form bag for custom drivers (e.g. site-settings,
	 *                                        feature-flag identity).
	 */
	public function __construct(
		protected ?Model $model = null,
		protected array $draft = [],
		protected array $extras = []
	) {
	}

	/**
	 * The parent model bindings resolve against. Null when no model is in scope.
	 *
	 * @since 1.1.0
	 */
	public function model(): ?Model
	{
		return $this->model;
	}

	/**
	 * The draft snapshot — column → value overrides applied by editor previews.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function draft(): array
	{
		return $this->draft;
	}

	/**
	 * Read a single draft column, or null when no override exists for it.
	 *
	 * @since 1.1.0
	 */
	public function draftValue( string $key ): mixed
	{
		return $this->draft[ $key ] ?? null;
	}

	/**
	 * The free-form extras bag for custom source drivers.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function extras(): array
	{
		return $this->extras;
	}

	/**
	 * Return a clone of this context with the given model.
	 *
	 * @since 1.1.0
	 */
	public function withModel( ?Model $model ): self
	{
		return new self( $model, $this->draft, $this->extras );
	}

	/**
	 * Return a clone of this context with the given draft snapshot.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $draft
	 */
	public function withDraft( array $draft ): self
	{
		return new self( $this->model, $draft, $this->extras );
	}

	/**
	 * Return a clone of this context with the given extras bag.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $extras
	 */
	public function withExtras( array $extras ): self
	{
		return new self( $this->model, $this->draft, $extras );
	}
}
