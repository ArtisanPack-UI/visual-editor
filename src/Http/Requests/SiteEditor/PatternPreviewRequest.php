<?php

/**
 * PatternPreviewRequest — #832.
 *
 * Validates `POST /visual-editor/api/patterns/preview`. The body names the
 * patterns to render by id or slug — never markup — so the endpoint can
 * only render content that already went through the pattern write paths.
 * The batch is capped so one request can't fan out into an unbounded
 * number of renders.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests\SiteEditor;

use Illuminate\Foundation\Http\FormRequest;

class PatternPreviewRequest extends FormRequest
{
	/**
	 * Most patterns one request may render.
	 *
	 * @since 1.13.0
	 */
	public const MAX_BATCH = 24;

	/**
	 * @since 1.13.0
	 */
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * @since 1.13.0
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function rules(): array
	{
		return [
			'patterns'   => [ 'required', 'array', 'list', 'min:1', 'max:' . self::MAX_BATCH ],
			'patterns.*' => [ 'required', 'string', 'max:255', 'distinct' ],
		];
	}
}
