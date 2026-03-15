<?php

/**
 * Table of Contents Block Livewire Component.
 *
 * Server-side rendering component for the Table of Contents
 * dynamic block. Receives heading data from the block tree
 * and generates a navigable outline.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\Blocks;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Livewire component for the Table of Contents dynamic block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\Blocks
 *
 * @since      2.0.0
 */
class TableOfContentsBlockComponent extends Component
{
	/**
	 * Heading levels to include (e.g. [2, 3]).
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, int>
	 */
	public array $headingLevels = [ 2, 3 ];

	/**
	 * List style (bulleted, numbered, or plain).
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $listStyle = 'numbered';

	/**
	 * Whether to nest headings hierarchically.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $hierarchical = true;

	/**
	 * Maximum nesting depth.
	 *
	 * @since 2.0.0
	 *
	 * @var int
	 */
	public int $maxDepth = 3;

	/**
	 * Title text for the table of contents.
	 *
	 * @since 2.0.0
	 *
	 * @var string
	 */
	public string $title = 'Table of Contents';

	/**
	 * Whether the TOC should be collapsible.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $collapsible = false;

	/**
	 * Whether to use smooth scroll for anchor links.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $smoothScroll = true;

	/**
	 * Headings data passed from the editor.
	 *
	 * Each heading is an array with keys: level, text, id.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, array{level: int, text: string, id: string}>
	 */
	public array $headings = [];

	/**
	 * Whether this is being rendered in the editor.
	 *
	 * @since 2.0.0
	 *
	 * @var bool
	 */
	public bool $isEditor = false;

	/**
	 * Render the component.
	 *
	 * @since 2.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		$filteredHeadings = $this->filterHeadings();
		$tocItems         = $this->hierarchical
			? $this->buildHierarchical( $filteredHeadings )
			: $this->buildFlat( $filteredHeadings );

		return view( 'visual-editor::livewire.blocks.table-of-contents-block', [
			'tocItems' => $tocItems,
		] );
	}

	/**
	 * Filter headings based on configured heading levels.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array{level: int, text: string, id: string}>
	 */
	protected function filterHeadings(): array
	{
		if ( empty( $this->headings ) ) {
			return $this->getSampleHeadings();
		}

		return array_values( array_filter(
			$this->headings,
			fn ( array $heading ) => in_array( $heading['level'], $this->headingLevels, true ),
		) );
	}

	/**
	 * Build a hierarchical structure from flat headings.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, array{level: int, text: string, id: string}> $headings The filtered headings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function buildHierarchical( array $headings ): array
	{
		if ( empty( $headings ) ) {
			return [];
		}

		$tree          = [];
		$stack         = [ &$tree ];
		$currentLevel  = $headings[0]['level'] ?? 2;
		$currentDepth  = 0;

		foreach ( $headings as $heading ) {
			$level = $heading['level'];

			$item = [
				'text'     => $heading['text'],
				'id'       => $heading['id'],
				'level'    => $level,
				'children' => [],
			];

			if ( $level > $currentLevel && $currentDepth < ( $this->maxDepth - 1 ) ) {
				$parent = &$stack[ count( $stack ) - 1 ];

				if ( ! empty( $parent ) ) {
					$lastIndex   = count( $parent ) - 1;
					$stack[]     = &$parent[ $lastIndex ]['children'];
					$currentDepth++;
				}
			} elseif ( $level < $currentLevel ) {
				$levelsUp = min( $currentDepth, $currentLevel - $level );

				for ( $i = 0; $i < $levelsUp; $i++ ) {
					array_pop( $stack );
					$currentDepth--;
				}
			}

			$currentLevel                            = $level;
			$stack[ count( $stack ) - 1 ][]          = $item;
		}

		return $tree;
	}

	/**
	 * Build a flat list from headings.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, array{level: int, text: string, id: string}> $headings The filtered headings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function buildFlat( array $headings ): array
	{
		return array_map( fn ( array $heading ) => [
			'text'     => $heading['text'],
			'id'       => $heading['id'],
			'level'    => $heading['level'],
			'children' => [],
		], $headings );
	}

	/**
	 * Generate sample headings for editor preview.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array{level: int, text: string, id: string}>
	 */
	protected function getSampleHeadings(): array
	{
		$samples = [];

		$sampleData = [
			[ 'level' => 2, 'text' => __( 'visual-editor::ve.sample_heading_introduction' ) ],
			[ 'level' => 3, 'text' => __( 'visual-editor::ve.sample_heading_overview' ) ],
			[ 'level' => 3, 'text' => __( 'visual-editor::ve.sample_heading_getting_started' ) ],
			[ 'level' => 2, 'text' => __( 'visual-editor::ve.sample_heading_features' ) ],
			[ 'level' => 3, 'text' => __( 'visual-editor::ve.sample_heading_configuration' ) ],
			[ 'level' => 2, 'text' => __( 'visual-editor::ve.sample_heading_conclusion' ) ],
		];

		foreach ( $sampleData as $data ) {
			if ( in_array( $data['level'], $this->headingLevels, true ) ) {
				$id        = str( $data['text'] )->slug()->toString();
				$samples[] = [
					'level' => $data['level'],
					'text'  => $data['text'],
					'id'    => $id,
				];
			}
		}

		return $samples;
	}
}
