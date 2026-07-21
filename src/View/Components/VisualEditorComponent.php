<?php

/**
 * VisualEditor Blade component.
 *
 * Renders the mount point for the React editor bound to a specific Eloquent
 * model. Reverse-resolves the model's class to a resource slug via the
 * `artisanpack.visual-editor.resources` map and emits the `data-resource`,
 * `data-id`, and `data-api-base` attributes the React bootstrap consumes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\View\Components;

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use RuntimeException;

class VisualEditorComponent extends Component
{
	public string $resource;

	public string $modelId;

	public string $apiBase;

	public ?string $initialTitle;

	public ?string $initialSlug;

	public ?string $initialStatus;

	public ?string $initialExcerpt;

	/**
	 * @var array{ id: int, url: string, alt?: string }|null
	 */
	public ?array $initialFeaturedImage;

	public int|string|null $initialAuthorId;

	public ?bool $initialCommentsOpen;

	/**
	 * @var array<int, array{ value: int|string, label: string }>|null
	 */
	public ?array $authorOptions;

	/**
	 * @var array{ excerpt?: bool, featuredImage?: bool, comments?: bool }|null
	 */
	public ?array $supports;

	public ?string $previewUrl;

	/**
	 * ISO-8601 timestamp when the underlying model was created. Optional.
	 *
	 * Together with {@see $initialUpdatedAt}, the React editor uses these
	 * to derive the "never saved" signal that gates the #639 page-pattern
	 * inserter modal from auto-opening on already-saved-but-empty pages.
	 * Hosts that don't pass either can safely omit both — the detection
	 * hook falls back to "not fresh" and the modal never auto-opens
	 * (safe default; the toolbar button still works).
	 *
	 * @since 1.4.0
	 */
	public ?string $initialCreatedAt;

	/**
	 * ISO-8601 timestamp when the underlying model was last updated. See
	 * {@see $initialCreatedAt}.
	 *
	 * @since 1.4.0
	 */
	public ?string $initialUpdatedAt;

	/**
	 * @var array<int, array{slug: string, label: string}>
	 */
	public array $contentTypes;

	/**
	 * Serialised breakpoint registry (#617) — the merged config +
	 * theme.json + defaults snapshot the React shell hydrates the
	 * viewport switcher against.
	 *
	 * @var array<int, array{key: string, minWidthPx: int, previewWidthPx: int, label: string}>
	 */
	public array $breakpoints;

	public function __construct(
		public Model $model,
		?string $resource = null,
		?string $apiBase = null,
		?string $initialTitle = null,
		?string $initialSlug = null,
		?string $initialStatus = null,
		?string $initialExcerpt = null,
		?array $initialFeaturedImage = null,
		int|string|null $initialAuthorId = null,
		?bool $initialCommentsOpen = null,
		?array $authorOptions = null,
		?array $supports = null,
		?string $previewUrl = null,
	) {
		$key = $model->getKey();

		if ( ! $model->exists || null === $key || '' === (string) $key ) {
			throw new RuntimeException( sprintf(
				'Visual editor cannot mount against an unsaved %s. Persist the model before rendering <x-visual-editor>.',
				$model::class
			) );
		}

		$this->resource             = $resource ?? $this->resolveResourceSlug( $model );
		$this->modelId              = (string) $key;
		$this->apiBase              = $apiBase ?? $this->defaultApiBase();
		$this->initialTitle         = $initialTitle;
		$this->initialSlug          = $initialSlug;
		$this->initialStatus        = $initialStatus;
		$this->initialExcerpt       = $initialExcerpt;
		$this->initialFeaturedImage = $initialFeaturedImage;
		$this->initialAuthorId      = $initialAuthorId;
		$this->initialCommentsOpen  = $initialCommentsOpen;
		$this->authorOptions        = $authorOptions;
		$this->supports             = $supports;
		$this->previewUrl           = $previewUrl;
		$this->initialCreatedAt     = $this->resolveTimestamp( $model, 'created_at' );
		$this->initialUpdatedAt     = $this->resolveTimestamp( $model, 'updated_at' );
		$this->contentTypes         = $this->resolveContentTypes();
		$this->breakpoints          = app( BreakpointRegistry::class )->toArray();

		$this->applyEditorConfigFilter();
	}

	/**
	 * Identifies the editor surface for the `ap.visualEditor.editorConfig`
	 * filter. Subclasses (e.g. a future site-editor component) override
	 * to return `'site'` so callbacks can gate their overrides per screen.
	 *
	 * @since 1.6.0
	 *
	 * @return string
	 */
	protected function screen(): string
	{
		return 'post';
	}

	/**
	 * Runs the assembled editor config through the
	 * `ap.visualEditor.editorConfig` filter and re-hydrates the
	 * component's props from the filtered result.
	 *
	 * Each known prop declares a coercer that maps a filter-returned
	 * value to its declared type or falls back to the current value —
	 * so an extra key from the filter can't leak into unrelated props,
	 * and a malformed value can't poison a typed prop. To extend, add a
	 * new row to {@see configSchema()} rather than a fresh assignment.
	 *
	 * @since 1.6.0
	 *
	 * @return void
	 */
	protected function applyEditorConfigFilter(): void
	{
		$schema = $this->configSchema();
		$config = [];

		foreach ( array_keys( $schema ) as $key ) {
			$config[ $key ] = $this->{$key};
		}

		$filtered = applyFilters( 'ap.visualEditor.editorConfig', $config, $this->screen() );

		if ( ! is_array( $filtered ) ) {
			return;
		}

		foreach ( $schema as $key => $coercer ) {
			if ( ! array_key_exists( $key, $filtered ) ) {
				continue;
			}

			$this->{$key} = $coercer( $filtered[ $key ], $this->{$key} );
		}
	}

	/**
	 * Prop-name → coercer table for the editor config filter.
	 *
	 * Each coercer takes the filter-returned value plus the current prop
	 * value and returns whatever should land on the prop; returning
	 * `$current` is the "reject bad shape" fallback.
	 *
	 * @since 1.6.0
	 *
	 * @return array<string, callable(mixed, mixed): mixed>
	 */
	protected function configSchema(): array
	{
		$string             = static fn ( mixed $v, mixed $current ) => is_string( $v ) ? $v : $current;
		$nullableString     = static fn ( mixed $v, mixed $current ) => is_string( $v ) || null === $v ? $v : $current;
		$nullableArray      = static fn ( mixed $v, mixed $current ) => is_array( $v ) || null === $v ? $v : $current;
		$nullableBool       = static fn ( mixed $v, mixed $current ) => is_bool( $v ) || null === $v ? $v : $current;
		$nullableIntOrString = static fn ( mixed $v, mixed $current ) => is_int( $v ) || is_string( $v ) || null === $v ? $v : $current;
		$array              = static fn ( mixed $v, mixed $current ) => is_array( $v ) ? $v : $current;

		return [
			'resource'             => $string,
			'modelId'              => $string,
			'apiBase'              => $string,
			'initialTitle'         => $nullableString,
			'initialSlug'          => $nullableString,
			'initialStatus'        => $nullableString,
			'initialExcerpt'       => $nullableString,
			'initialFeaturedImage' => $nullableArray,
			'initialAuthorId'      => $nullableIntOrString,
			'initialCommentsOpen'  => $nullableBool,
			'authorOptions'        => $nullableArray,
			'supports'             => $nullableArray,
			'previewUrl'           => $nullableString,
			'initialCreatedAt'     => $nullableString,
			'initialUpdatedAt'     => $nullableString,
			'contentTypes'         => $array,
			'breakpoints'          => $array,
		];
	}

	/**
	 * Read a timestamp column off the model and normalize to an ISO-8601
	 * string with microsecond precision, or `null` when the column is
	 * absent / unparseable.
	 *
	 * `created_at` and `updated_at` are the conventional Eloquent
	 * timestamp columns; hosts with disabled timestamps or custom column
	 * names simply return `null` and the modal auto-open falls back to
	 * "not fresh" (safe default).
	 *
	 * Microsecond precision is intentional (#639): Carbon's default
	 * `toIso8601String()` collapses to whole-second `Y-m-d\TH:i:sP` — any
	 * save that lands in the same integer second as row creation then
	 * leaves `created_at === updated_at` byte-for-byte identical, and
	 * the JS-side "never saved" heuristic misfires as a permanent
	 * auto-open loop. Emitting sub-second precision keeps the first-save
	 * suppression tight without needing a separate boolean flag.
	 *
	 * @since 1.4.0
	 */
	protected function resolveTimestamp( Model $model, string $column ): ?string
	{
		$value = $model->getAttribute( $column );

		if ( null === $value ) {
			return null;
		}

		if ( is_object( $value ) && method_exists( $value, 'format' ) ) {
			return (string) $value->format( 'Y-m-d\TH:i:s.uP' );
		}

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Lists the registered cms-framework content types so the editor can
	 * surface variations of `artisanpack/single-content` (one per type)
	 * and any other block that wants to enumerate available resources.
	 *
	 * @return array<int, array{slug: string, label: string}>
	 *
	 * @since 1.0.0
	 */
	protected function resolveContentTypes(): array
	{
		$map = (array) config( 'artisanpack.visual-editor.resources', [] );
		$types = [];

		foreach ( $map as $plural => $modelClass ) {
			if ( ! is_string( $plural ) || '' === $plural ) {
				continue;
			}

			// Laravel's inflector handles English plural -> singular
			// reliably (handles `pages` -> `page`, `categories` ->
			// `category`, `classes` -> `class`, irregulars like
			// `children` -> `child`). Avoids the hand-rolled
			// `-es` heuristic mis-stripping the tail of legitimate
			// stems like `types`.
			$singular = Str::singular( $plural );

			$types[] = [
				// Match the WP convention — `postType` on a block is the
				// singular slug (`post`, `page`) so `useEntityProp` /
				// `getEntityRecord` find a matching entity descriptor.
				'slug'   => $singular,
				'plural' => $plural,
				'label'  => ucwords( str_replace( [ '-', '_' ], ' ', $singular ) ),
			];
		}

		return $types;
	}

	public function render(): View
	{
		return view( 'visual-editor::components.editor' );
	}

	/**
	 * Looks up the resource slug for the given model class.
	 *
	 * @since 1.0.0
	 */
	protected function resolveResourceSlug( Model $model ): string
	{
		$map   = (array) config( 'artisanpack.visual-editor.resources', [] );
		$class = $model::class;

		foreach ( $map as $slug => $modelClass ) {
			if ( is_string( $slug ) && $modelClass === $class ) {
				return $slug;
			}
		}

		throw new RuntimeException( sprintf(
			'Model %s is not registered in config("artisanpack.visual-editor.resources"). '
			. 'Add it to the resources map or pass an explicit :resource="..." prop.',
			$class
		) );
	}

	/**
	 * Returns the default API base path for the editor.
	 *
	 * @since 1.0.0
	 */
	protected function defaultApiBase(): string
	{
		return (string) config( 'artisanpack.visual-editor.api.base', '/visual-editor/api' );
	}
}
