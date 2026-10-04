<?php

/**
 * Heading hierarchy validation agent.
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
use ArtisanPackUI\VisualEditor\Ai\Support\BlockPayloadLimiter;
use ArtisanPackUI\VisualEditor\Support\BlockShape;
use JsonException;

/**
 * Reviews the document's heading structure and flags common problems
 * (skipped levels, multiple h1s, ambiguous headings) alongside concrete
 * suggested fixes. Also feeds the Accessibility package's document-audit
 * surface (see #614).
 *
 * ## Input
 *
 * ```
 * [
 *   'blocks' => array,   // required, ordered list of block payloads
 * ]
 * ```
 *
 * Non-heading blocks are still forwarded — the model uses surrounding
 * paragraphs and lists to judge whether a heading is "ambiguous" — but
 * only heading blocks are ever cited in the issues array.
 *
 * Payloads are bounded by {@see BlockPayloadLimiter} (#828): the tree
 * must stay under the configured block-count and depth limits, and
 * only ids, names, heading levels and plain text reach the model.
 * Oversized documents raise a `FeatureError` instead of being cut.
 *
 * ## Output schema
 *
 * ```
 * {
 *   issues: [
 *     { block_id: string, issue: string, suggestion: string }
 *   ]
 * }
 * ```
 *
 * A clean document returns `{ issues: [] }`. Callers should treat an
 * empty array as "no problems found" rather than as failure.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.3.0
 */
class HeadingHierarchyAgent extends ArtisanPackAgent
{
	/**
	 * Block names treated as headings. `artisanpack/heading` is what the
	 * editor registers since the I7 cutover (#415); the core and bare
	 * names cover theme markup and simplified payloads (#825).
	 *
	 * @since 1.12.1
	 *
	 * @var array<int, string>
	 */
	protected const HEADING_NAMES = [
		'heading',
		'core/heading',
		'artisanpack/heading',
	];

	/**
	 * Attributes that carry the text the audit reads. Everything else
	 * on a block (styles, media, editor state) is stripped before the
	 * tree is serialized (#828).
	 *
	 * @since 1.12.1
	 *
	 * @var array<int, string>
	 */
	protected const TEXT_ATTRIBUTES = [
		'content',
		'text',
		'value',
		'citation',
		'caption',
		'title',
		'label',
	];

	/**
	 * Characters of text kept for each non-heading block. Headings keep
	 * their full text; surrounding blocks only need enough to judge
	 * whether a heading describes what follows it.
	 *
	 * @since 1.12.1
	 *
	 * @var int
	 */
	protected const CONTEXT_TEXT_LENGTH = 280;

