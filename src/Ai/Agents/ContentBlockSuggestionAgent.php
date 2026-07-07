<?php

/**
 * Content block suggestion agent.
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
 * Suggests what block type should come next given the blocks already
 * written and the caret position. Powers the inline "+ suggest"
 * affordance in the editor (see #610).
 *
 * ## Input
 *
 * ```
 * [
 *   'existing_blocks'  => array,          // required, ordered list of block payloads
 *   'cursor_position'  => int,            // required, 0-indexed insertion offset
 *   'document_type'    => string|null,    // optional, e.g. "blog-post", "landing-page"
 * ]
 * ```
 *
 * Each block payload is expected to expose at minimum a `type` field
 * (e.g. `core/paragraph`, `core/heading`). Any extra keys (`attrs`,
 * `content`, `id`) pass through to the model verbatim — the agent does
 * not read block bodies for privacy-sensitive fields, so callers should
 * strip anything that shouldn't leave the tenant boundary before invoking.
 *
 * ## Output schema
 *
 * ```
 * {
 *   suggestions: [
 *     { block_type: string, why: string, starter_content?: string }
 *   ]
 * }
 * ```
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */
class ContentBlockSuggestionAgent extends ArtisanPackAgent
{
	/**
	 * {@inheritDoc}
	 */
	public string $featureKey = 'visual_editor.suggest_next_block';

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
You suggest the next block a writer should add given the blocks already in the document and the caret's insertion position.

Requirements:
- Return between 1 and 4 suggestions, ordered by relevance (best first).
- `block_type` must be a Gutenberg-style slug (e.g. `core/paragraph`, `core/heading`, `core/list`, `core/quote`, `core/image`, `core/columns`). Prefer core blocks; do NOT invent block types that don't exist.
- `why` is a single sentence explaining why this block fits after the blocks already present.
- `starter_content` is optional. Include it ONLY when a short concrete example would help the writer (e.g. a heading placeholder like "How it works"). Never fabricate facts, quotes, statistics, or names — leave it out if you would need to guess.
- If `document_type` is provided, bias suggestions toward that shape (e.g. a landing page benefits from CTA + testimonial blocks; a blog post benefits from heading/subheading/list scaffolding).
- If the document already ends with a heading, a paragraph almost always comes next.
- If the document is empty or has only a title, the first suggestion should be a heading or paragraph to open the body — not a rich block like columns.

Return a JSON object with key `suggestions` (array of {block_type, why, starter_content?}).
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
			'required'             => [ 'suggestions' ],
			'properties'           => [
				'suggestions' => [
					'type'     => 'array',
					'minItems' => 1,
					'maxItems' => 4,
					'items'    => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'block_type', 'why' ],
						'properties'           => [
							'block_type'      => [ 'type' => 'string' ],
							'why'             => [ 'type' => 'string' ],
							'starter_content' => [ 'type' => 'string' ],
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
			'output'        => $this->validateOutput( $result['output'] ),
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
	 * @return array{ existing_blocks: array<int, mixed>, cursor_position: int, document_type: string|null }
	 */
	protected function normalizeInput( mixed $input ): array
	{
		if ( ! is_array( $input ) ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				'input must be an array with `existing_blocks` and `cursor_position` keys.',
			);
		}

		if ( ! isset( $input['existing_blocks'] ) || ! is_array( $input['existing_blocks'] ) ) {
			throw FeatureError::forFeature( $this->featureKey, '`existing_blocks` must be an array.' );
		}

		if ( ! array_key_exists( 'cursor_position', $input ) ) {
			throw FeatureError::forFeature( $this->featureKey, '`cursor_position` is required.' );
		}

		$cursor = filter_var( $input['cursor_position'], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ] );
		if ( false === $cursor ) {
			throw FeatureError::forFeature( $this->featureKey, '`cursor_position` must be a non-negative integer.' );
		}

		$documentType = null;
		if ( isset( $input['document_type'] ) && is_string( $input['document_type'] ) && '' !== trim( $input['document_type'] ) ) {
			$documentType = trim( $input['document_type'] );
		}

		return [
			'existing_blocks' => array_values( $input['existing_blocks'] ),
			'cursor_position' => $cursor,
			'document_type'   => $documentType,
		];
	}

	/**
	 * Build the structured message body for the prompter.
	 *
	 * @since 1.3.0
	 *
	 * @param  array{ existing_blocks: array<int, mixed>, cursor_position: int, document_type: string|null }  $input  Normalized input.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function buildMessage( array $input ): array
	{
		try {
			$serialized = json_encode( $input['existing_blocks'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} catch ( JsonException $e ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				sprintf( 'existing_blocks are not JSON-encodable: %s', $e->getMessage() ),
			);
		}

		$parts = [
			[ 'type' => 'text', 'text' => sprintf( 'Insertion position (0-indexed): %d', $input['cursor_position'] ) ],
			[ 'type' => 'text', 'text' => sprintf( 'Existing blocks: %s', $serialized ) ],
		];

		if ( null !== $input['document_type'] ) {
			$parts[] = [ 'type' => 'text', 'text' => sprintf( 'Document type: %s', $input['document_type'] ) ];
		}

		return $parts;
	}

	/**
	 * Enforce the output schema shape before returning.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed>  $output  Decoded model output.
	 *
	 * @return array{ suggestions: array<int, array{ block_type: string, why: string, starter_content?: string }> }
	 */
	protected function validateOutput( array $output ): array
	{
		$rawSuggestions = isset( $output['suggestions'] ) && is_array( $output['suggestions'] )
			? $output['suggestions']
			: [];

		$clean = [];
		foreach ( $rawSuggestions as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$blockType = isset( $entry['block_type'] ) ? trim( (string) $entry['block_type'] ) : '';
			$why       = isset( $entry['why'] ) ? trim( (string) $entry['why'] ) : '';

			if ( '' === $blockType || '' === $why ) {
				continue;
			}

			$suggestion = [
				'block_type' => $blockType,
				'why'        => $why,
			];

			if ( isset( $entry['starter_content'] ) && is_string( $entry['starter_content'] ) && '' !== trim( $entry['starter_content'] ) ) {
				$suggestion['starter_content'] = $entry['starter_content'];
			}

			$clean[] = $suggestion;

			if ( 4 === count( $clean ) ) {
				break;
			}
		}

		return [ 'suggestions' => $clean ];
	}
}
