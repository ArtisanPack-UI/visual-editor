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

use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use Illuminate\Database\Eloquent\Model;

class CustomFieldSource implements BlockBindingSource
{
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

		$draft = $context->draftValue( $key );

		if ( null !== $draft && '' !== $draft ) {
			return $draft;
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

	public function availableFields( string $resource, ?string $modelClass = null ): array
	{
		if ( null === $modelClass || ! class_exists( $modelClass ) ) {
			return [];
		}

		$cmsField = '\\ArtisanPackUI\\CMSFramework\\Modules\\ContentTypes\\Models\\CustomField';

		if ( ! class_exists( $cmsField ) ) {
			return [];
		}

		// Use the model's table name as the cms-framework "content type"
		// — that mirrors the convention `HasCustomFields::getCustomFieldsForType()`
		// uses internally so the picker shows whatever cms-framework will
		// actually resolve at render time.
		try {
			/** @var Model $instance */
			$instance = new $modelClass();

			if ( ! method_exists( $instance, 'getTable' ) ) {
				return [];
			}

			$table = $instance->getTable();
		} catch ( \Throwable $e ) {
			return [];
		}

		$rows = $cmsField::query()
			->orderBy( 'order' )
			->orderBy( 'id' )
			->get();

		$fields = [];

		foreach ( $rows as $row ) {
			$contentTypes = $row->getAttribute( 'content_types' );

			if ( ! is_array( $contentTypes ) || ! in_array( $table, $contentTypes, true ) ) {
				continue;
			}

			$key = (string) $row->getAttribute( 'key' );

			if ( '' === $key ) {
				continue;
			}

			$fields[] = [
				'key'   => $key,
				'label' => (string) ( $row->getAttribute( 'name' ) ?: $key ),
				'type'  => $this->mapFieldType( $row->getAttribute( 'type' ) ),
			];
		}

		return $fields;
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
