<?php

/**
 * Fake DynamicContentAccessor used to exercise
 * {@see ArtisanPackUI\VisualEditor\Services\Bindings\Sources\DynamicContentSource}
 * without pulling cms-framework's live registry in tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace Tests\Support;

class FakeDynamicContentAccessor
{
	/**
	 * @param  array<string, array<string, mixed>|list<array<string, mixed>>>  $data
	 */
	public function __construct( protected array $data = [] )
	{
	}

	public function singleton( string $typeSlug ): ?array
	{
		$value = $this->data[ $typeSlug ] ?? null;

		return is_array( $value ) && $this->isSingleton( $value ) ? $value : null;
	}

	public function collection( string $typeSlug ): array
	{
		$value = $this->data[ $typeSlug ] ?? [];

		return is_array( $value ) && ! $this->isSingleton( $value ) ? array_values( $value ) : [];
	}

	public function collectionItem( string $typeSlug, int $index ): ?array
	{
		$rows = $this->collection( $typeSlug );

		return $rows[ $index ] ?? null;
	}

	public function signatureFor( string $typeSlug ): string
	{
		return 'test:' . $typeSlug;
	}

	public function forget( ?string $typeSlug = null ): void
	{
		if ( null === $typeSlug ) {
			$this->data = [];
			return;
		}

		unset( $this->data[ $typeSlug ] );
	}

	/**
	 * @param  array<int|string, mixed>  $value
	 */
	protected function isSingleton( array $value ): bool
	{
		if ( [] === $value ) {
			return true;
		}

		return ! array_is_list( $value ) || ( isset( $value[0] ) && ! is_array( $value[0] ) );
	}
}
