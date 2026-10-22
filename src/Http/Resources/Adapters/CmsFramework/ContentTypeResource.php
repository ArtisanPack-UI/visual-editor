<?php

/**
 * ContentTypeResource — WP-shape envelope for a custom content type.
 *
 * Shapes entries of a registered custom content type (a host `package`
 * type, for example) into the same envelope {@see PostResource} and
 * {@see PageResource} emit, with `type` set to the content type's slug
 * instead of `post`. Used by the query-resolve endpoint so the site
 * editor's template preview (#993) and query loops over custom types
 * receive correctly-typed records.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.3
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework;

class ContentTypeResource extends WpEntityResource
{
	/**
	 * @since 1.15.3
	 *
	 * @param  mixed   $resource  The model being shaped.
	 * @param  string  $postType  The content type's slug, emitted as `type`.
	 */
	public function __construct( mixed $resource, protected string $postType )
	{
		parent::__construct( $resource );
	}

	/**
	 * The content type's slug.
	 *
	 * @since 1.15.3
	 */
	protected function type(): string
	{
		return $this->postType;
	}
}
