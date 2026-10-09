<?php

/**
 * Binding source that reads a cms-framework custom field off the parent
 * model.
 *
 * cms-framework stores custom-field values as ordinary columns on the
 * content-type's table (the field's `key` is the column name), so the
 * resolution is a straight attribute lookup. The class intentionally
 * does not import cms-framework — it just reads attributes through the
 * model — so the visual-editor's standalone install keeps working when
 * cms-framework is not present.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Bindings\Sources;

use ArtisanPackUI\VisualEditor\Resources\ResourceResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use Illuminate\Database\Eloquent\Model;

class CustomFieldSource implements BlockBindingSource
{
	/**
	 * cms-framework's custom-field manager, referenced by name so the
	 * standalone install never autoloads it.
	 *
	 * @since 1.13.0
	 */
	protected const CUSTOM_FIELD_MANAGER = 'ArtisanPackUI\\CMSFramework\\Modules\\ContentTypes\\Managers\\CustomFieldManager';

	public function name(): string
	{
		return 'custom_field';
	}

	public function resolve( BindingContext $context, array $args ): mixed
	{
		$key = is_string( $args['key'] ?? null ) ? $args['key'] : '';

		if ( '' === $key ) {
			return null;
		}

		// Draft presence wins over the saved column: when the editor
		// hands us a draft entry — even null / empty-string — that's
		// the unsaved edit the inspector preview should reflect. The
		// empty-value policy in the resolver runs on the result, so a
		// user clearing the field gracefully degrades to fallback /
		// hide / placeholder.
		if ( $context->hasDraftValue( $key ) ) {
			return $context->draftValue( $key );
		}

		$model = $context->model();

		if ( ! $model instanceof Model ) {
			return null;
		}

		return $model->getAttribute( $key );
	}

	public function eagerLoadRelations( array $bindingArgs ): array
	{
		return [];
	}

	/**
	 * List the cms-framework custom fields registered for a resource.
	 *
	 * cms-framework stores content-type *slugs* in
	 * `custom_fields.content_types`, so the resource slug is the primary
	 * lookup key. The model's table name is also tried for hosts where
	 * slug and table differ only by convention (and for backward
	 * compatibility with installs where they're equal); results are
	 * de-duplicated by field key. The slug alone is enough, so a null
	 * `$modelClass` — or a shared generic model whose table is only set
	 * per record — still lists fields (#833).
	 *
	 * Fields are read through cms-framework's `CustomFieldManager` so
	 * filter-registered fields are included alongside persisted rows.
	 *
	 * @since 1.1.0
	 *
	 * @param  string                                                 $resource    The resource / content-type slug.
	 * @param  class-string<\Illuminate\Database\Eloquent\Model>|null  $modelClass  The model registered for the resource, if any.
	 *
	 * @return array<int, array{key: string, label: string, type: string}>
	 */
	public function availableFields( string $resource, ?string $modelClass = null ): array
	{
		$manager = self::CUSTOM_FIELD_MANAGER;

		if ( ! class_exists( $manager ) ) {
			return [];
		}

		$fields = [];

		foreach ( $this->contentTypeKeys( $resource, $modelClass ) as $contentType ) {
			try {
				$rows = app( $manager )->getFieldsForContentType( $contentType );
			} catch ( \Throwable $e ) {
				// A missing `custom_fields` table (cms-framework installed
				// but not migrated) must not 500 the inspector.
				report( $e );

				continue;
			}

			foreach ( $rows as $row ) {
				$key = (string) $row->getAttribute( 'key' );

				if ( '' === $key || isset( $fields[ $key ] ) ) {
					continue;
				}

				$fields[ $key ] = [
					'key'   => $key,
					'label' => (string) ( $row->getAttribute( 'name' ) ?: $key ),
					'type'  => $this->mapFieldType( $row->getAttribute( 'type' ) ),
				];
			}
		}

		return array_values( $fields );
	}

	/**
	 * Build the ordered list of cms-framework content-type keys to match
	 * against: the resource slug first, then the model's table name when
	 * it differs. The slug is only trusted when it's a registered
	 * resource, so the endpoint can't be used to enumerate the fields of
	 * content types that aren't exposed to the editor.
	 *
	 * @since 1.13.0
	 *
	 * @param  string       $resource    The resource / content-type slug.
	 * @param  string|null  $modelClass  The model registered for the resource, if any.
	 *
	 * @return array<int, string>
	 */
	protected function contentTypeKeys( string $resource, ?string $modelClass ): array
	{
		$keys = '' !== $resource && app( ResourceResolver::class )->has( $resource ) ? [ $resource ] : [];

		if ( null === $modelClass || ! class_exists( $modelClass ) ) {
			return $keys;
		}

		try {
			$instance = new $modelClass();
			$table    = $instance instanceof Model ? $instance->getTable() : '';
		} catch ( \Throwable ) {
			return $keys;
		}

		if ( '' !== $table && ! in_array( $table, $keys, true ) ) {
			$keys[] = $table;
		}

		return $keys;
	}

	/**
	 * Map a cms-framework FieldType enum to the binding-layer type label.
	 *
	 * @since 1.1.0
	 */
	protected function mapFieldType( mixed $fieldType ): string
	{
		$value = $fieldType instanceof \BackedEnum ? $fieldType->value : (string) $fieldType;

		return match ( $value ) {
			'number'             => 'number',
			'boolean', 'checkbox' => 'boolean',
			'date'               => 'date',
			'datetime'           => 'datetime',
			'url'                => 'url',
			'image', 'file'      => 'image',
			default              => 'string',
		};
	}
}
