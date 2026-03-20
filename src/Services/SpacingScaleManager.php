<?php

/**
 * Spacing Scale Manager Service.
 *
 * Manages spacing scale definitions, CSS custom property generation,
 * preset scales, and block gap configuration for the visual editor's
 * global styles system.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use InvalidArgumentException;

/**
 * Service for managing spacing scales with CSS generation and preset support.
 *
 * Provides default spacing steps, custom value support, CSS custom
 * property generation, block gap configuration, and predefined scale
 * presets (compact, default, spacious).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class SpacingScaleManager
{

	/**
	 * Allowed spacing step keys.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_STEPS = [ 'xs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl' ];

	/**
	 * Allowed preset names.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_PRESETS = [ 'compact', 'default', 'spacious' ];

	/**
	 * The default spacing scale.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{name: string, slug: string, value: string}>
	 */
	public const DEFAULT_SCALE = [
		'xs'  => [
			'name'  => 'Extra Small',
			'slug'  => 'xs',
			'value' => '0.25rem',
		],
		'sm'  => [
			'name'  => 'Small',
			'slug'  => 'sm',
			'value' => '0.5rem',
		],
		'md'  => [
			'name'  => 'Medium',
			'slug'  => 'md',
			'value' => '1rem',
		],
		'lg'  => [
			'name'  => 'Large',
			'slug'  => 'lg',
			'value' => '1.5rem',
		],
		'xl'  => [
			'name'  => 'Extra Large',
			'slug'  => 'xl',
			'value' => '2rem',
		],
		'2xl' => [
			'name'  => '2X Large',
			'slug'  => '2xl',
			'value' => '3rem',
		],
		'3xl' => [
			'name'  => '3X Large',
			'slug'  => '3xl',
			'value' => '4rem',
		],
	];

	/**
	 * The default block gap step.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const DEFAULT_BLOCK_GAP = 'md';

	/**
	 * Predefined scale presets.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{scale: array<string, string>, blockGap: string}>
	 */
	public const PRESETS = [
		'compact'  => [
			'scale'    => [
				'xs'  => '0.125rem',
				'sm'  => '0.25rem',
				'md'  => '0.5rem',
				'lg'  => '0.75rem',
				'xl'  => '1rem',
				'2xl' => '1.5rem',
				'3xl' => '2rem',
			],
			'blockGap' => 'sm',
		],
		'default'  => [
			'scale'    => [
				'xs'  => '0.25rem',
				'sm'  => '0.5rem',
				'md'  => '1rem',
				'lg'  => '1.5rem',
				'xl'  => '2rem',
				'2xl' => '3rem',
				'3xl' => '4rem',
			],
			'blockGap' => 'md',
		],
		'spacious' => [
			'scale'    => [
				'xs'  => '0.5rem',
				'sm'  => '1rem',
				'md'  => '1.5rem',
				'lg'  => '2.5rem',
				'xl'  => '4rem',
				'2xl' => '6rem',
				'3xl' => '8rem',
			],
			'blockGap' => 'lg',
		],
	];

	/**
	 * The current spacing scale entries.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{name: string, slug: string, value: string}>
	 */
	protected array $scale = [];

	/**
	 * The current block gap step slug.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $blockGap;

	/**
	 * Custom spacing entries added beyond the default steps.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{name: string, slug: string, value: string}>
	 */
	protected array $customSteps = [];

	/**
	 * Create a new SpacingScaleManager instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $config Initial configuration.
	 */
	public function __construct( array $config = [] )
	{
		if ( isset( $config['scale'] ) && is_array( $config['scale'] ) ) {
			$this->scale = $this->buildScaleFromValues( $config['scale'] );
		} else {
			$this->scale = self::DEFAULT_SCALE;
		}

		$this->blockGap = $config['blockGap'] ?? self::DEFAULT_BLOCK_GAP;

		if ( isset( $config['customSteps'] ) && is_array( $config['customSteps'] ) ) {
			foreach ( $config['customSteps'] as $entry ) {
				if ( isset( $entry['slug'], $entry['name'], $entry['value'] ) && $this->isValidDimension( $entry['value'] ) ) {
					$this->customSteps[ $entry['slug'] ] = $entry;
				}
			}
		}
	}

	/**
	 * Get the full spacing scale (including custom steps).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{name: string, slug: string, value: string}>
	 */
	public function getScale(): array
	{
		$merged = array_merge( $this->scale, $this->customSteps );

		return veApplyFilters( 'ap.visualEditor.spacingScale', $merged );
	}

	/**
	 * Set the full scale, replacing all entries.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array{name: string, slug: string, value: string}> $scale The scale entries.
	 *
	 * @return void
	 */
	public function setScale( array $scale ): void
	{
		$this->scale       = $scale;
		$this->customSteps = [];
	}

	/**
	 * Get a single spacing step by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The step slug.
	 *
	 * @return array{name: string, slug: string, value: string}|null
	 */
	public function getStep( string $slug ): ?array
	{
		$scale = $this->getScale();

		return $scale[ $slug ] ?? null;
	}

	/**
	 * Get the CSS value for a spacing step.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The step slug.
	 *
	 * @return string|null The CSS value or null.
	 */
	public function getStepValue( string $slug ): ?string
	{
		$step = $this->getStep( $slug );

		return $step['value'] ?? null;
	}

	/**
	 * Set a spacing step value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug  The step slug.
	 * @param string $name  The display name.
	 * @param string $value The CSS value (e.g. '1rem', '16px').
	 *
	 * @throws InvalidArgumentException If the value is not a valid CSS dimension.
	 *
	 * @return void
	 */
	public function setStep( string $slug, string $name, string $value ): void
	{
		$value = $this->validateDimension( $value );

		$entry = [
			'name'  => $name,
			'slug'  => $slug,
			'value' => $value,
		];

		if ( in_array( $slug, self::ALLOWED_STEPS, true ) ) {
			$this->scale[ $slug ] = $entry;
		} else {
			$this->customSteps[ $slug ] = $entry;
		}
	}

	/**
	 * Remove a spacing step by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The step slug.
	 *
	 * @return void
	 */
	public function removeStep( string $slug ): void
	{
		unset( $this->scale[ $slug ], $this->customSteps[ $slug ] );
	}

	/**
	 * Check if a spacing step exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The step slug.
	 *
	 * @return bool
	 */
	public function hasStep( string $slug ): bool
	{
		return isset( $this->scale[ $slug ] ) || isset( $this->customSteps[ $slug ] );
	}

	/**
	 * Get the block gap step slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getBlockGap(): string
	{
		return $this->blockGap;
	}

	/**
	 * Set the block gap step slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The step slug to use for block gap.
	 *
	 * @return void
	 */
	public function setBlockGap( string $slug ): void
	{
		$this->blockGap = $slug;
	}

	/**
	 * Get the resolved CSS value for the block gap.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null The CSS value or null if the step doesn't exist.
	 */
	public function getBlockGapValue(): ?string
	{
		return $this->getStepValue( $this->blockGap );
	}

	/**
	 * Get the default spacing scale.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{name: string, slug: string, value: string}>
	 */
	public function getDefaultScale(): array
	{
		return self::DEFAULT_SCALE;
	}

	/**
	 * Get the default block gap slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getDefaultBlockGap(): string
	{
		return self::DEFAULT_BLOCK_GAP;
	}

	/**
	 * Reset the scale and block gap to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function resetToDefaults(): void
	{
		$this->scale       = self::DEFAULT_SCALE;
		$this->blockGap    = self::DEFAULT_BLOCK_GAP;
		$this->customSteps = [];
	}

	/**
	 * Apply a predefined spacing preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string $preset The preset name (compact, default, spacious).
	 *
	 * @throws InvalidArgumentException If the preset is not recognized.
	 *
	 * @return void
	 */
	public function applyPreset( string $preset ): void
	{
		if ( ! in_array( $preset, self::ALLOWED_PRESETS, true ) ) {
			throw new InvalidArgumentException(
				"Unknown spacing preset: {$preset}. Allowed: " . implode( ', ', self::ALLOWED_PRESETS ) . '.',
			);
		}

		$presetData = self::PRESETS[ $preset ];

		$this->scale       = $this->buildScaleFromValues( $presetData['scale'] );
		$this->blockGap    = $presetData['blockGap'];
		$this->customSteps = [];
	}

	/**
	 * Get the available presets with their configurations.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{scale: array<string, string>, blockGap: string}>
	 */
	public function getPresets(): array
	{
		return self::PRESETS;
	}

	/**
	 * Resolve a spacing reference to a CSS value.
	 *
	 * Supports references in the format 'spacing:slug' (e.g., 'spacing:md').
	 * Returns the original value if it is not a spacing reference.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value or spacing reference.
	 *
	 * @return string The resolved CSS value.
	 */
	public function resolveSpacingReference( string $value ): string
	{
		if ( ! str_starts_with( $value, 'spacing:' ) ) {
			return $value;
		}

		$slug     = substr( $value, 8 );
		$resolved = $this->getStepValue( $slug );

		return $resolved ?? $value;
	}

	/**
	 * Generate CSS custom properties for the spacing scale.
	 *
	 * Produces CSS variables for each spacing step and the block gap.
	 * Output is a raw CSS string (without a selector).
	 *
	 * @since 1.0.0
	 *
	 * @return string The CSS custom properties.
	 */
	public function generateCssProperties(): string
	{
		$lines = [];
		$scale = $this->getScale();

		foreach ( $scale as $entry ) {
			$lines[] = '--ve-spacing-' . $entry['slug'] . ': ' . $entry['value'] . ';';
		}

		$gapValue = $this->getBlockGapValue();

		if ( null !== $gapValue ) {
			$lines[] = '--ve-block-gap: var(--ve-spacing-' . $this->blockGap . ');';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Generate a full CSS :root rule for the spacing scale.
	 *
	 * @since 1.0.0
	 *
	 * @return string The complete CSS rule.
	 */
	public function generateCssBlock(): string
	{
		$properties = $this->generateCssProperties();

		if ( '' === $properties ) {
			return '';
		}

		return ":root {\n" . $this->indentCss( $properties ) . "\n}";
	}

	/**
	 * Build the spacing data as a format suitable for the Alpine store.
	 *
	 * @since 1.0.0
	 *
	 * @return array{scale: array<int, array{name: string, slug: string, value: string}>, blockGap: string, customSteps: array<int, array{name: string, slug: string, value: string}>}
	 */
	public function toStoreFormat(): array
	{
		return [
			'scale'       => array_values( $this->scale ),
			'blockGap'    => $this->blockGap,
			'customSteps' => array_values( $this->customSteps ),
		];
	}

	/**
	 * Build the spacing data from a store format array.
	 *
	 * @since 1.0.0
	 *
	 * @param array{scale?: array<int, array{name: string, slug: string, value: string}>, blockGap?: string, customSteps?: array<int, array{name: string, slug: string, value: string}>} $data The store format data.
	 *
	 * @return void
	 */
	public function fromStoreFormat( array $data ): void
	{
		if ( isset( $data['scale'] ) && is_array( $data['scale'] ) ) {
			$scale = [];

			foreach ( $data['scale'] as $entry ) {
				if ( isset( $entry['slug'], $entry['name'], $entry['value'] ) && $this->isValidDimension( $entry['value'] ) ) {
					$scale[ $entry['slug'] ] = $entry;
				}
			}

			$this->scale = $scale;
		}

		if ( isset( $data['blockGap'] ) && is_string( $data['blockGap'] ) ) {
			$this->blockGap = preg_replace( '/[^a-zA-Z0-9_-]/', '', $data['blockGap'] );
		}

		if ( isset( $data['customSteps'] ) && is_array( $data['customSteps'] ) ) {
			$custom = [];

			foreach ( $data['customSteps'] as $entry ) {
				if ( isset( $entry['slug'], $entry['name'], $entry['value'] ) && $this->isValidDimension( $entry['value'] ) ) {
					$custom[ $entry['slug'] ] = $entry;
				}
			}

			$this->customSteps = $custom;
		}
	}

	/**
	 * Build a scale array from a simple slug => value map.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $values The slug => CSS value map.
	 *
	 * @return array<string, array{name: string, slug: string, value: string}>
	 */
	protected function buildScaleFromValues( array $values ): array
	{
		$scale = [];
		$names = [
			'xs'  => 'Extra Small',
			'sm'  => 'Small',
			'md'  => 'Medium',
			'lg'  => 'Large',
			'xl'  => 'Extra Large',
			'2xl' => '2X Large',
			'3xl' => '3X Large',
		];

		foreach ( $values as $slug => $value ) {
			$scale[ $slug ] = [
				'name'  => $names[ $slug ] ?? ucfirst( $slug ),
				'slug'  => $slug,
				'value' => $value,
			];
		}

		return $scale;
	}

	/**
	 * Validate a CSS dimension value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to validate.
	 *
	 * @throws InvalidArgumentException If the value is not a valid CSS dimension.
	 *
	 * @return string The validated value.
	 */
	protected function validateDimension( string $value ): string
	{
		$value = trim( $value );

		if ( '0' === $value ) {
			return $value;
		}

		if ( ! preg_match( '/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|vmin|vmax|ch|ex)$/', $value ) ) {
			throw new InvalidArgumentException(
				"Invalid CSS dimension: {$value}. Expected a value with a valid CSS unit (e.g. '1rem', '16px').",
			);
		}

		return $value;
	}

	/**
	 * Check if a value is a valid CSS dimension without throwing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to check.
	 *
	 * @return bool
	 */
	protected function isValidDimension( string $value ): bool
	{
		$value = trim( $value );

		if ( '0' === $value ) {
			return true;
		}

		return (bool) preg_match( '/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|vmin|vmax|ch|ex)$/', $value );
	}

	/**
	 * Indent CSS lines with a tab character.
	 *
	 * @since 1.0.0
	 *
	 * @param string $css The CSS string to indent.
	 *
	 * @return string The indented CSS.
	 */
	protected function indentCss( string $css ): string
	{
		$lines = explode( "\n", $css );

		return implode( "\n", array_map( fn ( string $line ) => "\t" . $line, $lines ) );
	}
}
