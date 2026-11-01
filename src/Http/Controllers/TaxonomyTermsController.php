<?php

/**
 * Taxonomy terms controller.
 *
 * Lists a taxonomy's terms for the Query Loop's Filters panel (#1029).
 * Terms come from the same sources the Terms List block reads, through
 * {@see CategoriesBlock::termsForTaxonomy()}, so a host taxonomy answered
 * by the `ap.visualEditor.terms` filter works here with no extra wiring.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Blocks\Core\CategoriesBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class TaxonomyTermsController extends Controller
{
	/**
	 * Returns the taxonomy's terms as `{ id, name }` rows. Host terms may
	 * repeat an id or use one `taxQuery` rejects, so those rows are dropped.
	 *
	 * @since 1.20.0
	 *
	 * @param  CategoriesBlock  $block     Block that owns the term sources.
	 * @param  string           $taxonomy  Taxonomy slug.
	 *
	 * @return JsonResponse An empty list when the taxonomy has no source.
	 */
	public function index( CategoriesBlock $block, string $taxonomy ): JsonResponse
	{
		$terms = $block->termsForTaxonomy( $taxonomy )
			->map( static fn ( object $term ): array => [
				'id'   => (int) $term->id,
				'name' => (string) $term->name,
			] )
			->filter( static fn ( array $term ): bool => $term['id'] > 0 )
			->unique( 'id' )
			->values()
			->all();

		return response()->json( [ 'data' => $terms ] );
	}
}
