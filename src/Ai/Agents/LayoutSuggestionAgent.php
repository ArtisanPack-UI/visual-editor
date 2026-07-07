<?php

/**
 * Layout / pattern suggestion agent.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Ai\Agents;

use ArtisanPackUI\Ai\Agents\ArtisanPackAgent;
use ArtisanPackUI\Ai\Contracts\AgentPrompter;
use ArtisanPackUI\Ai\Credentials\Credentials;
use ArtisanPackUI\Ai\Exceptions\FeatureError;
use JsonException;

/**
 * Given the contents of an in-progress section, ranks candidate patterns
 * from the caller's available pattern library that would fit that
 * section. Powers the "suggest a layout for this section" affordance
 * (see #611).
 *
 * ## Input
 *
 * ```
 * [
 *   'section_content'    => array,     // required, ordered list of block payloads inside the section
 *   'available_patterns' => string[],  // required, non-empty list of pattern slugs
 * ]
 * ```
 *
 * Pattern slugs are opaque to the agent; the caller is expected to
 * resolve slugs back to actual pattern definitions when applying the
 * suggestion. Passing rich pattern descriptions is supported by encoding
 * them into the slug (`"hero-two-column: side-by-side hero with image"`),
 * but the shipped format is deliberately slug-only to keep the prompt
 * small.
 *
 * ## Output schema
 *
 * ```
 * {
 *   matches: [
 *     { pattern_slug: string, confidence: float, rationale: string }
 *   ]
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */
class LayoutSuggestionAgent extends ArtisanPackAgent
{
	/**
	 * {@inheritDoc}
	 */
	public string $featureKey = 'visual_editor.suggest_layout';

	/**
	 * {@inheritDoc}
	 */
	public string $package = 'artisanpack-ui/visual-editor';

	/**
	 * {@inheritDoc}
	 */
	public string $defaultModel = 'claude-haiku-4-5';

	/**
	 * {@inheritDoc}
	 */
	public function instructions(): string
	{
		return <<<'PROMPT'
You rank candidate section-layout patterns for the supplied section content.

Requirements:
- Only return slugs that appear in `available_patterns`. Do NOT invent slugs.
- Return between 1 and 5 matches, ordered by descending confidence.
- `confidence` is a float in [0, 1]. Use 0.9+ when the content clearly fits (e.g. a heading + three parallel paragraphs → three-column feature grid); 0.5 for a reasonable fit; below 0.4 for weak matches — omit those entirely rather than including them.
- `rationale` is a single sentence naming the structural cue that drove the match (e.g. "three parallel paragraphs suggest a three-column grid").
- If NONE of the available patterns fits, return an empty `matches` array. Do not force a suggestion.

Return a JSON object with key `matches` (array of {pattern_slug, confidence, rationale}).
PROMPT;
	}

	/**
	 * {@inheritDoc}
	 */
	public function outputSchema(): array
	{
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'matches' ],
			'properties'           => [
				'matches' => [
					'type'     => 'array',
					'maxItems' => 5,
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'pattern_slug', 'confidence', 'rationale' ],
						'properties'           => [
							'pattern_slug' => [ 'type' => 'string' ],
							'confidence'   => [ 'type' => 'number', 'minimum' => 0, 'maximum' => 1 ],
							'rationale'    => [ 'type' => 'string' ],
						],
					],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( Credentials $credentials, string $model, string $instructions ): array
	{
		$normalized = $this->normalizeInput( $this->input() );

		$prompter = app( AgentPrompter::class );

		$result = $prompter->prompt(
			credentials: $credentials,
			model: $model,
			instructions: $instructions,
			message: $this->buildMessage( $normalized ),
			outputSchema: $this->outputSchema(),
		);

		return [
			'output'        => $this->validateOutput( $result['output'], $normalized['available_patterns'] ),
			'input_tokens'  => (int) ( $result['input_tokens'] ?? 0 ),
			'output_tokens' => (int) ( $result['output_tokens'] ?? 0 ),
		];
	}

	/**
	 * Validate and shape-check the raw agent input.
	 *
	 * @since 1.3.0
	 *
	 * @param  mixed  $input  Raw agent input.
	 *
	 * @return array{ section_content: array<int, mixed>, available_patterns: array<int, string> }
	 */
	protected function normalizeInput( mixed $input ): array
	{
		if ( ! is_array( $input ) ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				'input must be an array with `section_content` and `available_patterns` keys.',
			);
		}

		if ( ! isset( $input['section_content'] ) || ! is_array( $input['section_content'] ) ) {
			throw FeatureError::forFeature( $this->featureKey, '`section_content` must be an array.' );
		}

		if ( ! isset( $input['available_patterns'] ) || ! is_array( $input['available_patterns'] ) ) {
			throw FeatureError::forFeature( $this->featureKey, '`available_patterns` must be an array of pattern slugs.' );
		}

		$patterns = [];
		foreach ( $input['available_patterns'] as $slug ) {
			if ( is_string( $slug ) && '' !== trim( $slug ) ) {
				$patterns[] = trim( $slug );
			}
		}

		if ( [] === $patterns ) {
			throw FeatureError::forFeature( $this->featureKey, '`available_patterns` must contain at least one non-empty slug.' );
		}

		return [
			'section_content'    => array_values( $input['section_content'] ),
			'available_patterns' => array_values( array_unique( $patterns ) ),
		];
	}

	/**
	 * Build the structured message body for the prompter.
	 *
	 * @since 1.3.0
	 *
	 * @param  array{ section_content: array<int, mixed>, available_patterns: array<int, string> }  $input  Normalized input.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function buildMessage( array $input ): array
	{
		try {
			$content = json_encode( $input['section_content'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} catch ( JsonException $e ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				sprintf( 'section_content is not JSON-encodable: %s', $e->getMessage() ),
			);
		}

		return [
			[ 'type' => 'text', 'text' => sprintf( 'Section content: %s', $content ) ],
			[ 'type' => 'text', 'text' => 'Available patterns: ' . implode( ', ', $input['available_patterns'] ) ],
		];
	}

	/**
	 * Enforce the output schema shape and drop matches with unknown slugs.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed>  $output      Decoded model output.
	 * @param  array<int, string>    $whitelisted Known pattern slugs.
	 *
	 * @return array{ matches: array<int, array{ pattern_slug: string, confidence: float, rationale: string }> }
	 */
	protected function validateOutput( array $output, array $whitelisted ): array
	{
		$rawMatches = isset( $output['matches'] ) && is_array( $output['matches'] )
			? $output['matches']
			: [];

		$known = array_flip( $whitelisted );
		$clean = [];
		foreach ( $rawMatches as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$slug      = isset( $entry['pattern_slug'] ) ? trim( (string) $entry['pattern_slug'] ) : '';
			$rationale = isset( $entry['rationale'] ) ? trim( (string) $entry['rationale'] ) : '';
			$conf      = isset( $entry['confidence'] ) ? (float) $entry['confidence'] : 0.0;

			if ( '' === $slug || ! isset( $known[ $slug ] ) ) {
				continue;
			}

			if ( $conf < 0.0 ) {
				$conf = 0.0;
			} elseif ( $conf > 1.0 ) {
				$conf = 1.0;
			}

			$clean[] = [
				'pattern_slug' => $slug,
				'confidence'   => $conf,
				'rationale'    => $rationale,
			];

			if ( 5 === count( $clean ) ) {
				break;
			}
		}

		return [ 'matches' => $clean ];
	}
}
