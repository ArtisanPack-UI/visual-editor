<?php

/**
 * Upload icon set form request.
 *
 * Phase 6 (#557) of the Icon Block feature (#494). Validates the
 * multipart payload — zip + prefix + label — before the controller
 * hands the upload to {@see \ArtisanPackUI\VisualEditor\Services\Icon\IconSetUploader}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\Icon;

use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UploadIconSetRequest extends FormRequest
{
	/**
	 * Per-upload size ceiling in kilobytes. A full FA Pro family zips
	 * to ~25 MB; the cap sits comfortably above legitimate uploads but
	 * keeps a misuse (full-resolution illustrations being passed off as
	 * icons) from filling the host's temp directory.
	 */
	public const MAX_ZIP_KILOBYTES = 51_200;

	public function authorize(): bool
	{
		// Authorisation happens in the controller via the
		// SiteEditorAccessGate so the unauthorised path returns the
		// gate's own response (install instructions, redirect to login,
		// 503, etc.) instead of a flat 403.
		return true;
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		return [
			'prefix' => [
				'required',
				'string',
				'regex:' . UploadedIconSetRegistry::PREFIX_PATTERN,
			],
			'label'  => [
				'required',
				'string',
				'max:' . UploadedIconSetRegistry::LABEL_MAX_LENGTH,
			],
			'zip'    => [
				'required',
				'file',
				'mimetypes:application/zip,application/x-zip-compressed,multipart/x-zip',
				'max:' . self::MAX_ZIP_KILOBYTES,
			],
		];
	}

	/**
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'prefix.regex' => 'Prefix must be 2–32 chars of lowercase letters, digits, dashes or underscores.',
			'zip.mimetypes' => 'Uploaded file must be a zip archive.',
		];
	}
}
