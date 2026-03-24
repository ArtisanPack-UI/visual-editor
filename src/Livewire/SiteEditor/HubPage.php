<?php

/**
 * Site Editor Hub Page Livewire Component.
 *
 * The landing page for the site editor, displaying a card-based dashboard
 * with sections for Global Styles, Templates, Template Parts, and Patterns.
 * Cards are filterable via the `ve.hub.cards` filter hook.
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
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Livewire component for the Site Editor hub page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Livewire\SiteEditor
 *
 * @since      1.0.0
 */
#[Layout( 'visual-editor::layouts.site-editor' )]
class HubPage extends Component implements SiteEditorPage
{
	/**
	 * Authorize access when the component mounts.
	 *
	 * Checks the configured permission gate as a fallback
	 * in case route middleware is bypassed or removed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		$gates      = (array) config( 'artisanpack.visual-editor.site_editor.gates', [] );
		$permission = $gates['access']
			?? (string) config( 'artisanpack.visual-editor.site_editor.permission', 'visual-editor.access-site-editor' );

		if ( '' !== $permission && Gate::has( $permission ) ) {
			$this->authorize( $permission );
		}
	}

	/**
	 * Build the default hub cards.
	 *
	 * Each card has: slug, label, description, icon (inline SVG), url, and count.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getCards(): array
	{
		$prefix = (string) config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );

		$gates = (array) config( 'artisanpack.visual-editor.site_editor.gates', [] );

		$cards = [
			[
				'slug'        => 'global-styles',
				'label'       => __( 'visual-editor::ve.hub_global_styles' ),
				'description' => __( 'visual-editor::ve.hub_global_styles_description' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" /></svg>',
				'url'         => url( $prefix . '/global-styles' ),
				'count'       => null,
				'permission'  => $gates['styles'] ?? null,
			],
			[
				'slug'        => 'templates',
				'label'       => __( 'visual-editor::ve.hub_templates' ),
				'description' => __( 'visual-editor::ve.hub_templates_description' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>',
				'url'         => url( $prefix . '/templates' ),
				'count'       => $this->getTemplateCount(),
				'permission'  => $gates['templates'] ?? null,
			],
			[
				'slug'        => 'template-parts',
				'label'       => __( 'visual-editor::ve.hub_template_parts' ),
				'description' => __( 'visual-editor::ve.hub_template_parts_description' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 0 1-.657.643 48.491 48.491 0 0 1-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 0 1-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 0 0-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 0 1-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 0 0 .657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 0 1-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 0 0 5.427-.63 48.05 48.05 0 0 0 .582-4.717.532.532 0 0 0-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 0 0 .657-.663 48.422 48.422 0 0 0-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 0 1-.61-.58v0Z" /></svg>',
				'url'         => url( $prefix . '/parts' ),
				'count'       => $this->getTemplatePartCount(),
				'permission'  => $gates['parts'] ?? null,
			],
			[
				'slug'        => 'patterns',
				'label'       => __( 'visual-editor::ve.hub_patterns' ),
				'description' => __( 'visual-editor::ve.hub_patterns_description' ),
				'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" /></svg>',
				'url'         => url( $prefix . '/patterns' ),
				'count'       => null,
				'permission'  => $gates['patterns'] ?? null,
			],
		];

		$cards = veApplyFilters( 've.hub.cards', $cards );

		return $this->filterCardsByPermission( $cards );
	}

	/**
	 * Render the hub page.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'visual-editor::livewire.site-editor.hub', [
			'cards' => $this->getCards(),
		] );
	}

	/**
	 * Filter hub cards by the current user's permissions.
	 *
	 * Cards without a permission key or with a null permission are always shown.
	 * Cards with a permission are only shown if the user passes the gate check.
	 *
	 * When a gate is not explicitly registered (Gate::has() returns false),
	 * the card is shown for graceful degradation — this allows the hub to
	 * remain fully functional when no permission system is installed.
	 *
	 * Note: packages that use Gate::before() (e.g. Spatie Permission) should
	 * also register gates via Gate::define() or use the `ve.hub.cards` filter
	 * hook to customize card visibility.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $cards The hub cards to filter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function filterCardsByPermission( array $cards ): array
	{
		/** @var Authenticatable|null $user */
		$user = Auth::user();

		return array_values( array_filter( $cards, function ( array $card ) use ( $user ): bool {
			$permission = $card['permission'] ?? null;

			if ( null === $permission || '' === $permission ) {
				return true;
			}

			if ( ! Gate::has( $permission ) ) {
				return true;
			}

			if ( null === $user ) {
				return false;
			}

			return $user->can( $permission );
		} ) );
	}

	/**
	 * Get the count of registered templates.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function getTemplateCount(): int
	{
		if ( ! app()->bound( 'visual-editor.templates' ) ) {
			return 0;
		}

		return count( app( 'visual-editor.templates' )->all() );
	}

	/**
	 * Get the count of registered template parts.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function getTemplatePartCount(): int
	{
		if ( ! app()->bound( 'visual-editor.template-parts' ) ) {
			return 0;
		}

		return count( app( 'visual-editor.template-parts' )->all() );
	}
}
