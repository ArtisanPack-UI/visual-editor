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
		$rawId = $attrs['formId'] ?? null;
		$formId = is_numeric( $rawId ) ? (int) $rawId : 0;

		return [
			'formId'    => $formId,
			'className' => isset( $attrs['className'] ) && is_string( $attrs['className'] ) ? $attrs['className'] : '',
		];
	}

	public function render( array $attrs ): string
	{
		$formId = (int) ( $attrs['formId'] ?? 0 );

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

		$classes = $this->wrapperClasses( $attrs );

		return sprintf(
			'<div class="%s" data-keystone-form="%s" data-form-id="%d"></div>',
			e( implode( ' ', $classes ) ),
			e( $form->slug ),
			$form->id
		);
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 */
	protected function placeholder( array $attrs, string $message ): string
	{
		$classes   = $this->wrapperClasses( $attrs );
		$classes[] = 'wp-block-artisanpack-form--placeholder';

		return sprintf(
			'<div class="%s"><p>%s</p></div>',
			e( implode( ' ', $classes ) ),
			e( $message )
		);
	}

	/**
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<int, string>
	 */
	protected function wrapperClasses( array $attrs ): array
	{
		$classes = [ 'wp-block-artisanpack-form' ];

		if ( isset( $attrs['className'] ) && '' !== $attrs['className'] ) {
			$classes[] = (string) $attrs['className'];
		}

		return $classes;
	}
}
