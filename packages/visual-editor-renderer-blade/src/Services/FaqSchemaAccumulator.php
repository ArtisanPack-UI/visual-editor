<?php

/**
 * Per-render accumulator for FAQ structured data (#1034).
 *
 * Google expects one `FAQPage` per page. The `artisanpack/faq` block and the
 * Accordions block's FAQ toggle each used to print their own JSON-LD script,
 * so a page with two of them carried two `FAQPage` nodes. They now push their
 * questions here while rendering, and {@see \ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer::render()}
 * prints one merged script once the tree has rendered.
 *
 * Scoped, like the CSS accumulators, so a long-lived worker (Octane / queue)
 * never carries questions across requests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

class FaqSchemaAccumulator
{
	/**
	 * Queued `Question` nodes. Keyed by their encoded form so a question
	 * rendered twice (a reused pattern) appears once, in first-push order.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $questions = [];

	/**
	 * Queue `Question` nodes for the merged script.
	 *
	 * @since 1.19.0
	 *
	 * @param  array<int, array<string, mixed>>  $questions  Schema.org `Question` nodes.
	 */
	public function push( array $questions ): void
	{
		foreach ( $questions as $question ) {
			$key = json_encode( $question, JSON_UNESCAPED_UNICODE );

			// Encoding fails on invalid UTF-8, which would break the
			// merged payload for every other question too.
			if ( false === $key ) {
				continue;
			}

			$this->questions[ $key ] ??= $question;
		}
	}

	/**
	 * Return the merged `FAQPage` script and clear the queue. Returns an
	 * empty string when no questions were pushed.
	 *
	 * @since 1.19.0
	 */
	public function flush(): string
	{
		if ( [] === $this->questions ) {
			return '';
		}

		$encoded = json_encode(
			[
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => array_values( $this->questions ),
			],
			JSON_UNESCAPED_UNICODE | JSON_HEX_TAG,
		);

		$this->questions = [];

		return false === $encoded ? '' : '<script type="application/ld+json">' . $encoded . '</script>';
	}
}
