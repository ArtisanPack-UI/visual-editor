<?php

/**
 * Form Block.
 *
 * A dynamic block that embeds a configurable form from
 * the artisanpack-ui/forms package. Shows a live preview
 * in the editor and handles AJAX submission on the frontend
 * via a Livewire component.
 *
 * Gracefully degrades if the forms package is not installed.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Form
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\Form;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Livewire\Blocks\FormBlockComponent;
use Throwable;

/**
 * Form dynamic block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Form
 *
 * @since      1.0.0
 */
class FormBlock extends DynamicBlock
{
	/**
	 * Get the Livewire component class for this dynamic block.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getComponent(): string
	{
		return FormBlockComponent::class;
	}

	/**
	 * Get the content field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'formId'            => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.form_id' ),
				'options' => $this->getFormOptions(),
				'default' => null,
				'panel'   => __( 'visual-editor::ve.form_panel_form_selection' ),
			],
			'displayStyle'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.form_display_style' ),
				'options' => [
					'embedded'   => __( 'visual-editor::ve.form_style_embedded' ),
					'modal'      => __( 'visual-editor::ve.form_style_modal' ),
					'slide-over' => __( 'visual-editor::ve.form_style_slide_over' ),
				],
				'default' => 'embedded',
				'panel'   => __( 'visual-editor::ve.form_panel_display' ),
			],
			'showLabels'        => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.form_show_labels' ),
				'default' => true,
				'panel'   => __( 'visual-editor::ve.form_panel_display' ),
			],
			'layout'            => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.form_layout' ),
				'options' => [
					'stacked' => __( 'visual-editor::ve.form_layout_stacked' ),
					'inline'  => __( 'visual-editor::ve.form_layout_inline' ),
					'grid'    => __( 'visual-editor::ve.form_layout_grid' ),
				],
				'default' => 'stacked',
				'panel'   => __( 'visual-editor::ve.form_panel_display' ),
			],
			'columns'           => [
				'type'     => 'range',
				'label'    => __( 'visual-editor::ve.form_columns' ),
				'min'      => 1,
				'max'      => 4,
				'default'  => 2,
				'panel'    => __( 'visual-editor::ve.form_panel_display' ),
				'showWhen' => [ 'layout' => 'grid' ],
			],
			'submitButtonText'  => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.form_submit_button_text' ),
				'placeholder' => __( 'visual-editor::ve.form_submit_button_text_placeholder' ),
				'hint'        => __( 'visual-editor::ve.form_submit_button_text_hint' ),
				'default'     => '',
				'panel'       => __( 'visual-editor::ve.form_panel_submission' ),
			],
			'submitButtonColor' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.form_submit_button_color' ),
				'options' => [
					'primary'   => __( 'visual-editor::ve.primary' ),
					'secondary' => __( 'visual-editor::ve.secondary' ),
					'accent'    => __( 'visual-editor::ve.accent' ),
					'success'   => __( 'visual-editor::ve.success' ),
					'warning'   => __( 'visual-editor::ve.warning' ),
					'error'     => __( 'visual-editor::ve.error' ),
					'info'      => __( 'visual-editor::ve.info' ),
				],
				'default' => 'primary',
				'panel'   => __( 'visual-editor::ve.form_panel_submission' ),
			],
			'submitButtonSize'  => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.form_submit_button_size' ),
				'options' => [
					'sm' => __( 'visual-editor::ve.small' ),
					'md' => __( 'visual-editor::ve.medium' ),
					'lg' => __( 'visual-editor::ve.large' ),
				],
				'default' => 'md',
				'panel'   => __( 'visual-editor::ve.form_panel_submission' ),
			],
			'successMessage'    => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.form_success_message' ),
				'placeholder' => __( 'visual-editor::ve.form_success_message_placeholder' ),
				'hint'        => __( 'visual-editor::ve.form_success_message_hint' ),
				'default'     => '',
				'panel'       => __( 'visual-editor::ve.form_panel_submission' ),
			],
			'redirectUrl'       => [
				'type'        => 'url',
				'label'       => __( 'visual-editor::ve.form_redirect_url' ),
				'placeholder' => __( 'visual-editor::ve.form_redirect_url_placeholder' ),
				'default'     => '',
				'panel'       => __( 'visual-editor::ve.form_panel_submission' ),
			],
			'useAjax'           => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.form_use_ajax' ),
				'default' => true,
				'panel'   => __( 'visual-editor::ve.form_panel_advanced' ),
			],
			'enableHoneypot'    => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.form_enable_honeypot' ),
				'default' => true,
				'panel'   => __( 'visual-editor::ve.form_panel_advanced' ),
			],
			'prefillViaUrl'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.form_prefill_via_url' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.form_panel_advanced' ),
			],
			'customClass'       => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.form_custom_class' ),
				'placeholder' => __( 'visual-editor::ve.form_custom_class_placeholder' ),
				'default'     => '',
				'panel'       => __( 'visual-editor::ve.form_panel_advanced' ),
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'fieldSpacing' => [
				'type'    => 'unit',
				'label'   => __( 'visual-editor::ve.form_field_spacing' ),
				'default' => '1rem',
			],
		] );
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		return [
			[
				'group'    => 'block',
				'controls' => [
					[
						'type'    => 'select',
						'field'   => 'displayStyle',
						'source'  => 'content',
						'options' => [
							[ 'value' => 'embedded', 'label' => __( 'visual-editor::ve.form_style_embedded' ) ],
							[ 'value' => 'modal', 'label' => __( 'visual-editor::ve.form_style_modal' ) ],
							[ 'value' => 'slide-over', 'label' => __( 'visual-editor::ve.form_style_slide_over' ) ],
						],
					],
				],
			],
			[
				'group'    => 'form-actions',
				'controls' => [
					[
						'type'  => 'button',
						'field' => 'editForm',
						'label' => __( 'visual-editor::ve.form_edit_form' ),
						'icon'  => 'pencil-square',
					],
				],
			],
		];
	}

	/**
	 * Get available forms as select options.
	 *
	 * Returns an associative array of form ID => form name for the
	 * form selector dropdown. Gracefully returns an empty option if
	 * the forms package is not installed.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function getFormOptions(): array
	{
		$options = [ '' => __( 'visual-editor::ve.form_select_a_form' ) ];

		if ( ! class_exists( \ArtisanPackUI\Forms\Models\Form::class ) ) {
			return $options;
		}

		try {
			$forms = \ArtisanPackUI\Forms\Models\Form::query()
				->orderBy( 'name' )
				->get();

			if ( function_exists( 'applyFilters' ) ) {
				$filtered = applyFilters( 've.form-block.available-forms', $forms );
				if ( is_iterable( $filtered ) ) {
					$forms = $filtered;
				}
			}

			foreach ( $forms as $form ) {
				$label                         = $form->name;
				if ( ! $form->is_active ) {
					$label .= ' (' . __( 'visual-editor::ve.form_inactive_label' ) . ')';
				}
				$options[ (string) $form->id ] = $label;
			}
		} catch ( Throwable $e ) {
			// Database table may not exist yet; return default options.
		}

		return $options;
	}
}
