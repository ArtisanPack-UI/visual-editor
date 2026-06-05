<?php

/**
 * Template-part adapter — WP `wp_template_part` REST shape.
 *
 * Adds the `area` field to {@see TemplateAdapter}'s envelope and switches
 * the `type` discriminator from `wp_template` to `wp_template_part`.
 * Areas are the closed list `header | footer | sidebar | general` for V1
 * (plan 14 §8); validation lives upstream in {@see ResolvedTemplatePart}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Http\Resources\Adapters\CmsFramework\SiteEditor;

use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplate;
use ArtisanPackUI\VisualEditor\SiteEditor\Resolution\ResolvedTemplatePart;

class TemplatePartAdapter extends TemplateAdapter
{
	/**
	 * @since 1.0.0
	 *
	 * @return array{
	 *     id: int|string,
	 *     slug: string,
	 *     type: string,
	 *     source: string,
	 *     origin: string|null,
	 *     title: array{rendered: string, raw: string},
	 *     description: string,
	 *     content: array{raw: string, blocks: array<int, array<string, mixed>>},
	 *     status: string,
	 *     theme: string,
	 *     has_theme_file: bool,
	 *     is_custom: bool,
	 *     author: int|null,
	 *     modified: string|null,
	 *     area: string
	 * }
	 */
	public function toArray( ResolvedTemplate $template ): array
	{
		// Subclass narrows the input contract — see {@see ResolvedTemplatePart}
		// for the area enum the WP-shape `area` field surfaces.
		if ( ! $template instanceof ResolvedTemplatePart ) {
			throw new \InvalidArgumentException(
				'TemplatePartAdapter expects a ResolvedTemplatePart instance.'
			);
		}

		$envelope         = parent::toArray( $template );
		$envelope['type'] = 'wp_template_part';
		$envelope['area'] = $template->area;

		return $envelope;
	}
}
