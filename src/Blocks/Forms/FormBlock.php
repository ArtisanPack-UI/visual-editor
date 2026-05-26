<?php

/**
 * Server-rendered `artisanpack/form` block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Forms;

use ArtisanPackUI\Forms\Models\Form;
use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

/**
 * Renders a placeholder DIV that the artisanpack-ui/forms React island
 * mounts a `<FormRenderer />` onto on the public site.
 *
 * The block deliberately does not render the form fields server-side —
 * field configuration, conditional logic, and validation all live in the
 * React renderer, which fetches `/api/v1/forms/{id}/render` and posts to
 * `/api/v1/forms/{id}/submit`. Keeping the markup to a single mount-point
 * keeps the two paths (block render + standalone /forms/{id} page)
 * pixel-identical.
 *
 * Hosts must publish the form island bundle so the mount-point hydrates.
 * Keystone CMS ships one at `resources/js/keystone-form-island.tsx`; any
 * other consumer can ship an equivalent that scans for
 * `[data-keystone-form]` elements and calls FormRenderer on each.
 *
 * Styling: the block declares the full Gutenberg-parity supports surface
 * (typography, color, border, spacing, plus `className`/`anchor`) in its
 * block.json. Wrapper class + style attributes are compiled via
 * {@see BlockSupports::wrapperAttrs()} — the same path every other
 * server-rendered block in the visual-editor stack goes through. That
 * makes the form a first-class citizen of theme.json / the site editor's
 * global styles: presets serialize as `has-{slug}-*` classes; custom
 * values land as inline declarations referencing `--wp--preset--*` so
 * theme-token changes flow through without a republish.
 */
class FormBlock extends DynamicBlock
{
	public function name(): string
	{
		return 'artisanpack/form';
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<string, mixed>
	 */
	public function validateAttrs( array $attrs ): array
	{
		// Block-supports attributes pass through verbatim.
		// `BlockSupports::compile()` already shape-checks each branch —
		// non-strings collapse to '', non-array `style` is dropped — so
		// an over-eager validator here would either duplicate that
		// logic or strip valid editor input. The validator stays
		// authoritative only over `formId`, which goes through the
		// strict integer parser in `normalizeFormId()` (CodeRabbit PR
		// #467: float strings and scientific notation would otherwise
		// silently coerce into unrelated form ids).
		$normalized = [ 'formId' => $this->normalizeFormId( $attrs['formId'] ?? null ) ];

		foreach ( [ 'className', 'anchor', 'align', 'textAlign', 'backgroundColor', 'textColor', 'gradient', 'borderColor', 'fontSize', 'fontFamily' ] as $key ) {
			if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
				$normalized[ $key ] = $attrs[ $key ];
			}
		}

		if ( isset( $attrs['style'] ) && is_array( $attrs['style'] ) ) {
			$normalized['style'] = $attrs['style'];
		}

		return $normalized;
	}

	public function render( array $attrs ): string
	{
		$formId = $this->normalizeFormId( $attrs['formId'] ?? null );

		if ( $formId <= 0 ) {
			return $this->placeholder( $attrs, __( 'Select a form to display.' ) );
		}

		$form = Form::query()->find( $formId );

		if ( null === $form ) {
			return $this->placeholder( $attrs, __( 'The selected form is no longer available.' ) );
		}

		if ( ! $form->is_active ) {
			return $this->placeholder( $attrs, __( 'The selected form is inactive.' ) );
		}

		return sprintf(
			'<div%s data-keystone-form="%s" data-form-id="%d"></div>',
			BlockSupports::wrapperAttrs( $attrs, [ 'wp-block-artisanpack-form' ] ),
			e( $form->slug ),
			$form->id
		);
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function placeholder( array $attrs, string $message ): string
	{
		return sprintf(
			'<div%s><p>%s</p></div>',
			BlockSupports::wrapperAttrs( $attrs, [ 'wp-block-artisanpack-form', 'wp-block-artisanpack-form--placeholder' ] ),
			e( $message )
		);
	}

	/**
	 * Coerce the incoming `formId` into a strict positive integer or 0.
	 *
	 * The editor saves the attribute as a JSON number, but block-tree
	 * deserialization can hand back strings ("12") or floats ("12.9",
	 * "1e2"). `is_numeric` + `(int)` accepts and truncates floats /
	 * scientific notation, which would silently coerce non-IDs (e.g.,
	 * "1e2" → 100) into real lookup attempts. `FILTER_VALIDATE_INT`
	 * rejects anything that isn't a clean integer literal, and the
	 * positive-only guard keeps `0` / negatives funneling into the
	 * "select a form" placeholder branch in {@see render()}.
	 *
	 * @since 1.1.0
	 */
	protected function normalizeFormId( mixed $value ): int
	{
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}

		if ( is_string( $value ) ) {
			$parsed = filter_var( $value, FILTER_VALIDATE_INT );

			return false !== $parsed && $parsed > 0 ? $parsed : 0;
		}

		return 0;
	}
}
