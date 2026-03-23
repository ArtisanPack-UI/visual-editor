<?php

/**
 * Template Editor Page Livewire Component.
 *
 * Full-page Livewire component that wraps the template block editor.
 * Handles mounting (load existing template or create new), saving, and
 * routing for template editing.
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
use ArtisanPackUI\VisualEditor\Models\Template;
use ArtisanPackUI\VisualEditor\Services\TemplateManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Livewire component for the template editor page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @since      1.0.0
 */
#[Layout( 'visual-editor::layouts.site-editor' )]
class TemplateEditorPage extends Component implements SiteEditorPage
{
	/**
	 * The template being edited, or null for create mode.
	 *
	 * @since 1.0.0
	 *
	 * @var Template|null
	 */
	public ?Template $template = null;

	/**
	 * The initial blocks for the editor.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $initialBlocks = [];

	/**
	 * The template settings for the sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	public array $templateSettings = [];

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
	 * @param string|null $slug The template slug for editing.
	 *
	 * @return void
	 */
	public function mount( ?string $slug = null ): void
	{
		$permission = (string) config( 'artisanpack.visual-editor.site_editor.gates.templates', 'visual-editor.manage-templates' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}

		if ( null !== $slug ) {
			$manager  = app( TemplateManager::class );
			$resolved = $manager->resolve( $slug );

			if ( null === $resolved ) {
				abort( 404 );
			}

			if ( $resolved instanceof Template ) {
				$this->template = $resolved;
			} else {
				// Convert registered array to a new (unsaved) model for editing.
				$this->template     = new Template( $resolved );
				$this->isCreateMode = true;
			}

			if ( $this->template->is_locked ) {
				abort( 403, __( 'visual-editor::ve.template_editor_locked_message' ) );
			}

			$this->initialBlocks = $this->template->content ?? [];

			$createdBy = '';
			if ( $this->template->exists ) {
				try {
					$createdBy = $this->template->user?->name ?? '';
				} catch ( Throwable ) {
					// User model may not be resolvable in all environments.
				}
			}

			$this->templateSettings = [
				'name'        => $this->template->name,
				'slug'        => $this->template->slug,
				'type'        => $this->template->type ?? 'page',
				'contentType' => $this->template->for_content_type ?? '',
				'description' => $this->template->description ?? '',
				'status'      => $this->template->status ?? 'active',
				'createdBy'   => $createdBy,
				'updatedAt'   => $this->template->updated_at?->diffForHumans() ?? '',
			];
		} else {
			$this->isCreateMode     = true;
			$this->templateSettings = [
				'name'        => '',
				'slug'        => '',
				'type'        => 'page',
				'contentType' => '',
				'description' => '',
				'status'      => 'draft',
			];
		}
	}

	/**
	 * Save the template.
	 *
	 * Handles both create and update modes. Dispatches a browser event
	 * with the result for the Alpine editor to react to.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks   The block content from the editor.
	 * @param array<string, mixed>             $settings The template settings from the sidebar.
	 *
	 * @return void
	 */
	#[On( 've-template-editor-save' )]
	public function save( array $blocks, array $settings ): void
	{
		if ( ! auth()->check() ) {
			abort( 403 );
		}

		$permission = (string) config( 'artisanpack.visual-editor.site_editor.gates.templates', 'visual-editor.manage-templates' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}

		$name = ! empty( $settings['name'] ) ? $settings['name'] : __( 'visual-editor::ve.template_editor_untitled' );
		$slug = ! empty( $settings['slug'] ) ? $settings['slug'] : Str::slug( $name );

		$data = [
			'name'             => $name,
			'slug'             => $slug,
			'content'          => $blocks,
			'type'             => $settings['type'] ?? 'page',
			'for_content_type' => $settings['contentType'] ?? null,
			'description'      => $settings['description'] ?? null,
			'status'           => $settings['status'] ?? 'draft',
			'is_custom'        => true,
		];

		if ( $this->isCreateMode ) {
			$data['user_id'] = auth()->id();

			// Generate a unique slug if one already exists.
			$maxTries = 10;
			$tries    = 0;

			while ( Template::where( 'slug', $data['slug'] )->exists() ) {
				$data['slug'] = $slug . '-' . Str::random( 6 );
				$tries++;

				if ( $tries >= $maxTries ) {
					$data['slug'] = $slug . '-' . Str::uuid()->toString();

					break;
				}
			}

			$this->template = Template::create( $data );
			veDoAction( 'ap.visualEditor.templateCreated', $this->template );

			$this->isCreateMode = false;

			$this->dispatch( 've-template-editor-saved', templateId: $this->template->id, slug: $this->template->slug, created: true );
		} else {
			// Only include slug in the update if it actually changed.
			if ( isset( $data['slug'] ) && $data['slug'] === $this->template->slug ) {
				unset( $data['slug'] );
			}

			$this->template->update( $data );
			$this->template->refresh();

			$this->dispatch( 've-template-editor-saved', templateId: $this->template->id, slug: $this->template->slug, created: false );
		}
	}

	/**
	 * Render the template editor page.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'visual-editor::livewire.site-editor.template-editor', [
			'template'         => $this->template,
			'initialBlocks'    => $this->initialBlocks,
			'templateSettings' => $this->templateSettings,
			'isCreateMode'     => $this->isCreateMode,
		] );
	}
}
