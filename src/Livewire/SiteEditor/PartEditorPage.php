<?php

/**
 * Part Editor Page Livewire Component.
 *
 * Full-page Livewire component that wraps the template part block editor.
 * Handles mounting (load existing part or create new), saving, and
 * routing for template part editing.
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
use ArtisanPackUI\VisualEditor\Models\TemplatePart;
use ArtisanPackUI\VisualEditor\Services\TemplatePartManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Livewire component for the template part editor page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @since      1.0.0
 */
#[Layout( 'visual-editor::layouts.site-editor' )]
class PartEditorPage extends Component implements SiteEditorPage
{
	/**
	 * The template part being edited, or null for create mode.
	 *
	 * @since 1.0.0
	 *
	 * @var TemplatePart|null
	 */
	public ?TemplatePart $part = null;

	/**
	 * The initial blocks for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $initialBlocks = [];

	/**
	 * The part settings for the sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	public array $partSettings = [];

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
	 * @param string|null $slug The template part slug for editing.
	 *
	 * @return void
	 */
	public function mount( ?string $slug = null ): void
	{
		$permission = (string) config( 'artisanpack.visual-editor.site_editor.gates.parts', 'visual-editor.manage-parts' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}

		if ( null !== $slug ) {
			$this->part = TemplatePart::where( 'slug', $slug )->first();

			if ( null === $this->part ) {
				// Check the in-memory registry for programmatically registered parts.
				$manager    = app( TemplatePartManager::class );
				$registered = $manager->resolve( $slug );

				if ( null === $registered ) {
					abort( 404 );
				}

				// Convert registered array to a new (unsaved) model for editing.
				$data = is_array( $registered ) ? $registered : $registered->toArray();

				$this->part         = new TemplatePart( $data );
				$this->isCreateMode = true;
			}

			if ( $this->part->is_locked ) {
				abort( 403, __( 'visual-editor::ve.part_editor_locked_message' ) );
			}

			$this->initialBlocks = $this->part->content ?? [];

			$createdBy = '';
			if ( $this->part->exists ) {
				try {
					$createdBy = $this->part->user?->name ?? '';
				} catch ( Throwable ) {
					// User model may not be resolvable in all environments.
				}
			}

			$this->partSettings = [
				'name'        => $this->part->name,
				'slug'        => $this->part->slug,
				'area'        => $this->part->area ?? 'custom',
				'description' => $this->part->description ?? '',
				'status'      => $this->part->status ?? 'active',
				'createdBy'   => $createdBy,
				'updatedAt'   => $this->part->updated_at?->diffForHumans() ?? '',
			];
		} else {
			$this->isCreateMode = true;
			$this->partSettings = [
				'name'        => '',
				'slug'        => '',
				'area'        => 'custom',
				'description' => '',
				'status'      => 'draft',
			];
		}
	}

	/**
	 * Save the template part.
	 *
	 * Handles both create and update modes. Dispatches a browser event
	 * with the result for the Alpine editor to react to.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks   The block content from the editor.
	 * @param array<string, mixed>             $settings The part settings from the sidebar.
	 *
	 * @return void
	 */
	#[On( 've-part-editor-save' )]
	public function save( array $blocks, array $settings ): void
	{
		$permission = (string) config( 'artisanpack.visual-editor.site_editor.gates.parts', 'visual-editor.manage-parts' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}

		$manager = app( TemplatePartManager::class );

		$data = [
			'name'        => $settings['name'] ?? __( 'visual-editor::ve.part_editor_untitled' ),
			'slug'        => $settings['slug'] ?? Str::slug( $settings['name'] ?? 'untitled-part' ),
			'area'        => $settings['area'] ?? 'custom',
			'description' => $settings['description'] ?? null,
			'status'      => $settings['status'] ?? 'draft',
			'content'     => $blocks,
			'is_custom'   => true,
			'user_id'     => auth()->id(),
		];

		if ( $this->isCreateMode ) {
			// Check if a DB record already exists with this slug
			// (e.g. a registered part being promoted to DB).
			$existing = TemplatePart::where( 'slug', $data['slug'] )->first();

			if ( null !== $existing ) {
				// DB record exists — update via manager for hooks and revisions.
				unset( $data['slug'] );
				$this->part = $manager->update( $existing, $data, auth()->id() );
			} else {
				// Create directly via Eloquent to bypass the manager's
				// slug-exists check which rejects slugs that match
				// in-memory registered parts. The DB record intentionally
				// overrides the registered part. Fire hook manually.
				$this->part = TemplatePart::create( $data );
				veDoAction( 'ap.visualEditor.templatePartCreated', $this->part );
			}

			$this->isCreateMode = false;

			$this->dispatch( 've-part-editor-saved', partId: $this->part->id, slug: $this->part->slug, created: null === $existing );
		} else {
			// Only include slug in the update if it actually changed.
			// TemplatePartManager::update() rejects slug changes that
			// conflict with registered parts, even when the DB part
			// already owns the slug.
			if ( isset( $data['slug'] ) && $data['slug'] === $this->part->slug ) {
				unset( $data['slug'] );
			}

			$manager->update( $this->part, $data, auth()->id() );

			$this->part->refresh();

			$this->dispatch( 've-part-editor-saved', partId: $this->part->id, slug: $this->part->slug, created: false );
		}
	}

	/**
	 * Render the part editor page.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'visual-editor::livewire.site-editor.part-editor', [
			'part'          => $this->part,
			'initialBlocks' => $this->initialBlocks,
			'partSettings'  => $this->partSettings,
			'isCreateMode'  => $this->isCreateMode,
		] );
	}
}
