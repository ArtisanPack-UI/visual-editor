<?php

/**
 * Global Styles Page Livewire Component.
 *
 * A dedicated admin page for managing site-wide global styles (colors,
 * typography, spacing) with a split-view layout: editors on the left
 * and a live CSS preview on the right.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Livewire\SiteEditor;

use ArtisanPackUI\VisualEditor\Contracts\SiteEditorPage;
use ArtisanPackUI\VisualEditor\Models\Pattern;
use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use ArtisanPackUI\VisualEditor\Rendering\BlockRenderer;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Livewire component for the Global Styles admin page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @since      1.0.0
 */
#[Layout( 'visual-editor::layouts.site-editor' )]
class GlobalStylesPage extends Component implements SiteEditorPage
{
	/**
	 * The current palette data for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $palette = [];

	/**
	 * The current typography data for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $typography = [];

	/**
	 * The current spacing data for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $spacing = [];

	/**
	 * Whether the revision history panel is open.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $showHistory = false;

	/**
	 * The revision history records.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public array $revisions = [];

	/**
	 * Authorize access and load data when the component mounts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$permission = (string) config( 'artisanpack.visual-editor.site_editor.permission', 'visual-editor.access-site-editor' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}

		$this->loadStyles();
	}

	/**
	 * Save the global styles from the Alpine store.
	 *
	 * Dispatched from the frontend when the user clicks Save.
	 *
	 * @since 1.0.0
	 *
	 * @param array $palette    The palette data from the editor.
	 * @param array $typography The typography data from the editor.
	 * @param array $spacing    The spacing data from the editor.
	 *
	 * @return void
	 */
	public function save( array $palette, array $typography, array $spacing ): void
	{
		Validator::make(
			[ 'palette' => $palette, 'typography' => $typography, 'spacing' => $spacing ],
			[
				'palette'    => 'present|array',
				'typography' => 'present|array',
				'spacing'    => 'present|array',
			],
		)->validate();

		$repository = $this->repository();

		$repository->save( [
			'palette'    => $palette,
			'typography' => $typography,
			'spacing'    => $spacing,
		], auth()->id() );

		$this->palette    = $palette;
		$this->typography = $typography;
		$this->spacing    = $spacing;

		$this->dispatch( 've-global-styles-saved' );
	}

	/**
	 * Discard unsaved style changes and revert to the last saved state.
	 *
	 * Dispatches a reset event with the currently persisted data so the
	 * Alpine store reverts to the saved values without modifying the database.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function discardChanges(): void
	{
		$this->loadStyles();

		$this->dispatch( 've-global-styles-reset', [
			'palette'    => $this->palette,
			'typography' => $this->typography,
			'spacing'    => $this->spacing,
		] );
	}

	/**
	 * Reset global styles to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function resetToDefaults(): void
	{
		$repository = $this->repository();

		$record = $repository->resetToDefaults( auth()->id() );

		$this->palette    = $record->palette ?? [];
		$this->typography = $record->typography ?? [];
		$this->spacing    = $record->spacing ?? [];

		$this->dispatch( 've-global-styles-reset', [
			'palette'    => $this->palette,
			'typography' => $this->typography,
			'spacing'    => $this->spacing,
		] );
	}

	/**
	 * Toggle the revision history panel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function toggleHistory(): void
	{
		$this->showHistory = ! $this->showHistory;

		if ( $this->showHistory ) {
			$this->loadRevisions();
		}
	}

	/**
	 * Restore a specific revision.
	 *
	 * @since 1.0.0
	 *
	 * @param int $revisionId The revision ID to restore.
	 *
	 * @return void
	 */
	public function restoreRevision( int $revisionId ): void
	{
		$repository = $this->repository();

		$record = $repository->restoreRevision( $revisionId, auth()->id() );

		if ( null === $record ) {
			return;
		}

		$this->palette    = $record->palette ?? [];
		$this->typography = $record->typography ?? [];
		$this->spacing    = $record->spacing ?? [];

		$this->loadRevisions();

		$this->dispatch( 've-global-styles-reset', [
			'palette'    => $this->palette,
			'typography' => $this->typography,
			'spacing'    => $this->spacing,
		] );
	}

