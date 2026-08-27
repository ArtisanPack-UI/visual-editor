<?php

/**
 * Upload font form request.
 *
 * Validates the multipart payload for a custom font upload — a family name and
 * one or more face files with optional weight/style — before the controller
 * hands it to {@see \ArtisanPackUI\VisualEditor\Fonts\Services\FontInstaller::installUpload()}.
 * Only web-font container extensions are accepted (`.woff2`, `.woff`, `.ttf`,
 * `.otf`), each capped at the configured size; the installer additionally
 * verifies each file's font signature before self-hosting it.
 *
 * Authorisation (the `manage_fonts` capability) is enforced in `authorize()`
 * via {@see AuthorizesFontManagement}, so an unauthorised caller is rejected
 * before the multipart body is materialised and validated.
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

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadFontRequest extends FormRequest
{
	use AuthorizesFontManagement;

	/**
	 * Default per-file size ceiling in kilobytes, used when
	 * `fonts.upload.max_kilobytes` is unset. A single WOFF2 face is tens of KB;
	 * 5 MB leaves ample room for a large TTF while keeping a misuse from filling
	 * the host's temp directory.
	 */
	public const DEFAULT_MAX_KILOBYTES = 5_120;

	/**
	 * Default accepted face-file extensions, used when
	 * `fonts.upload.extensions` is unset.
	 *
	 * @var array<int, string>
	 */
	public const DEFAULT_EXTENSIONS = [ 'woff2', 'woff', 'ttf', 'otf' ];

	/**
	 * @since 1.7.0
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		$maxKilobytes = (int) config(
			'artisanpack.visual-editor.fonts.upload.max_kilobytes',
			self::DEFAULT_MAX_KILOBYTES
		);

		return [
			'family'         => [ 'required', 'string', 'max:191' ],
			'faces'          => [ 'required', 'array', 'min:1', 'max:50' ],
			'faces.*.file'   => [
				'required',
				'file',
				'extensions:' . implode( ',', $this->allowedExtensions() ),
				'max:' . $maxKilobytes,
			],
			'faces.*.weight' => [ 'sometimes', 'integer', 'min:1', 'max:1000' ],
			'faces.*.style'  => [ 'sometimes', 'string', Rule::in( [ 'normal', 'italic' ] ) ],
		];
	}

	/**
	 * @since 1.7.0
	 *
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'faces.required'        => __( 'At least one font file must be uploaded.' ),
			'faces.*.file.required' => __( 'Each face must include a font file.' ),
			'faces.*.file.extensions' => __( 'Font files must be a .woff2, .woff, .ttf, or .otf file.' ),
			'faces.*.file.max'      => __( 'Each font file may not be larger than :max kilobytes.' ),
			'faces.*.style.in'      => __( 'Font style must be either normal or italic.' ),
		];
	}

	/**
	 * The accepted face-file extensions, normalized to lowercase and free of
	 * leading dots.
	 *
	 * @since 1.7.0
	 *
	 * @return array<int, string>
	 */
	public function allowedExtensions(): array
	{
		$configured = config( 'artisanpack.visual-editor.fonts.upload.extensions', self::DEFAULT_EXTENSIONS );

		if ( ! is_array( $configured ) || [] === $configured ) {
			$configured = self::DEFAULT_EXTENSIONS;
		}

		return array_values( array_map(
			static fn ( $extension ): string => ltrim( strtolower( (string) $extension ), '.' ),
			$configured
		) );
	}
}
