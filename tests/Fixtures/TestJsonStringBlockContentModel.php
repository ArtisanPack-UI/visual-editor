<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

/**
 * A block-content model whose column is read and written as a raw JSON
 * string rather than through the trait's `array` cast.
 *
 * @since 1.15.0
 */
class TestJsonStringBlockContentModel extends TestBlockContentModel
{
	/**
	 * Keep the column a string so the model-level guard sees JSON text.
	 *
	 * @since 1.15.0
	 *
	 * @var array<string, string>
	 */
	protected $casts = [ 'content' => 'string' ];
}
