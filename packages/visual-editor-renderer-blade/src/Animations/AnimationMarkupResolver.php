<?php

/**
 * Animation markup resolver — Blade renderer (#489).
 *
 * Bridges the editor's `artisanpackAnimations` attribute bag to the
 * markup pieces a Blade partial needs:
 *
 *  - The CSS scope (`.ap-block-<uid>`) the per-block rules attach to.
 *  - The class list to add to the block wrapper (`ap-anim`,
 *    `ap-anim-pre` for entrance blocks).
 *  - The `data-ap-anim-*` attribute map the runtime keys off.
 *  - The CSS string itself (`<style>` payload).
 *  - The noscript fallback CSS (wrapped in `<noscript>` by the caller).
 *
 * The class list and data map are returned as arrays so the partial can
 * `implode` them into the existing attribute soup without parsing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Animations;

use ArtisanPackUI\VisualEditor\Animations\AnimationCssEmitter;

class AnimationMarkupResolver
{
	public function __construct( protected AnimationCssEmitter $emitter ) {}

	/**
	 * Resolves the bag for one block scope. Returns the markup pieces a
	 * Blade partial needs as a single associative array.
	 *
	 * @since 1.1.0
	 *
	 * @param  string                $scope        e.g. `.ap-block-abc123`.
	 * @param  array<string, mixed>  $attributes   The `artisanpackAnimations` bag.
	 *
	 * @return array{
	 *     hasAnimations: bool,
	 *     hasEntrance: bool,
	 *     classes: array<int, string>,
	 *     data: array<string, string>,
	 *     css: string,
	 *     noscriptCss: string,
	 * }
	 */
	public function resolve( string $scope, array $attributes ): array
	{
		return [
			'hasAnimations' => $this->emitter->hasAny( $attributes ),
			'hasEntrance'   => $this->emitter->hasEntrance( $attributes ),
			'classes'       => $this->emitter->wrapperClasses( $attributes ),
			'data'          => $this->emitter->dataAttributes( $attributes ),
			'css'           => $this->emitter->emit( $scope, $attributes ),
			'noscriptCss'   => $this->emitter->noscriptCss( $scope ),
		];
	}

	/**
	 * Emits the data-* attribute soup as a `key="value"`-joined HTML
	 * fragment, ready to drop into a Blade attribute slot.
	 *
	 * @since 1.1.0
	 *
	 * @param  array<string, string>  $data
	 */
	public function dataString( array $data ): string
	{
		$pieces = [];
		foreach ( $data as $key => $value ) {
			$pieces[] = sprintf( '%s="%s"', $key, htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) );
		}

		return implode( ' ', $pieces );
	}
}
