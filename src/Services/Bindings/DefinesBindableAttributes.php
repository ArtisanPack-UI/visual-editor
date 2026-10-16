<?php

/**
 * Per-model allowlist of attributes block bindings may read (#869).
 *
 * A model implementing this contract declares, in code, which of its
 * attributes and relations a content editor can surface through a block
 * binding. The list is only consulted when the host turns on
 * `bindings.enforce_allowlist`; see {@see BindingAttributeAllowlist}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings;

interface DefinesBindableAttributes
{
	/**
	 * Attribute and relation names a binding may read from this model.
	 * Return `[ '*' ]` to allow every key the model serializes.
	 *
	 * @since 1.15.0
	 *
	 * @return array<int, string>
	 */
	public function bindableAttributes(): array;
}
