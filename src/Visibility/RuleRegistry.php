<?php

/**
 * Ordered collection of {@see VisibilityRule} instances.
 *
 * Rules are seeded from the built-in set by
 * {@see \ArtisanPackUI\VisualEditor\VisualEditorServiceProvider} and
 * extended by hosts through the `ap.visualEditor.visibility.registerRules`
 * filter. The registry is stateless past construction: the evaluator asks
 * it for the full ordered rule list every time it walks a tree.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Visibility;

class RuleRegistry
{
	/**
	 * @var array<string, VisibilityRule>
	 */
	protected array $rules = [];

	/**
	 * @param  array<int, VisibilityRule>  $rules
	 */
	public function __construct( array $rules = [] )
	{
		foreach ( $rules as $rule ) {
			$this->register( $rule );
		}
	}

	public function register( VisibilityRule $rule ): void
	{
		$this->rules[ $rule->key() ] = $rule;
	}

	public function get( string $key ): ?VisibilityRule
	{
		return $this->rules[ $key ] ?? null;
	}

	/**
	 * @return array<int, VisibilityRule>
	 */
	public function all(): array
	{
		return array_values( $this->rules );
	}
}
