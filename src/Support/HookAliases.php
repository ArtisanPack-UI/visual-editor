<?php

/**
 * Hook name deprecation aliases.
 *
 * Issue #664 renamed every visual-editor PHP hook (and the JS `ap.icons.*`
 * bridge hook) from kebab-case to camelCase for consistency with the
 * ArtisanPack UI ecosystem's naming convention. Existing host apps and
 * downstream packages continue to subscribe under the old names, so this
 * class installs alias entries via `deprecateHook()` (shipped by
 * `artisanpack-ui/hooks` ^1.2) that route old-name subscribers to fire on
 * the canonical (new) hook. A deprecation notice is logged the first time
 * an alias resolves per process.
 *
 * {@see \deprecateHook()} for the underlying implementation. Called once,
 * as early as possible, from {@see \ArtisanPackUI\VisualEditor\VisualEditorServiceProvider::boot()}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.5.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

class HookAliases
{
	/**
	 * The exhaustive old-name → new-name rename table for #664.
	 *
	 * The 13 visual-editor PHP hooks plus the `visual_editor.pre_publish_checks`
	 * cross-package hook (fired by visual-editor, subscribed by seo). The
	 * `ap.icons.register-icon-sets` alias is included defensively — the
	 * canonical alias belongs to the `artisanpack-ui/icons` package, but
	 * declaring it here is idempotent and protects hosts that install
	 * visual-editor before the icons package publishes its own alias
	 * registration.
	 *
	 * @var array<string, string>
	 */
	private const RENAMES = [
		'ap.visual-editor.resources'                    => 'ap.visualEditor.resources',
		'ap.visual-editor.templates'                    => 'ap.visualEditor.templates',
		'ap.visual-editor.template-parts'               => 'ap.visualEditor.templateParts',
		'ap.visual-editor.patterns'                     => 'ap.visualEditor.patterns',
		'ap.visual-editor.global-styles'                => 'ap.visualEditor.globalStyles',
		'ap.visual-editor.navigation'                   => 'ap.visualEditor.navigation',
		'ap.visual-editor.visibility.register-rules'    => 'ap.visualEditor.visibility.registerRules',
		'ap.visual-editor.visibility.evaluated'         => 'ap.visualEditor.visibility.evaluated',
		'ap.visual-editor.visibility.user-search-results' => 'ap.visualEditor.visibility.userSearchResults',
		'ap.visual-editor.rendered-block'               => 'ap.visualEditor.renderedBlock',
		'ap.visual-editor.breadcrumbs.trail'            => 'ap.visualEditor.breadcrumbs.trail',
		'ap.visual-editor.loginout.envelope'            => 'ap.visualEditor.loginout.envelope',
		'ap.visual-editor.loginout.login-form'          => 'ap.visualEditor.loginout.loginForm',
		'visual_editor.pre_publish_checks'              => 'ap.visualEditor.prePublishChecks',
		'ap.icons.register-icon-sets'                   => 'ap.icons.registerIconSets',
	];

	/**
	 * Register every deprecation alias in the rename table.
	 *
	 * Idempotent — safe to call multiple times. `deprecateHook()` is itself
	 * idempotent per (old, new) pair.
	 *
	 * @since 1.5.0
	 */
	public static function registerAll(): void
	{
		foreach ( self::RENAMES as $old => $new ) {
			deprecateHook( $old, $new );
		}
	}
}
