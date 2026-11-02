<?php

/**
 * The Code block's `language-*` class (#1044).
 *
 * Mirrored by `codeLanguage.ts` in the React and Vue renderers, and
 * `scripts/verify-renderer-parity.mjs` keeps the lists the same.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class CodeLanguage
{
	/**
	 * Languages the block offers.
	 *
	 * @since 1.20.0
	 *
	 * @var array<int, string>
	 */
	public const LANGUAGES = [
		'plaintext',
		'html',
		'css',
		'javascript',
		'typescript',
		'php',
		'python',
		'ruby',
		'go',
		'rust',
		'json',
		'yaml',
		'bash',
		'sql',
		'markdown',
		'blade',
	];

	/**
	 * The class for an attribute value. Only known languages pass, because
	 * the value comes from saved block data and lands in a `class` attribute.
	 *
	 * @since 1.20.0
	 *
	 * @param  mixed  $language  The block's `language` attribute.
	 *
	 * @return string The class, or an empty string for no language.
	 */
	public static function className( mixed $language ): string
	{
		return in_array( $language, self::LANGUAGES, true ) ? 'language-' . $language : '';
	}
}
