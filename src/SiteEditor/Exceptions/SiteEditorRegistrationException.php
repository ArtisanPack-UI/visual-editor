<?php

/**
 * Site-editor registration exception.
 *
 * Thrown lazily — on the first {@see TemplateResolver::all()} (or sibling
 * resolver) call, never at boot — when an `ap.visual-editor.{templates,
 * template-parts,patterns,global-styles,navigation}` filter returns a
 * non-conforming shape or an entry is missing a required field.
 *
 * Lazy because `class_exists` registration sites in cms-framework register
 * filter callbacks at boot. Validating eagerly would couple visual-editor's
 * boot success to whatever shape every contributor happens to return that
 * day; deferring validation until the editor's first request keeps a
 * standalone visual-editor or cms-framework install booting cleanly even
 * when a misconfigured contributor returns garbage.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor\Exceptions;

use RuntimeException;

class SiteEditorRegistrationException extends RuntimeException
{
	/**
	 * The filter return value was not the expected map / array.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $filterName  The filter slug (e.g. `ap.visual-editor.templates`).
	 * @param  string  $expected    A short description of the expected shape.
	 * @param  string  $actual      A short description of the actual shape.
	 */
	public static function invalidFilterShape( string $filterName, string $expected, string $actual ): self
	{
		return new self( sprintf(
			'Filter "%s" returned an invalid shape. Expected %s, got %s.',
			$filterName,
			$expected,
			$actual,
		) );
	}

	/**
	 * An entry in a filter return map / object was missing a required field.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $filterName  The filter slug.
	 * @param  string  $entryKey    The map key (or `'(singleton)'` for global-styles).
	 * @param  string  $field       The missing required field.
	 */
	public static function missingRequiredField( string $filterName, string $entryKey, string $field ): self
	{
		return new self( sprintf(
			'Filter "%s" entry "%s" is missing required field "%s".',
			$filterName,
			$entryKey,
			$field,
		) );
	}

	/**
	 * A field was present but had the wrong type or value.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $filterName  The filter slug.
	 * @param  string  $entryKey    The map key.
	 * @param  string  $field       The field name.
	 * @param  string  $expected    A short description of the expected type / value.
	 */
	public static function invalidField( string $filterName, string $entryKey, string $field, string $expected ): self
	{
		return new self( sprintf(
			'Filter "%s" entry "%s" field "%s" is invalid: expected %s.',
			$filterName,
			$entryKey,
			$field,
			$expected,
		) );
	}
}
