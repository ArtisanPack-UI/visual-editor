<?php

/**
 * Font Library REST controller.
 *
 * The public JSON surface the Font Library modal drives: browse installed
 * fonts and provider catalogs, install a catalog font, upload a custom font,
 * and uninstall one or many. Read endpoints stay open to any authenticated
 * user and flag the session read-only; every mutating action is gated by
 * {@see \ArtisanPackUI\VisualEditor\Fonts\Policies\FontPolicy} — a user without
 * the `manage_fonts` capability gets a shaped 403 rather than the gate's HTML.
 *
 * The heavy lifting (provider fetch, self-hosting, `fonts.css` regeneration,
 * transactional rollback) lives in {@see FontInstaller}; this controller only
 * validates, authorizes, and shapes the response.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Controllers\Fonts;

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontFileWriteException;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontInstallationException;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontProviderException;
use ArtisanPackUI\VisualEditor\Fonts\Models\Font;
use ArtisanPackUI\VisualEditor\Fonts\Models\FontFace;
use ArtisanPackUI\VisualEditor\Fonts\Policies\FontPolicy;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;
use ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller;
use ArtisanPackUI\VisualEditor\Http\Requests\Fonts\InstallFontRequest;
use ArtisanPackUI\VisualEditor\Http\Requests\Fonts\UploadFontRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FontLibraryController extends Controller
{
	/**
	 * Ceiling, in bytes, on a preview face body that may be written to the shared
	 * cache store. A genuine preview WOFF2 is tens of KB; anything past 2 MB is
	 * anomalous and is served without being cached so it cannot bloat the store.
	 */
	protected const PREVIEW_CACHE_MAX_BYTES = 2 * 1024 * 1024;

	public function __construct(
		protected FontInstaller $installer,
		protected FontSourceRegistry $registry,
	) {
	}

	/**
	 * List every installed font with its faces, plus the session's read-only
	 * signal so the modal knows whether to disable its mutating controls.
	 *
	 * @since 1.7.0
	 */
	public function index( Request $request ): JsonResponse
	{
		$fonts = Font::query()
			->with( 'faces' )
			->orderBy( 'family' )
			->get()
			->map( fn ( Font $font ): array => $this->serializeFont( $font ) )
			->all();

		$canManage = $this->canManage();

		return new JsonResponse( [
			'data'       => $fonts,
			'can_manage' => $canManage,
			'read_only'  => ! $canManage,
		] );
	}

	/**
	 * List the registered font sources for the modal's provider tabs.
	 *
	 * @since 1.7.0
	 */
	public function sources( Request $request ): JsonResponse
	{
		$sources = [];

		foreach ( $this->registry->all() as $key => $provider ) {
			$sources[] = [
				'key'              => $key,
				'label'            => $provider->label(),
				'is_self_hostable' => $provider->isSelfHostable(),
			];
		}

		$canManage = $this->canManage();

		return new JsonResponse( [
			'data'       => $sources,
			'can_manage' => $canManage,
			'read_only'  => ! $canManage,
		] );
	}

	/**
	 * Browse or search a single provider's catalog, one page at a time.
	 *
	 * @since 1.7.0
	 */
	public function catalog( Request $request, string $provider ): JsonResponse
	{
		$source = $this->registry->get( $provider );

		if ( null === $source ) {
			return new JsonResponse( [
				'error'   => 'unknown_provider',
				'message' => __( 'No font source is registered under that key.' ),
			], Response::HTTP_NOT_FOUND );
		}

		$query = trim( (string) $request->query( 'q', '' ) );
		$page  = max( 1, (int) $request->query( 'page', 1 ) );

		try {
			$result = $source->searchCatalog( $query, $page );
		} catch ( FontProviderException $e ) {
			return new JsonResponse( [
				'error'   => 'catalog_unavailable',
				'message' => $e->getMessage(),
			], Response::HTTP_BAD_GATEWAY );
		}

		$result = $this->withPreviewUrls( $provider, $source, $result );

		return new JsonResponse( [ 'data' => $result ] );
	}

	/**
	 * Decorate each catalog family with a same-origin `preview_url` pointing at
	 * {@see previewStylesheet()}, so the modal can render the sample in its real
	 * typeface. Only self-hostable providers get one — a preview needs
	 * {@see FontProvider::fetchFace()}, which non-self-hostable sources cannot
	 * serve. Providers therefore never build the URL themselves; the API
	 * response owns it, and any future provider inherits previews for free.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, mixed>  $result  The provider's `searchCatalog()` result.
	 *
	 * @return array<string, mixed> The result with `preview_url` on each family.
	 */
	protected function withPreviewUrls( string $provider, FontProvider $source, array $result ): array
	{
		if ( ! $source->isSelfHostable() || ! isset( $result['families'] ) || ! is_array( $result['families'] ) ) {
			return $result;
		}

		$result['families'] = array_map(
			function ( array $family ) use ( $provider ): array {
				if ( isset( $family['slug'] ) ) {
					$family['preview_url'] = route(
						'visual-editor.api.fonts.sources.preview',
						[ 'provider' => $provider, 'slug' => $family['slug'] ],
						false
					);
				}

				return $family;
			},
			$result['families']
		);

		return $result;
	}

	/**
	 * Serve a same-origin `@font-face` stylesheet for one catalog family,
	 * rendered in a single representative face (prefer 400/normal). The
	 * `src` points back at {@see previewFace()} so the browser fetches the font
	 * bytes from this app, never the provider CDN.
	 *
	 * @since 1.7.0
	 */
	public function previewStylesheet( string $provider, string $slug ): Response
	{
		$source = $this->registry->get( $provider );

		if ( null === $source || ! $source->isSelfHostable() ) {
			return response( '', Response::HTTP_NOT_FOUND );
		}

		try {
			$family = $source->getFamily( $slug );
		} catch ( FontProviderException ) {
			$family = null;
		}

		$face = null === $family ? null : $this->representativeFace( $family );

		if ( null === $face ) {
			return response( '', Response::HTTP_NOT_FOUND );
		}

		[ $weight, $style ] = $face;

		$faceUrl = route(
			'visual-editor.api.fonts.sources.preview-face',
			[ 'provider' => $provider, 'slug' => $slug, 'weight' => $weight, 'style' => $style ],
			false
		);

		$css = sprintf(
			'@font-face { font-family: "%s"; font-weight: %d; font-style: %s; font-display: swap; src: url("%s") format("woff2"); }',
			$this->cssString( (string) ( $family['family'] ?? $slug ) ),
			$weight,
			$style,
			$this->cssString( $faceUrl )
		);

		return response( $css, Response::HTTP_OK, [
			'Content-Type'  => 'text/css; charset=UTF-8',
			'Cache-Control' => 'private, max-age=86400',
		] );
	}

	/**
	 * Stream one catalog face's WOFF2 bytes through the app, caching the
	 * download so browsing a page of the catalog doesn't re-hit the provider
	 * CDN on every scroll.
	 *
	 * @since 1.7.0
	 */
	public function previewFace( string $provider, string $slug, string $weight, string $style ): Response
	{
		$source = $this->registry->get( $provider );

		if ( null === $source || ! $source->isSelfHostable() ) {
			return response( '', Response::HTTP_NOT_FOUND );
		}

		$cacheKey = sprintf( 've.font-preview.%s.%s.%s.%s', $provider, $slug, $weight, $style );
		$encoded  = Cache::get( $cacheKey );

		if ( ! is_string( $encoded ) ) {
			try {
				$bytes = $source->fetchFace( $slug, $weight, $style );
			} catch ( FontProviderException ) {
				return response( '', Response::HTTP_NOT_FOUND );
			}

			// Cache the base64 form, never the raw bytes: a DB or other text
			// cache store rejects binary WOFF2 as an invalid string, so the
			// download is stored ASCII-safe and decoded on the way out.
			$encoded = base64_encode( $bytes );

			// A real preview WOFF2 is tens of KB; refuse to cache anything past a
			// small ceiling so a hostile or misbehaving provider cannot bloat the
			// shared cache store with an oversized body. The face is still served,
			// just re-fetched next time rather than persisted.
			if ( strlen( $bytes ) <= self::PREVIEW_CACHE_MAX_BYTES ) {
				Cache::put( $cacheKey, $encoded, now()->addDay() );
			}
		}

		$bytes = base64_decode( $encoded, true );

		if ( false === $bytes ) {
			return response( '', Response::HTTP_NOT_FOUND );
		}

		return response( $bytes, Response::HTTP_OK, [
			'Content-Type'  => 'font/woff2',
			'Cache-Control' => 'private, max-age=86400',
		] );
	}

	/**
	 * Pick the face to preview a family with: 400/normal when the family has
	 * it, otherwise the first face the provider reports. Returns `[weight,
	 * style]`, or `null` when the family exposes no usable face.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, mixed>  $family  A {@see FontProvider::getFamily()} result.
	 *
	 * @return array{0: int, 1: string}|null
	 */
	protected function representativeFace( array $family ): ?array
	{
		$faces = isset( $family['faces'] ) && is_array( $family['faces'] ) ? $family['faces'] : [];

		if ( [] === $faces ) {
			return null;
		}

		foreach ( $faces as $face ) {
			if ( 400 === (int) ( $face['weight'] ?? 0 ) && 'normal' === ( $face['style'] ?? 'normal' ) ) {
				return [ 400, 'normal' ];
			}
		}

		$first = $faces[0];
		$style = 'italic' === ( $first['style'] ?? 'normal' ) ? 'italic' : 'normal';

		return [ (int) ( $first['weight'] ?? 400 ), $style ];
	}

	/**
	 * Escape a value for a double-quoted CSS string, matching the front-end's
	 * `font-preview` escaping: backslash first, then the quote, then any
	 * newline that would terminate the string and inject a bad-string token.
	 *
	 * @since 1.7.0
	 */
	protected function cssString( string $value ): string
	{
		return str_replace(
			[ '\\', '"', "\r", "\n", "\f" ],
			[ '\\\\', '\\"', ' ', ' ', ' ' ],
			$value
		);
	}

	/**
	 * Install a catalog font's selected faces from a registered provider.
	 *
	 * @since 1.7.0
	 */
	public function store( InstallFontRequest $request ): JsonResponse
	{
		if ( $denial = $this->denyUnlessCanManage() ) {
			return $denial;
		}

		$provider = (string) $request->input( 'provider' );

		if ( ! $this->registry->has( $provider ) ) {
			return new JsonResponse( [
				'error'   => 'unknown_provider',
				'message' => __( 'No font source is registered under that key.' ),
			], Response::HTTP_NOT_FOUND );
		}

		try {
			$font = $this->installer->install(
				$provider,
				(string) $request->input( 'slug' ),
				(array) $request->input( 'faces', [] ),
			);
		} catch ( FontInstallationException | FontProviderException | FontFileWriteException $e ) {
			return new JsonResponse( [
				'error'   => 'install_failed',
				'message' => $e->getMessage(),
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		return new JsonResponse( [ 'data' => $this->serializeFont( $font ) ], Response::HTTP_CREATED );
	}

	/**
	 * Install a custom-uploaded font, self-hosting each uploaded face.
	 *
	 * @since 1.7.0
	 */
	public function upload( UploadFontRequest $request ): JsonResponse
	{
		if ( $denial = $this->denyUnlessCanManage() ) {
			return $denial;
		}

		try {
			$font = $this->installer->installUpload(
				(string) $request->input( 'family' ),
				$this->uploadedFaces( $request ),
			);
		} catch ( FontInstallationException | FontFileWriteException $e ) {
			return new JsonResponse( [
				'error'   => 'upload_failed',
				'message' => $e->getMessage(),
			], Response::HTTP_UNPROCESSABLE_ENTITY );
		}

		return new JsonResponse( [ 'data' => $this->serializeFont( $font ) ], Response::HTTP_CREATED );
	}

	/**
	 * Uninstall several fonts at once, rebuilding the bundle a single time.
	 *
	 * @since 1.7.0
	 */
	public function bulkUninstall( Request $request ): JsonResponse
	{
		if ( $denial = $this->denyUnlessCanManage() ) {
			return $denial;
		}

		$validated = $request->validate( [
			'ids'   => [ 'required', 'array', 'min:1', 'max:100' ],
			'ids.*' => [ 'integer', 'distinct' ],
		] );

		$removed = $this->installer->bulkUninstall( $validated['ids'] );

		return new JsonResponse( [ 'data' => [ 'removed' => $removed ] ] );
	}

	/**
	 * Uninstall a single font: delete its rows, remove its files, and rebuild
	 * the bundle.
	 *
	 * @since 1.7.0
	 */
	public function destroy( Request $request, Font $font ): Response
	{
		if ( $denial = $this->denyUnlessCanManage() ) {
			return $denial;
		}

		$this->installer->uninstall( $font );

		return new JsonResponse( null, Response::HTTP_NO_CONTENT );
	}

	/**
	 * Whether the current user may perform mutating Font Library actions.
	 *
	 * @since 1.7.0
	 */
	protected function canManage(): bool
	{
		return Gate::allows( 'manage', Font::class );
	}

	/**
	 * Short-circuit a mutating action for a user without the `manage_fonts`
	 * capability, returning a shaped 403 that carries the read-only signal.
	 *
	 * @since 1.7.0
	 */
	protected function denyUnlessCanManage(): ?JsonResponse
	{
		if ( $this->canManage() ) {
			return null;
		}

		return new JsonResponse( [
			'error'     => 'forbidden',
			'message'   => __( 'You do not have permission to manage fonts.' ),
			'read_only' => true,
		], Response::HTTP_FORBIDDEN );
	}

	/**
	 * Read each uploaded face file's bytes into the shape
	 * {@see FontInstaller::installUpload()} consumes. The request's validation
	 * has already confirmed each `file` is a real, size-capped web-font upload.
	 *
	 * @since 1.7.0
	 *
	 * @return array<int, array{contents: string, weight?: int, style?: string}>
	 */
	protected function uploadedFaces( UploadFontRequest $request ): array
	{
		$faces = [];

		foreach ( (array) $request->input( 'faces', [] ) as $index => $face ) {
			$file = $request->file( "faces.{$index}.file" );

			if ( null === $file ) {
				continue;
			}

			$contents = @file_get_contents( $file->getRealPath() );

			if ( false === $contents ) {
				continue;
			}

			$entry = [ 'contents' => $contents ];

			if ( isset( $face['weight'] ) ) {
				$entry['weight'] = (int) $face['weight'];
			}

			if ( isset( $face['style'] ) ) {
				$entry['style'] = (string) $face['style'];
			}

			$faces[] = $entry;
		}

		return $faces;
	}

	/**
	 * Shape a font and its faces for the modal, resolving each face's public
	 * URL from the disk it was self-hosted on.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string, mixed>
	 */
	protected function serializeFont( Font $font ): array
	{
		return [
			'id'           => $font->id,
			'provider'     => $font->provider,
			'family'       => $font->family,
			'slug'         => $font->slug,
			'is_variable'  => $font->is_variable,
			'license'      => $font->license,
			'source_url'   => $font->source_url,
			'installed_at' => $font->installed_at?->toIso8601String(),
			'faces'        => $font->faces
				->map( fn ( FontFace $face ): array => [
					'id'     => $face->id,
					'weight' => $face->weight,
					'style'  => $face->style,
					'format' => $face->format,
					'axes'   => $face->axes,
					'url'    => $this->faceUrl( $face ),
				] )
				->all(),
		];
	}

	/**
	 * Resolve a face file's public URL, tolerating disks whose driver cannot
	 * build one.
	 *
	 * @since 1.7.0
	 */
	protected function faceUrl( FontFace $face ): ?string
	{
		try {
			return Storage::disk( $face->disk )->url( $face->path );
		} catch ( Throwable ) {
			return null;
		}
	}
}
