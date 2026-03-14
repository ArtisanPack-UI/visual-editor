<?php

/**
 * Map Embed Block.
 *
 * Embeds interactive or static maps from Google Maps
 * or OpenStreetMap with configurable coordinates, zoom,
 * map type, and marker settings.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\MapEmbed
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Embed\MapEmbed;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Map Embed block for the visual editor.
 *
 * Supports Google Maps and OpenStreetMap providers with
 * address search, coordinate input, and interactive/static modes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\MapEmbed
 *
 * @since      1.0.0
 */
class MapEmbedBlock extends BaseBlock
{
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
			'provider'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.map_provider' ),
				'options' => [
					'openstreetmap' => 'OpenStreetMap',
					'google'        => 'Google Maps',
				],
				'default' => 'openstreetmap',
			],
			'address'     => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.map_address' ),
				'default' => '',
			],
			'latitude'    => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.map_latitude' ),
				'default' => '',
			],
			'longitude'   => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.map_longitude' ),
				'default' => '',
			],
			'zoom'        => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.map_zoom' ),
				'min'     => 1,
				'max'     => 20,
				'default' => 13,
			],
			'mapType'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.map_type' ),
				'options' => [
					'roadmap'   => __( 'visual-editor::ve.map_type_roadmap' ),
					'satellite' => __( 'visual-editor::ve.map_type_satellite' ),
					'terrain'   => __( 'visual-editor::ve.map_type_terrain' ),
					'hybrid'    => __( 'visual-editor::ve.map_type_hybrid' ),
				],
				'default' => 'roadmap',
			],
			'markerLabel' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.map_marker_label' ),
				'default' => '',
			],
			'interactive' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.map_interactive' ),
				'default' => true,
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Map-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'height' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.map_height' ),
				'default' => '400px',
			],
		] );
	}

	/**
	 * Get available block transforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array
	{
		return [
			'embed' => [
				'url' => '_mapUrl',
			],
		];
	}
}
