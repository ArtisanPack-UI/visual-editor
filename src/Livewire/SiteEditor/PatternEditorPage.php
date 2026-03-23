<?php

/**
 * Pattern Editor Page Livewire Component.
 *
 * Full-page Livewire component that wraps the pattern block editor.
 * Handles mounting (load existing pattern or create new), saving, and
 * routing for pattern editing.
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

use ArtisanPackUI\VisualEditor\Models\Pattern;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Livewire component for the pattern editor page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @since      1.0.0
 */
#[Layout( 'visual-editor::layouts.site-editor' )]
class PatternEditorPage extends Component
{
	/**
	 * The pattern being edited, or null for create mode.
	 *
	 * @since 1.0.0
	 *
	 * @var Pattern|null
	 */
	public ?Pattern $pattern = null;

	/**
	 * The initial blocks for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $initialBlocks = [];

	/**
	 * The pattern settings for the sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	public array $patternSettings = [];

	/**
	 * Whether the editor is in create mode.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	public bool $isCreateMode = false;

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $slug The pattern slug for editing.
	 *
	 * @return void
	 */
	public function mount( ?string $slug = null ): void
	{
		$permission = (string) config( 'artisanpack.visual-editor.site_editor.gates.patterns', 'visual-editor.manage-patterns' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}

		if ( null !== $slug ) {
			$this->pattern = Pattern::where( 'slug', $slug )->first();

			if ( null === $this->pattern ) {
				abort( 404 );
			}

			$this->initialBlocks = $this->pattern->blocks ?? [];

			$createdBy = '';
			if ( $this->pattern->exists ) {
				try {
					$createdBy = $this->pattern->user?->name ?? '';
				} catch ( Throwable ) {
					// User model may not be resolvable in all environments.
				}
			}

			$this->patternSettings = [
				'name'        => $this->pattern->name,
				'slug'        => $this->pattern->slug,
				'category'    => $this->pattern->category ?? '',
				'description' => $this->pattern->description ?? '',
				'keywords'    => $this->pattern->keywords ?? '',
				'status'      => $this->pattern->status ?? 'active',
				'isSynced'    => (bool) $this->pattern->is_synced,
				'createdBy'   => $createdBy,
				'updatedAt'   => $this->pattern->updated_at?->diffForHumans() ?? '',
			];
		} else {
			$this->isCreateMode    = true;
			$this->patternSettings = [
				'name'        => '',
				'slug'        => '',
				'category'    => '',
				'description' => '',
				'keywords'    => '',
				'status'      => 'draft',
				'isSynced'    => false,
			];
		}
	}

	/**
	 * Save the pattern.
	 *
	 * Handles both create and update modes. Dispatches a browser event
	 * with the result for the Alpine editor to react to.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks   The block content from the editor.
	 * @param array<string, mixed>             $settings The pattern settings from the sidebar.
	 *
	 * @return void
	 */
	#[On( 've-pattern-editor-save' )]
	public function save( array $blocks, array $settings ): void
	{
		$permission = (string) config( 'artisanpack.visual-editor.site_editor.gates.patterns', 'visual-editor.manage-patterns' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}

		if ( null === auth()->id() ) {
			abort( 403 );
		}

		$name = ! empty( $settings['name'] ) ? $settings['name'] : __( 'visual-editor::ve.pattern_editor_untitled' );

		$data = [
			'name'        => $name,
			'slug'        => ! empty( $settings['slug'] ) ? $settings['slug'] : Str::slug( $name ),
			'blocks'      => $blocks,
			'category'    => $settings['category'] ?? null,
			'description' => $settings['description'] ?? null,
			'keywords'    => $settings['keywords'] ?? null,
			'status'      => $settings['status'] ?? 'draft',
			'is_synced'   => (bool) ( $settings['isSynced'] ?? false ),
			'user_id'     => auth()->id(),
		];

		if ( $this->isCreateMode ) {
			$existing = Pattern::where( 'slug', $data['slug'] )->first();

			if ( null !== $existing ) {
				unset( $data['slug'] );
				$existing->update( $data );
				$this->pattern = $existing;
			} else {
				$this->pattern = Pattern::create( $data );
				veDoAction( 'ap.visualEditor.patternCreated', $this->pattern );
			}

			$this->isCreateMode = false;

			$this->dispatch( 've-pattern-editor-saved', patternId: $this->pattern->id, slug: $this->pattern->slug, created: null === $existing );
		} else {
			// Only include slug in the update if it actually changed.
			if ( isset( $data['slug'] ) && $data['slug'] === $this->pattern->slug ) {
				unset( $data['slug'] );
			}

			$this->pattern->update( $data );
			$this->pattern->refresh();

			$this->dispatch( 've-pattern-editor-saved', patternId: $this->pattern->id, slug: $this->pattern->slug, created: false );
		}
	}

	/**
	 * Render the pattern editor page.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'visual-editor::livewire.site-editor.pattern-editor', [
			'pattern'         => $this->pattern,
			'initialBlocks'   => $this->initialBlocks,
			'patternSettings' => $this->patternSettings,
			'isCreateMode'    => $this->isCreateMode,
		] );
	}
}
