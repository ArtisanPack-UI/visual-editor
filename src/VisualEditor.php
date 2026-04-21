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
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use Closure;
use InvalidArgumentException;
use JsonException;

class VisualEditor
{
	public function __construct(
		protected BlockTypeRegistry $registry,
		protected DynamicBlockRegistry $dynamicRegistry,
	) {
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