	/**
	 * {@inheritDoc}
	 */
	public string $featureKey = 'visual_editor.heading_hierarchy';

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
You review the document's heading structure and flag common accessibility problems.

Categories of issues to report:
- Skipped heading levels (e.g. an h2 directly followed by an h4 without an intermediate h3).
- Multiple h1 elements in a single document.
- Ambiguous or generic headings ("More info", "Details", "Section 2") — call these out with a suggested rewrite based on what the heading actually introduces.
- Empty or whitespace-only heading text.
- Heading order that doesn't reflect the document's information architecture (e.g. an h3 introducing a top-level section).

Rules:
- Only report REAL issues. Do not invent problems in a well-structured document.
- Every issue must cite the exact `block_id` from the input. Do NOT invent block ids.
- `issue` is a short label (e.g. "skipped level", "duplicate h1", "ambiguous heading").
- `suggestion` is a concrete fix the writer can apply — a specific rewrite, a level change, or "merge into previous section".
- Return `{ "issues": [] }` when the document is clean.

Return a JSON object with key `issues` (array of {block_id, issue, suggestion}).
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
			'required'             => [ 'issues' ],
			'properties'           => [
				'issues' => [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => [ 'block_id', 'issue', 'suggestion' ],
						'properties'           => [
							'block_id'   => [ 'type' => 'string' ],
							'issue'      => [ 'type' => 'string' ],
							'suggestion' => [ 'type' => 'string' ],
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

		// If there are literally no headings in the document, short-circuit
		// to an empty issues array. There's nothing for the model to check,
		// and running it would waste tokens on a guaranteed-empty output.
		if ( ! $this->containsHeading( $normalized['blocks'] ) ) {
			return [
				'output'        => [ 'issues' => [] ],
				'input_tokens'  => 0,
				'output_tokens' => 0,
			];
		}

		$prompter = app( AgentPrompter::class );

		$result = $prompter->prompt(
			credentials: $credentials,
			model: $model,
			instructions: $instructions,
			message: $this->buildMessage( $normalized ),
			outputSchema: $this->outputSchema(),
		);

		return [
			'output'        => $this->validateOutput( $result['output'], $this->collectBlockIds( $normalized['blocks'] ) ),
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
	 * @return array{ blocks: array<int, mixed> }
	 */
	protected function normalizeInput( mixed $input ): array
	{
		if ( ! is_array( $input ) ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				'input must be an array with a `blocks` key.',
			);
		}

		if ( ! isset( $input['blocks'] ) || ! is_array( $input['blocks'] ) ) {
			throw FeatureError::forFeature( $this->featureKey, '`blocks` must be an array.' );
		}

		BlockPayloadLimiter::assertTreeWithinLimits( $input['blocks'], $this->featureKey );

		return [ 'blocks' => array_values( $input['blocks'] ) ];
	}

	/**
	 * Whether any block in the tree is a heading. Recurses into
	 * `innerBlocks` so headings nested inside a `core/columns`,
	 * `core/group`, or `core/cover` are still seen (see review #1) —
	 * without this walk the agent short-circuits to `{issues: []}` on
	 * documents whose only headings live under a container block, which
	 * is the exact case the feature is meant to catch.
	 *
	 * Uses a permissive check so the editor's `getBlocks()` tree
	 * (`{ name: 'artisanpack/heading' }`), `parse_blocks()` output
	 * (`{ blockName: 'core/heading' }`) and simplified shapes
	 * (`{ type: 'heading' }`) all work (#825).
	 *
	 * @since 1.3.0
	 *
	 * @param  array<int, mixed>  $blocks  Normalized block list.
	 *
	 * @return bool
	 */
	protected function containsHeading( array $blocks ): bool
	{
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( in_array( BlockShape::readName( $block ), self::HEADING_NAMES, true ) ) {
				return true;
			}
			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && $this->containsHeading( $block['innerBlocks'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Collect all recognized block ids in the tree so we can drop any
	 * model-hallucinated ids from the output. Recurses through
	 * `innerBlocks` for the same reason `containsHeading()` does — the
	 * model can (and should) cite a nested heading's id, and validation
	 * must not throw it away as "unknown".
	 *
	 * @since 1.3.0
	 *
	 * @param  array<int, mixed>  $blocks  Normalized block list.
	 *
	 * @return array<int, string>
	 */
	protected function collectBlockIds( array $blocks ): array
	{
		$ids = [];
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( isset( $block['id'] ) && is_string( $block['id'] ) && '' !== $block['id'] ) {
				$ids[] = $block['id'];
			} elseif ( isset( $block['clientId'] ) && is_string( $block['clientId'] ) && '' !== $block['clientId'] ) {
				$ids[] = $block['clientId'];
			}
			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$ids = array_merge( $ids, $this->collectBlockIds( $block['innerBlocks'] ) );
			}
		}
		return $ids;
	}

	/**
	 * Build the structured message body for the prompter.
	 *
	 * @since 1.3.0
	 *
	 * @param  array{ blocks: array<int, mixed> }  $input  Normalized input.
	 *
	 * @return array<int, array<string, string>>
	 */
	protected function buildMessage( array $input ): array
	{
		try {
			$serialized = json_encode( $this->stripBlocks( $input['blocks'] ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} catch ( JsonException $e ) {
			throw FeatureError::forFeature(
				$this->featureKey,
				sprintf( 'blocks are not JSON-encodable: %s', $e->getMessage() ),
			);
		}

		BlockPayloadLimiter::assertSerializedWithinLimit( $serialized, $this->featureKey );

		return [
			[ 'type' => 'text', 'text' => sprintf( 'Document blocks: %s', $serialized ) ],
		];
	}

	/**
	 * Reduce the tree to what the audit needs: the block id, name,
	 * heading level, plain text and nested blocks. Drops editor-only
	 * keys (`originalContent`, `validationIssues`, `isValid`), styling
	 * and media attributes, and parse_blocks markup (#828).
	 *
	 * @since 1.12.1
	 *
	 * @param  array<int, mixed>  $blocks  Normalized block list.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function stripBlocks( array $blocks ): array
	{
		$stripped = [];

		foreach ( $blocks as $block ) {
			if ( is_array( $block ) ) {
				$stripped[] = $this->stripBlock( $block );
			}
		}

		return $stripped;
	}

	/**
	 * Strip a single block. See {@see self::stripBlocks()}.
	 *
	 * @since 1.12.1
	 *
	 * @param  array<string, mixed>  $block  Block payload.
	 *
	 * @return array<string, mixed>
	 */
	protected function stripBlock( array $block ): array
	{
		$name      = BlockShape::readName( $block );
		$isHeading = in_array( $name, self::HEADING_NAMES, true );
		$stripped  = [];

		foreach ( [ 'id', 'clientId' ] as $idKey ) {
			if ( is_string( $block[ $idKey ] ?? null ) && '' !== $block[ $idKey ] ) {
				$stripped[ $idKey ] = $block[ $idKey ];
			}
		}

		if ( '' !== $name ) {
			$stripped['name'] = $name;
		}

		[ , $attrs ] = BlockShape::readAttrs( $block );
		$kept        = [];

		if ( is_int( $attrs['level'] ?? null ) || ( is_string( $attrs['level'] ?? null ) && ctype_digit( $attrs['level'] ) ) ) {
			$kept['level'] = (int) $attrs['level'];
		}

		foreach ( self::TEXT_ATTRIBUTES as $key ) {
			if ( ! is_string( $attrs[ $key ] ?? null ) ) {
				continue;
			}
			$text = $this->plainText( $attrs[ $key ], $isHeading );
			if ( '' !== $text ) {
				$kept[ $key ] = $text;
			}
		}

		// parse_blocks() output keeps a heading's text in its markup,
		// not its attributes.
		if ( ! isset( $kept['content'] ) && is_string( $block['innerHTML'] ?? null ) ) {
			$text = $this->plainText( $block['innerHTML'], $isHeading );
			if ( '' !== $text ) {
				$kept['content'] = $text;
			}
		}

		if ( [] !== $kept ) {
			$stripped['attributes'] = $kept;
		}

		if ( is_array( $block['innerBlocks'] ?? null ) && [] !== $block['innerBlocks'] ) {
			$stripped['innerBlocks'] = $this->stripBlocks( $block['innerBlocks'] );
		}

		return $stripped;
	}

	/**
	 * Convert rich-text markup to collapsed plain text. Non-heading
	 * text is cut to {@see self::CONTEXT_TEXT_LENGTH} characters.
	 *
	 * @since 1.12.1
	 *
	 * @param  string  $html       Rich-text or block markup.
	 * @param  bool    $fullLength Whether to keep the whole text.
	 *
	 * @return string
	 */
	protected function plainText( string $html, bool $fullLength ): string
	{
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		if ( ! $fullLength && mb_strlen( $text ) > self::CONTEXT_TEXT_LENGTH ) {
			$text = rtrim( mb_substr( $text, 0, self::CONTEXT_TEXT_LENGTH ) ) . '…';
		}

		return $text;
	}

	/**
	 * Enforce output shape and drop issues referencing ids we didn't send.
	 * When no block ids were provided at all (input had bare block shapes),
	 * we keep the model's issues verbatim so callers still get feedback.
	 *
	 * @since 1.3.0
	 *
	 * @param  array<string, mixed>  $output    Decoded model output.
	 * @param  array<int, string>    $knownIds  Ids present in the input.
	 *
	 * @return array{ issues: array<int, array{ block_id: string, issue: string, suggestion: string }> }
	 */
	protected function validateOutput( array $output, array $knownIds ): array
	{
		$raw = isset( $output['issues'] ) && is_array( $output['issues'] )
			? $output['issues']
			: [];

		$known    = array_flip( $knownIds );
		$hasKnown = [] !== $known;
		$clean    = [];

		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$blockId    = isset( $entry['block_id'] ) ? trim( (string) $entry['block_id'] ) : '';
			$issue      = isset( $entry['issue'] ) ? trim( (string) $entry['issue'] ) : '';
			$suggestion = isset( $entry['suggestion'] ) ? trim( (string) $entry['suggestion'] ) : '';

			if ( '' === $blockId || '' === $issue || '' === $suggestion ) {
				continue;
			}

			if ( $hasKnown && ! isset( $known[ $blockId ] ) ) {
				continue;
			}

			$clean[] = [
				'block_id'   => $blockId,
				'issue'      => $issue,
				'suggestion' => $suggestion,
			];
		}

		return [ 'issues' => $clean ];
	}
}
