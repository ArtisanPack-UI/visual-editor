<?php

/**
 * Shared font-management authorisation for the Font Library form requests.
 *
 * Resolves the `manage_fonts` capability through {@see FontPolicy} in the
 * request's `authorize()` step — which runs *before* validation — so an
 * unauthorised caller is rejected before a large multipart upload is
 * materialised and size-validated, and the response keeps the same shaped JSON
 * 403 (with the `read_only` signal) the controller emits for the other Font
 * Library endpoints.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Fonts;

use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

trait AuthorizesFontManagement
{
	/**
	 * Authorise the request against the `manage_fonts` capability.
	 *
	 * @since 1.7.0
	 */
	public function authorize(): bool
	{
		return Gate::allows( 'manage', Font::class );
	}

	/**
	 * Reject an unauthorised request with the Font Library's shaped 403.
	 *
	 * @since 1.7.0
	 */
	protected function failedAuthorization(): void
	{
		throw new HttpResponseException( new JsonResponse( [
			'error'     => 'forbidden',
			'message'   => __( 'You do not have permission to manage fonts.' ),
			'read_only' => true,
		], Response::HTTP_FORBIDDEN ) );
	}
}