	/**
	 * Render the global styles page.
	 *
	 * Loads active template parts and patterns, renders their block
	 * content via BlockRenderer, and passes the results to the view
	 * for display in the live preview panel.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'visual-editor::livewire.site-editor.global-styles-page', [
			'palette'         => $this->palette,
			'typography'      => $this->typography,
			'spacing'         => $this->spacing,
			'previewParts'    => $this->loadPreviewParts(),
			'previewPatterns' => $this->loadPreviewPatterns(),
		] );
	}

	/**
	 * Resolve the global styles repository.
	 *
	 * @since 1.0.0
	 *
	 * @return GlobalStylesRepository
	 */
	protected function repository(): GlobalStylesRepository
	{
		return app( GlobalStylesRepository::class );
	}

	/**
	 * Load the current global styles from the repository.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function loadStyles(): void
	{
		$repository = $this->repository();

		$this->palette    = $repository->getPalette();
		$this->typography = $repository->getTypography();
		$this->spacing    = $repository->getSpacing();
	}

	/**
	 * Load revision history.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function loadRevisions(): void
	{
		$repository      = app( GlobalStylesRepository::class );
		$this->revisions = $repository->getRevisions( 20 )
			->map( function ( $revision ) {
				return [
					'id'         => $revision->id,
					'created_at' => $revision->created_at?->diffForHumans(),
					'user_id'    => $revision->user_id,
				];
			} )
			->toArray();
	}

	/**
	 * Load active template parts and render their content for preview.
	 *
	 * Groups template parts by area (header, footer, sidebar, custom)
	 * and renders each part's block content to HTML via BlockRenderer.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, array{name: string, slug: string, area: string, html: string}>>
	 */
	protected function loadPreviewParts(): array
	{
		$grouped = [];

		try {
			$renderer = app( BlockRenderer::class );
			$parts    = TemplatePart::active()->get();

			foreach ( $parts as $part ) {
				$blocks = $part->content ?? [];

				if ( [] === $blocks ) {
					continue;
				}

				$html = $renderer->render( $blocks );

				if ( '' === trim( $html ) ) {
					continue;
				}

				$area = $part->area ?? 'custom';

				$grouped[ $area ][] = [
					'name' => $part->name,
					'slug' => $part->slug,
					'area' => $area,
					'html' => $html,
				];
			}
		} catch ( Throwable $e ) {
			Log::warning( 'GlobalStylesPage: failed to load preview parts', [
				'error' => $e->getMessage(),
			] );
		}

		return $grouped;
	}

	/**
	 * Load active patterns and render their content for preview.
	 *
	 * Returns an array of rendered pattern previews grouped by category.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, array{name: string, slug: string, category: string|null, html: string}>>
	 */
	protected function loadPreviewPatterns(): array
	{
		$grouped = [];

		try {
			$renderer = app( BlockRenderer::class );
			$patterns = Pattern::where( 'status', 'active' )->get();

			foreach ( $patterns as $pattern ) {
				$blocks = $pattern->blocks ?? [];

				if ( [] === $blocks ) {
					continue;
				}

				$html = $renderer->render( $blocks );

				if ( '' === trim( $html ) ) {
					continue;
				}

				$category = $pattern->category ?? 'uncategorized';

				$grouped[ $category ][] = [
					'name'     => $pattern->name,
					'slug'     => $pattern->slug,
					'category' => $pattern->category,
					'html'     => $html,
				];
			}
		} catch ( Throwable $e ) {
			Log::warning( 'GlobalStylesPage: failed to load preview patterns', [
				'error' => $e->getMessage(),
			] );
		}

		return $grouped;
	}
}
