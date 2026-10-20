<?php

/**
 * Generic resource entry controller.
 *
 * Saves the title and excerpt of any model registered in the visual-editor
 * resource map. Custom content types have no dedicated entity adapter like
 * posts/pages, so the editor routes their Post Title / Post Excerpt edits
 * here (#992).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.3
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers;

use ArtisanPackUI\VisualEditor\Http\Requests\UpdateResourceEntryRequest;
use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResourceEntryController extends Controller
{
	/**
	 * Entry fields this endpoint may write.
	 *
	 * @since 1.15.3
	 *
	 * @var array<int, string>
	 */
	protected const FIELDS = [ 'title', 'excerpt' ];

	public function __construct( protected ResourceResolver $resolver )
	{
	}

	/**
	 * Writes the validated title/excerpt onto the resolved model.
	 *
	 * A field the model's record doesn't carry is rejected with a 422
	 * rather than skipped, so the editor never reports a save that didn't
	 * happen.
	 *
	 * @since 1.15.3
	 *
	 * @param  UpdateResourceEntryRequest  $request   The validated request.
	 * @param  string                      $resource  The resource slug.
	 * @param  int|string                  $id        The record id.
	 *
	 * @throws ValidationException When a sent field has no column on the record.
	 *
	 * @return JsonResponse The saved entry.
	 */
	public function update(
		UpdateResourceEntryRequest $request,
		string $resource,
		int|string $id,
	): JsonResponse {
		$model = $this->resolver->resolve( $resource, $id );

		Gate::authorize( 'update', $model );

		$data   = $request->validated();
		$errors = [];

		foreach ( self::FIELDS as $field ) {
			if ( array_key_exists( $field, $data ) && ! $this->hasColumn( $model, $field ) ) {
				$errors[ $field ] = __( 'This entry has no :field field.', [ 'field' => $field ] );
			}
		}

		if ( [] !== $errors ) {
			throw ValidationException::withMessages( $errors );
		}

		foreach ( self::FIELDS as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$model->setAttribute( $field, $data[ $field ] ?? '' );
			}
		}

		$model->save();

		return response()->json( $this->transform( $resource, $model ) );
	}

	/**
	 * Whether the loaded record carries the given column.
	 *
	 * @since 1.15.3
	 *
	 * @param  Model   $model  The resolved model.
	 * @param  string  $field  The column name.
	 *
	 * @return bool Whether the column is present.
	 */
	protected function hasColumn( Model $model, string $field ): bool
	{
		return array_key_exists( $field, $model->getAttributes() );
	}

	/**
	 * Shapes the saved entry like the WP-style entity records the editor
	 * already holds, so the client can merge it straight into its store.
	 *
	 * @since 1.15.3
	 *
	 * @param  string  $resource  The resource slug.
	 * @param  Model   $model     The saved model.
	 *
	 * @return array<string, mixed> The response payload.
	 */
	protected function transform( string $resource, Model $model ): array
	{
		$payload = [
			'id'       => $model->getKey(),
			'resource' => $resource,
		];

		foreach ( self::FIELDS as $field ) {
			if ( $this->hasColumn( $model, $field ) ) {
				$value             = (string) ( $model->getAttribute( $field ) ?? '' );
				$payload[ $field ] = [ 'raw' => $value, 'rendered' => $value ];
			}
		}

		$payload['updated_at'] = $model->updated_at?->toIso8601String();

		return $payload;
	}
}
