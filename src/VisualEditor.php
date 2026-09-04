<?php

/**
 * Main VisualEditor class.
 *
 * Provides the public API for registering blocks and managing the visual
 * editor. Packages and applications use this class (via the Facade or
 * service container) to register their block types.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor;

use ArtisanPackUI\VisualEditor\Blocks\ClosureDynamicBlock;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Blocks\ProvidesBlockMetadata;
use ArtisanPackUI\VisualEditor\DynamicContent\HostDynamicContentSource;
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicContentSourceRegistry;
use Closure;
use InvalidArgumentException;
use JsonException;

class VisualEditor
{
	/**
	 * The host Dynamic Content source registry.
	 *
	 * Nullable in the constructor to preserve backward compatibility with
	 * the pre-1.9 two-argument signature that a host app / test may still
	 * be using to construct `new VisualEditor(...)` directly. When the
	 * caller omits it we lazy-instantiate a fresh registry on first use.
	 *
	 * @since 1.9.0
	 */
	protected ?DynamicContentSourceRegistry $dynamicContentSourceRegistry;

	public function __construct(
		protected BlockTypeRegistry $registry,
		protected DynamicBlockRegistry $dynamicRegistry,
		?DynamicContentSourceRegistry $dynamicContentSourceRegistry = null,
	) {
		$this->dynamicContentSourceRegistry = $dynamicContentSourceRegistry;
	}

	/**
	 * Resolve the Dynamic Content source registry, materializing a
	 * fresh one on first use when the constructor was called without
	 * one (the pre-1.9 two-argument shape).
	 *
	 * @since 1.9.0
	 */
	protected function dynamicContentSourceRegistry(): DynamicContentSourceRegistry
	{
		return $this->dynamicContentSourceRegistry
			??= new DynamicContentSourceRegistry();
	}

	/**
	 * Registers a block type from one of three sources.
	 *
	 *   1. **Path string** — an absolute path to a `block.json` manifest.
	 *      The file is read and parsed, and the full metadata is stored
	 *      in the block type registry.
	 *
	 *      `VisualEditor::registerBlock(__DIR__ . '/callout/block.json');`
	 *
	 *   2. **Class name** — a string that resolves to a class implementing
	 *      {@see ProvidesBlockMetadata}. The static `blockMetadata()` method
	 *      is invoked to obtain the metadata array.
	 *
	 *      `VisualEditor::registerBlock(CalloutBlock::class);`
	 *
	 *   3. **Closure** — any callable that returns a metadata array. Useful
	 *      when the metadata is computed at registration time (e.g. pulling
	 *      attribute defaults from config).
	 *
	 *      `VisualEditor::registerBlock(fn () => ['name' => 'acme/callout', ...]);`
	 *
	 * In all three cases the returned metadata must contain a non-empty
	 * `name` field in `namespace/name` format.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|Closure  $source  Path to block.json, a class name that implements
	 *                                  {@see ProvidesBlockMetadata}, or a closure that
	 *                                  returns a metadata array.
	 *
	 * @throws InvalidArgumentException When the source is invalid or the resulting
	 *                                  metadata is missing a `name` field.
	 * @throws JsonException            When a block.json file cannot be parsed.
	 */
	public function registerBlock( $source ): void
	{
		$metadata = $this->resolveBlockMetadata( $source );

		if ( ! isset( $metadata['name'] ) || ! is_string( $metadata['name'] ) ) {
			throw new InvalidArgumentException(
				'Block metadata is missing a non-empty "name" field.'
			);
		}

		$normalizedName = trim( $metadata['name'] );

		if ( '' === $normalizedName ) {
			throw new InvalidArgumentException(
				'Block metadata is missing a non-empty "name" field.'
			);
		}

		// Store the trimmed name back into the metadata array so the
		// registry ends up with a canonical value in both the key and
		// the definition's own `name` field. Format validation
		// (namespace/name, lowercase, hyphens) is enforced by
		// {@see BlockTypeRegistry::register()}.
		$metadata['name'] = $normalizedName;

		$this->registry->register( $normalizedName, $metadata );
	}

	/**
	 * Resolve the block metadata array from the registration source.
	 *
	 * @since 1.0.0
	 *
	 * @param  string|Closure  $source
	 *
	 * @return array<string, mixed>
	 */
	protected function resolveBlockMetadata( $source ): array
	{
		if ( $source instanceof Closure ) {
			$value = ( $source )();

			if ( ! is_array( $value ) ) {
				throw new InvalidArgumentException(
					'Block registration closure must return an array of metadata.'
				);
			}

			return $value;
		}

		if ( ! is_string( $source ) || '' === trim( $source ) ) {
			throw new InvalidArgumentException(
				'Block registration requires a block.json path, a class name, or a closure.'
			);
		}

		if ( class_exists( $source ) ) {
			if ( ! is_subclass_of( $source, ProvidesBlockMetadata::class ) && ! in_array( ProvidesBlockMetadata::class, class_implements( $source ) ?: [], true ) ) {
				throw new InvalidArgumentException( sprintf(
					'Block class "%s" must implement %s.',
					$source,
					ProvidesBlockMetadata::class
				) );
			}

			$value = $source::blockMetadata();

			if ( ! is_array( $value ) ) {
				throw new InvalidArgumentException( sprintf(
					'%s::blockMetadata() must return an array.',
					$source
				) );
			}

			return $value;
		}

		return $this->loadBlockJsonMetadata( $source );
	}

	/**
	 * Read and decode a `block.json` manifest file into a metadata array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function loadBlockJsonMetadata( string $blockJsonPath ): array
	{
		if ( ! file_exists( $blockJsonPath ) ) {
			throw new InvalidArgumentException(
				sprintf( 'block.json not found: %s', $blockJsonPath )
			);
		}

		$json = file_get_contents( $blockJsonPath );

		if ( false === $json ) {
			throw new InvalidArgumentException(
				sprintf( 'Unable to read block.json: %s', $blockJsonPath )
			);
		}

		$metadata = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );

		if ( ! is_array( $metadata ) ) {
			throw new InvalidArgumentException(
				sprintf( 'block.json did not decode to an object: %s', $blockJsonPath )
			);
		}

		return $metadata;
	}

	/**
	 * Registers a block type programmatically without a block.json file.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $name        The block name (e.g. `artisanpack/paragraph`).
	 * @param  array<string, mixed>  $definition  Block metadata matching the block.json schema.
	 */
	public function registerBlockType( string $name, array $definition ): void
	{
		$this->registry->register( $name, [ 'name' => $name ] + $definition );
	}

	/**
	 * Registers a server-rendered block in a single call — both its editor
	 * metadata and its PHP render callback.
	 *
	 * This is the one-call path for a downstream package (a host app, a
	 * module, or a WordPress-style plugin/theme) that wants a block to appear
	 * in the editor inserter, expose editable attributes, and render on the
	 * public page — **without** shipping a compiled client `edit` component or
	 * rebuilding this package's editor bundle. The editor discovers the block
	 * at boot through the block-types endpoint (which flags it
	 * `apServerRender`) and synthesizes a generic edit component
	 * (server-side-render preview + attribute-driven inspector controls) from
	 * the metadata's `attributes` schema (#766).
	 *
	 * The `$metadata` array is block.json-shaped — `title`, `category`,
	 * `icon`, `description`, `attributes`, `supports`, etc. Any `name` inside
	 * it is ignored; the `$name` argument is authoritative. The `$render`
	 * callback receives the validated attributes and returns the block's HTML
	 * (a string, {@see \Stringable}, or a view). Optional `$callbacks` mirror
	 * the closure-form entries of {@see registerDynamicBlock()}
	 * (`searchableText`, `validateAttrs`, `authorize`).
	 *
	 * For a bespoke editor experience the client escape hatch
	 * (`window.ApVisualEditor.registerBlockType`) remains available; this
	 * method is the zero-client-build default.
	 *
	 * @since 1.8.0
	 *
	 * @param  array<string, mixed>     $metadata
	 * @param  array<string, callable>  $callbacks
	 */
	public function registerServerBlock( string $name, array $metadata, callable $render, array $callbacks = [] ): DynamicBlock
	{
		// The block name is authoritative; never let a stray `name` inside the
		// metadata array shadow it or reach the registry as a conflicting key.
		unset( $metadata['name'] );

		// Build — and thereby validate — the dynamic block BEFORE mutating the
		// type registry. A non-callable callback throws here, so it can never
		// leave a block type registered without its server renderer, which
		// would reach the editor unflagged (no `apServerRender`) and so with no
		// synthesizable edit component.
		$block = $this->buildClosureDynamicBlock( $name, [ 'render' => $render ] + $callbacks );

		$this->registerBlockType( $name, $metadata );
		$this->dynamicRegistry->register( $block );

		return $block;
	}

	/**
	 * Register a host Dynamic Content token source.
	 *
	 * Lets a host app, another package, or a cms-framework plugin/theme
	 * declare its own token source (e.g. `business.name`, `business.phone`)
	 * resolved at render time by the supplied `resolver` callable. Host
	 * sources merge with cms-framework's DB-authored types in the editor's
	 * inserter and the render-time binding resolver — a slug collision
	 * resolves to the host registration, so an app can intentionally
	 * shadow a cms-framework type.
	 *
	 * Definition shape:
	 * - `slug`         string   Lowercase snake_case source slug. Required.
	 * - `label`        string   Human-readable label (defaults to `slug`).
	 * - `cardinality`  string   `singleton` (one bag of fields) or `collection`
	 *                           (list of rows). Required.
	 * - `fields`       array    List of `{slug, label, type, description?}`.
	 * - `resolver`     callable Returns the source's data:
	 *                             - singleton → `array<string, mixed>` (or null)
	 *                             - collection → `list<array<string, mixed>>`
	 * - `description`  string   Optional prose for the inserter panel.
	 * - `icon`         string   Optional icon slug for the inserter panel.
	 *
	 * @since 1.9.0
	 *
	 * @param  array<string, mixed>  $definition
	 */
	public function registerDynamicContentSource( array $definition ): HostDynamicContentSource
	{
		$source = HostDynamicContentSource::fromArray( $definition );

		$this->dynamicContentSourceRegistry()->register( $source );

		return $source;
	}

	/**
	 * Returns the block type registry instance.
	 *
	 * @since 1.0.0
	 */
	public function getRegistry(): BlockTypeRegistry
	{
		return $this->registry;
	}

	/**
	 * Returns the dynamic block registry instance.
	 *
	 * @since 1.0.0
	 */
	public function getDynamicBlockRegistry(): DynamicBlockRegistry
	{
		return $this->dynamicRegistry;
	}

	/**
	 * Returns the host Dynamic Content source registry instance.
	 *
	 * @since 1.9.0
	 */
	public function getDynamicContentSourceRegistry(): DynamicContentSourceRegistry
	{
		return $this->dynamicContentSourceRegistry();
	}

	/**
	 * Register a server-rendered (dynamic) block.
	 *
	 * Supports two registration styles:
	 *
	 *   1. Class form — pass the fully-qualified class name of a
	 *      {@see DynamicBlock} subclass. The class is resolved from the
	 *      container so constructor dependencies are injected normally.
	 *
	 *      `VisualEditor::registerDynamicBlock(LatestPostsBlock::class);`
	 *
	 *   2. Closure form — pass the block name as the first argument and an
	 *      array of callbacks as the second. `render` is required; the other
	 *      callbacks fall back to the defaults on {@see DynamicBlock}.
	 *
	 *      `VisualEditor::registerDynamicBlock('acme/latest-posts', [
	 *          'render' => fn (array $attrs) => view('blocks.latest-posts', $attrs),
	 *          'searchableText' => fn (array $attrs) => $attrs['title'] ?? '',
	 *      ]);`
	 *
	 * @since 1.0.0
	 *
	 * @param  DynamicBlock|class-string<DynamicBlock>|string  $blockOrName
	 * @param  array<string, callable>|null                    $config
	 */
	public function registerDynamicBlock( $blockOrName, ?array $config = null ): DynamicBlock
	{
		$block = $this->resolveDynamicBlock( $blockOrName, $config );

		$this->dynamicRegistry->register( $block );

		return $block;
	}

	/**
	 * Resolve the appropriate {@see DynamicBlock} instance for the arguments
	 * passed to {@see registerDynamicBlock()}.
	 *
	 * @since 1.0.0
	 *
	 * @param  DynamicBlock|class-string<DynamicBlock>|string  $blockOrName
	 * @param  array<string, callable>|null                    $config
	 */
	protected function resolveDynamicBlock( $blockOrName, ?array $config ): DynamicBlock
	{
		if ( $blockOrName instanceof DynamicBlock ) {
			return $blockOrName;
		}

		if ( ! is_string( $blockOrName ) || '' === trim( $blockOrName ) ) {
			throw new InvalidArgumentException( 'Dynamic block registration requires a class name, block name, or DynamicBlock instance.' );
		}

		if ( null === $config ) {
			return $this->instantiateDynamicBlockClass( $blockOrName );
		}

		return $this->buildClosureDynamicBlock( $blockOrName, $config );
	}

	/**
	 * Instantiate a dynamic block class via the container.
	 *
	 * @since 1.0.0
	 *
	 * @param  class-string<DynamicBlock>|string  $class
	 */
	protected function instantiateDynamicBlockClass( string $class ): DynamicBlock
	{
		if ( ! class_exists( $class ) ) {
			throw new InvalidArgumentException( sprintf( 'Dynamic block class "%s" does not exist.', $class ) );
		}

		if ( ! is_subclass_of( $class, DynamicBlock::class ) ) {
			throw new InvalidArgumentException( sprintf( 'Dynamic block class "%s" must extend %s.', $class, DynamicBlock::class ) );
		}

		$instance = app( $class );

		if ( ! $instance instanceof DynamicBlock ) {
			throw new InvalidArgumentException( sprintf( 'Container resolved "%s" to a non-DynamicBlock instance.', $class ) );
		}

		return $instance;
	}

	/**
	 * Build a {@see ClosureDynamicBlock} from a name + callback array.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, callable>  $config
	 */
	protected function buildClosureDynamicBlock( string $name, array $config ): ClosureDynamicBlock
	{
		$render = $config['render'] ?? null;

		if ( ! is_callable( $render ) ) {
			throw new InvalidArgumentException( sprintf( 'Dynamic block "%s" must supply a callable "render" entry.', $name ) );
		}

		return new ClosureDynamicBlock(
			blockName: $name,
			renderCallback: Closure::fromCallable( $render ),
			searchCallback: $this->optionalCallback( $name, $config, 'searchableText' ),
			validateCallback: $this->optionalCallback( $name, $config, 'validateAttrs' ),
			authorizeCallback: $this->optionalCallback( $name, $config, 'authorize' ),
		);
	}

	/**
	 * Pull an optional closure-form callback out of the registration config.
	 *
	 * Returns null when the key is absent. Throws when the key is present but
	 * the value is not callable — a silent fallback there hides typos and
	 * makes customizations appear to "work" while using the default logic.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $config
	 */
	protected function optionalCallback( string $name, array $config, string $key ): ?Closure
	{
		if ( ! array_key_exists( $key, $config ) ) {
			return null;
		}

		$value = $config[ $key ];

		if ( ! is_callable( $value ) ) {
			throw new InvalidArgumentException( sprintf(
				'Dynamic block "%s" has a non-callable "%s" entry.',
				$name,
				$key
			) );
		}

		return Closure::fromCallable( $value );
	}

	/**
	 * Returns the fully-qualified names of blocks that should be exposed to
	 * the editor after the allow-list + deny-list filters run.
	 *
	 * Resolution order:
	 *   1. Start with the configured `enabled_blocks` allow-list. When
	 *      empty, fall back to every block currently in the registry — the
	 *      allow-list is only enforced when the host app has opted in.
	 *   2. Remove anything in the `disabled_blocks` deny-list.
	 *   3. De-duplicate and preserve authoring order.
	 *
	 * The return value is deterministic (no registry lookups, no locale
	 * sorting) so it can drive a snapshot test.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function getEnabledBlockNames(): array
	{
		$enabled  = $this->stringListFromConfig( 'artisanpack.visual-editor.enabled_blocks' );
		$disabled = $this->stringListFromConfig( 'artisanpack.visual-editor.disabled_blocks' );

		$candidates = [] === $enabled
			? array_column( $this->registry->all(), 'name' )
			: $enabled;

		$denyIndex = array_flip( $disabled );
		$seen      = [];
		$result    = [];

		foreach ( $candidates as $name ) {
			if ( ! is_string( $name ) ) {
				continue;
			}

			$normalized = trim( $name );

			if ( '' === $normalized || isset( $denyIndex[ $normalized ] ) || isset( $seen[ $normalized ] ) ) {
				continue;
			}

			$seen[ $normalized ] = true;
			$result[]            = $normalized;
		}

		return $result;
	}

	/**
	 * Pulls a config key, coerces it to a list of trimmed non-empty strings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function stringListFromConfig( string $key ): array
	{
		$raw = config( $key, [] );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$out = [];

		foreach ( $raw as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			$trimmed = trim( $value );

			if ( '' !== $trimmed ) {
				$out[] = $trimmed;
			}
		}

		return $out;
	}
}
