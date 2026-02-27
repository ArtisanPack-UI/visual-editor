<?php

/**
 * Supports Panel Registry.
 *
 * Maps block supports declarations to inspector panels,
 * providing auto-generated UI controls for the Styles tab.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Inspector
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Inspector;

use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;

/**
 * Registry that maps block supports to inspector panels.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Inspector
 *
 * @since      2.0.0
 */
class SupportsPanelRegistry
{
	/**
	 * Get the ordered inspector panels for a given block.
	 *
	 * Each panel contains a key, label, and list of controls
	 * to render in the Styles tab.
	 *
	 * @since 2.0.0
	 *
	 * @param BlockInterface $block The block to generate panels for.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPanelsForBlock( BlockInterface $block ): array
	{
		$panels         = [];
		$activeSupports = $block->getActiveStyleSupports();

		if ( $this->hasAnySupport( $activeSupports, [ 'color.text', 'color.background' ] ) ) {
			$panels[] = [
				'key'      => 'color',
				'label'    => __( 'visual-editor::ve.color' ),
				'controls' => $this->getColorControls( $activeSupports ),
			];
		}

		if ( $this->hasAnySupport( $activeSupports, [ 'typography.fontSize', 'typography.fontFamily', 'typography.dropCap' ] ) ) {
			$panels[] = [
				'key'      => 'typography',
				'label'    => __( 'visual-editor::ve.typography' ),
				'controls' => $this->getTypographyControls( $activeSupports ),
			];
		}

		if ( $this->hasAnySupport( $activeSupports, [ 'spacing.margin', 'spacing.padding' ] ) ) {
			$panels[] = [
				'key'      => 'spacing',
				'label'    => __( 'visual-editor::ve.spacing' ),
				'controls' => $this->getSpacingControls( $activeSupports ),
			];
		}

		if ( in_array( 'border', $activeSupports, true ) ) {
			$panels[] = [
				'key'      => 'border',
				'label'    => __( 'visual-editor::ve.border' ),
				'controls' => [ [ 'type' => 'border', 'field' => 'border' ] ],
			];
		}

		if ( in_array( 'shadow', $activeSupports, true ) ) {
			$panels[] = [
				'key'      => 'shadow',
				'label'    => __( 'visual-editor::ve.shadow' ),
				'controls' => [ [ 'type' => 'shadow', 'field' => 'shadow' ] ],
			];
		}

		if ( $this->hasAnySupport( $activeSupports, [ 'dimensions.aspectRatio', 'dimensions.minHeight' ] ) ) {
			$panels[] = [
				'key'      => 'dimensions',
				'label'    => __( 'visual-editor::ve.dimensions' ),
				'controls' => $this->getDimensionControls( $activeSupports ),
			];
		}

		if ( $this->hasAnySupport( $activeSupports, [ 'background.backgroundImage', 'background.backgroundSize', 'background.backgroundPosition', 'background.backgroundGradient' ] ) ) {
			$panels[] = [
				'key'      => 'background',
				'label'    => __( 'visual-editor::ve.background' ),
				'controls' => $this->getBackgroundControls( $activeSupports ),
			];
		}

		if ( function_exists( 'applyFilters' ) ) {
			$panels = applyFilters( 'ap.visualEditor.inspectorPanels', $panels, $block );
		}

		return $panels;
	}

	/**
	 * Check if any of the given supports are active.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, string> $activeSupports The active supports list.
	 * @param array<int, string> $check          The supports to check for.
	 *
	 * @return bool
	 */
	protected function hasAnySupport( array $activeSupports, array $check ): bool
	{
		return count( array_intersect( $activeSupports, $check ) ) > 0;
	}

	/**
	 * Get color panel controls.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, string> $activeSupports Active supports list.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function getColorControls( array $activeSupports ): array
	{
		$controls = [];

		if ( in_array( 'color.text', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'color',
				'field' => 'textColor',
				'label' => __( 'visual-editor::ve.text_color' ),
			];
		}

		if ( in_array( 'color.background', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'color',
				'field' => 'backgroundColor',
				'label' => __( 'visual-editor::ve.background_color' ),
			];
		}

		return $controls;
	}

	/**
	 * Get typography panel controls.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, string> $activeSupports Active supports list.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function getTypographyControls( array $activeSupports ): array
	{
		$controls = [];

		if ( in_array( 'typography.fontSize', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'font_size',
				'field' => 'fontSize',
				'label' => __( 'visual-editor::ve.font_size' ),
			];
		}

		if ( in_array( 'typography.fontFamily', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'select',
				'field' => 'fontFamily',
				'label' => __( 'visual-editor::ve.font_family' ),
			];
		}

		if ( in_array( 'typography.dropCap', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'toggle',
				'field' => 'dropCap',
				'label' => __( 'visual-editor::ve.drop_cap' ),
			];
		}

		return $controls;
	}

	/**
	 * Get spacing panel controls.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, string> $activeSupports Active supports list.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function getSpacingControls( array $activeSupports ): array
	{
		$controls = [];

		if ( in_array( 'spacing.margin', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'spacing',
				'field' => 'margin',
				'label' => __( 'visual-editor::ve.margin' ),
			];
		}

		if ( in_array( 'spacing.padding', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'spacing',
				'field' => 'padding',
				'label' => __( 'visual-editor::ve.padding' ),
			];
		}

		return $controls;
	}

	/**
	 * Get dimension panel controls.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, string> $activeSupports Active supports list.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function getDimensionControls( array $activeSupports ): array
	{
		$controls = [];

		if ( in_array( 'dimensions.aspectRatio', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'select',
				'field' => 'aspectRatio',
				'label' => __( 'visual-editor::ve.aspect_ratio' ),
			];
		}

		if ( in_array( 'dimensions.minHeight', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'unit',
				'field' => 'minHeight',
				'label' => __( 'visual-editor::ve.min_height' ),
			];
		}

		return $controls;
	}

	/**
	 * Get background panel controls.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, string> $activeSupports Active supports list.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function getBackgroundControls( array $activeSupports ): array
	{
		$controls = [];

		if ( in_array( 'background.backgroundImage', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'url',
				'field' => 'backgroundImage',
				'label' => __( 'visual-editor::ve.background_image' ),
			];
		}

		if ( in_array( 'background.backgroundSize', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'select',
				'field' => 'backgroundSize',
				'label' => __( 'visual-editor::ve.background_size' ),
			];
		}

		if ( in_array( 'background.backgroundPosition', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'select',
				'field' => 'backgroundPosition',
				'label' => __( 'visual-editor::ve.background_position' ),
			];
		}

		if ( in_array( 'background.backgroundGradient', $activeSupports, true ) ) {
			$controls[] = [
				'type'  => 'text',
				'field' => 'backgroundGradient',
				'label' => __( 'visual-editor::ve.background_gradient' ),
			];
		}

		return $controls;
	}
}
