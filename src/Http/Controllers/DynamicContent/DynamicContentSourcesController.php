<?php

/**
 * DynamicContent sources listing controller.
 *
 * Feeds the token inserter modal and the RichText `{{` autocomplete.
 * Returns the merged (DB + code-registered) Dynamic Content universe
 * with per-source field metadata so the editor can render grouped
 * results without a chained round-trip to cms-framework's own
 * `/api/v1/dynamic-content/types/registered` endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\DynamicContent;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

class DynamicContentSourcesController extends Controller
{
	/**
	 * List registered Dynamic Content types and their fields.
	 *
	 * Response body:
	 * - `sources` list<{
	 *       slug: string,
	 *       label: string,
	 *       cardinality: 'singleton'|'collection',
	 *       origin: 'db'|'code',
	 *       fields: list<{ slug, label, type }>
	 *     }>
	 *
	 * When cms-framework is not installed the response is `{ sources: [] }`
	 * — the editor UI surfaces an empty state instead of failing.
	 *
	 * @since 1.4.0
	 */
	public function index(): JsonResponse
	{
		$registryClass = 'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Managers\\DynamicContentTypeRegistry';

		if ( ! class_exists( $registryClass ) ) {
			return response()->json( [ 'sources' => [] ] );
		}

		try {
			/** @var object $registry */
			$registry = app( $registryClass );
			$types    = $registry->all();
		} catch ( Throwable $e ) {
			report( $e );

			return response()->json( [ 'sources' => [] ] );
		}

		if ( ! is_array( $types ) ) {
			return response()->json( [ 'sources' => [] ] );
		}

		$sources = [];

		foreach ( $types as $slug => $definition ) {
			if ( ! is_string( $slug ) || ! is_array( $definition ) ) {
				continue;
			}

			$cardinality = $definition['cardinality'] ?? null;
			$origin      = $definition['source'] ?? null;

			$sources[] = [
				'slug'        => $slug,
				'label'       => (string) ( $definition['name'] ?? $slug ),
				'cardinality' => $this->enumString( $cardinality, 'singleton' ),
				'origin'      => $this->enumString( $origin, 'db' ),
				'description' => (string) ( $definition['description'] ?? '' ),
				'icon'        => (string) ( $definition['icon'] ?? '' ),
				'fields'      => $this->mapFields( $definition['fields'] ?? [] ),
			];
		}

		return response()->json( [ 'sources' => $sources ] );
	}

	/**
	 * @param  array<int, mixed>  $fields
	 *
	 * @return list<array{slug: string, label: string, type: string}>
	 *
	 * @since 1.4.0
	 */
	protected function mapFields( array $fields ): array
	{
		$mapped = [];

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$slug = (string) ( $field['slug'] ?? '' );

			if ( '' === $slug ) {
				continue;
			}

			$mapped[] = [
				'slug'  => $slug,
				'label' => (string) ( $field['label'] ?? $slug ),
				'type'  => (string) ( $field['type'] ?? 'text' ),
			];
		}

		return $mapped;
	}

	/**
	 * @since 1.4.0
	 */
	protected function enumString( mixed $value, string $default ): string
	{
		if ( $value instanceof \BackedEnum ) {
			return (string) $value->value;
		}

		return is_string( $value ) && '' !== $value ? $value : $default;
	}
}
