<?php

/**
 * Contract for a block binding source driver.
 *
 * A binding source plugs into the visual-editor's binding layer (#504) and
 * supplies values for bound block attributes at render time. Built-in
 * drivers cover the cms-framework custom fields, post core columns, and
 * dotted-path relation lookups; third-party packages can register their
 * own sources (site settings, an external API, a feature-flag service)
 * via {@see \ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry}.
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

interface BlockBindingSource
{
	/**
	 * Unique identifier used in the block's `bindings` map (`source` key).
	 *
	 * Must be a lowercase snake_case slug — e.g. `custom_field`, `post_core`,
	 * `relation`. The registry rejects empty names and prevents collisions.
	 *
	 * @since 1.1.0
	 */
	public function name(): string;

	/**
	 * Resolve a single binding to its value.
	 *
	 * Implementations should return the raw value associated with the
	 * binding's arguments — the resolver applies the empty-value policy
	 * and any coercion afterwards. Return `null` when the bound field is
	 * empty / missing so the resolver can run the `onEmpty` policy.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, mixed>  $args  The binding's `args` map.
	 */
	public function resolve( BindingContext $context, array $args ): mixed;

	/**
	 * Optional eager-load hints for the parent model.
	 *
	 * Called once per render pass with every binding's args that targets
	 * this source. Drivers that read related models (e.g. RelationSource
	 * walking `author.profile.name`) should return the dotted relation
	 * paths the resolver should preload to avoid N+1 queries. Drivers
	 * that read columns on the parent row return an empty array.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<int, array<string, mixed>>  $bindingArgs  Every binding's `args` targeting this source.
	 *
	 * @return array<int, string>
	 */
	public function eagerLoadRelations( array $bindingArgs ): array;

	/**
	 * Catalog of fields the inspector picker can offer for a given resource.
	 *
	 * The editor's "link to data" toggle calls this through the
	 * `BindingSourcesController` to populate the source's field dropdown.
	 * Drivers that resolve free-form paths (e.g. RelationSource) can return
	 * an empty array — the inspector falls back to a text input.
	 *
	 * Each entry must be a map of shape:
	 *  - `key`   string  — value persisted into `args.key` (or `args.path`)
	 *  - `label` string  — human-readable label for the picker
	 *  - `type`  string  — one of `string|number|boolean|url|image|date|datetime|html`
	 *
	 * The `$resource` argument is the resource slug (e.g. `posts`,
	 * `pages`) from `config('artisanpack.visual-editor.resources')`. The
	 * `$modelClass` argument is the resolved Eloquent class, so drivers
	 * that introspect (e.g. CustomFieldSource scanning cms-framework
	 * columns) can build their catalog from the schema rather than the
	 * slug alone.
	 *
	 * @since 1.1.0
	 *
	 * @param  class-string<\Illuminate\Database\Eloquent\Model>|null  $modelClass
	 *
	 * @return array<int, array{ key: string, label: string, type: string }>
	 */
	public function availableFields( string $resource, ?string $modelClass = null ): array;
}
