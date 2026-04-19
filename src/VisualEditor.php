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
	 * Registers a block type from a block.json manifest file.
	 *
	 * Reads the JSON file, validates it has a `name` field, and stores
	 * the full metadata in the block type registry.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $blockJsonPath  Absolute path to the block.json file.
	 *
	 * @throws InvalidArgumentException When the file doesn't exist or is invalid.
	 * @throws JsonException            When the JSON cannot be parsed.
	 */
	public function registerBlock( string $blockJsonPath ): void
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

		if ( ! is_array( $metadata ) || ! isset( $metadata['name'] ) || ! is_string( $metadata['name'] ) || '' === trim( $metadata['name'] ) ) {
			throw new InvalidArgumentException(
				sprintf( 'block.json missing required "name" field: %s', $blockJsonPath )
			);
		}

		$this->registry->register( $metadata['name'], $metadata );
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
			searchCallback: isset( $config['searchableText'] ) && is_callable( $config['searchableText'] )
				? Closure::fromCallable( $config['searchableText'] )
				: null,
			validateCallback: isset( $config['validateAttrs'] ) && is_callable( $config['validateAttrs'] )
				? Closure::fromCallable( $config['validateAttrs'] )
				: null,
			authorizeCallback: isset( $config['authorize'] ) && is_callable( $config['authorize'] )
				? Closure::fromCallable( $config['authorize'] )
				: null,
		);
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
