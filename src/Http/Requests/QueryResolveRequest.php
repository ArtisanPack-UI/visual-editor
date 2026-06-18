<?php

/**
 * Validates the `POST /visual-editor/api/query/resolve` payload.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QueryResolveRequest extends FormRequest
{
	public function authorize(): bool
	{
		return true;
	}

	/**
	 * @return array<string, array<int, string>|string>
	 */
	public function rules(): array
	{
		return [
			'postType'              => [ 'sometimes', 'string', 'max:64' ],
			'perPage'               => [ 'sometimes', 'integer', 'min:1', 'max:100' ],
			// `pages: 0` is the upstream `core/query` "no cap" sentinel —
			// the QueryRuntime treats both 0 and missing identically.
			// Reject negatives but allow 0 so a freshly-inserted block
			// with default attributes can preview without configuring
			// the cap first.
			'pages'                 => [ 'sometimes', 'integer', 'min:0', 'max:1000' ],
			'offset'                => [ 'sometimes', 'integer', 'min:0', 'max:100000' ],
			'postIn'                => [ 'sometimes', 'array' ],
			'postIn.*'              => [ 'integer', 'min:1' ],
			'postNotIn'             => [ 'sometimes', 'array' ],
			'postNotIn.*'           => [ 'integer', 'min:1' ],
			'parents'               => [ 'sometimes', 'array' ],
			'parents.*'             => [ 'integer', 'min:1' ],
			'orderBy'               => [ 'sometimes', 'string', 'in:date,title,menu_order,random,relevance' ],
			'order'                 => [ 'sometimes', 'string', 'in:asc,desc' ],
			'author'                => [ 'sometimes', 'integer', 'min:1' ],
			'sticky'                => [ 'sometimes', 'boolean' ],
			'exclude'               => [ 'sometimes', 'array' ],
			'exclude.*'             => [ 'integer', 'min:1' ],
			'taxQuery'              => [ 'sometimes', 'array' ],
			'taxQuery.taxonomy'     => [ 'required_with:taxQuery', 'string', 'max:64' ],
			'taxQuery.terms'        => [ 'required_with:taxQuery', 'array' ],
			'taxQuery.terms.*'      => [ 'integer', 'min:1' ],
			// V1 only supports the `IN` operator — `NOT IN` / `AND` are
			// out of scope per #97's "Out of scope" list. Reject other
			// values at the request layer rather than silently dropping
			// the constraint at runtime, so the editor surfaces the
			// limitation explicitly.
			'taxQuery.operator'     => [ 'sometimes', 'string', 'in:IN' ],
			'search'                => [ 'sometimes', 'string', 'max:255' ],
			'status'                => [ 'sometimes', 'string', 'max:32' ],
			// Related-Posts editor preview (#601). When present, the
			// controller resolves the host post's primary taxonomy +
			// terms and runs the related-by-taxonomy query instead of
			// the literal `taxQuery` payload. Mutually exclusive with
			// `taxQuery` at the request level so the two paths don't
			// silently fight.
			'relatedTo'             => [ 'sometimes', 'integer', 'min:1', 'prohibits:taxQuery' ],
		];
	}
}
