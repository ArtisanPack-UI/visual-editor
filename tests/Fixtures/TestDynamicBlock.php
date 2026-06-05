<?php

declare( strict_types=1 );

namespace Tests\Fixtures;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

class TestDynamicBlock extends DynamicBlock
{
	public function name(): string
	{
		return 'tests/hello';
	}

	public function validateAttrs( array $attrs ): array
	{
		if ( isset( $attrs['greeting'] ) && ! is_string( $attrs['greeting'] ) ) {
			throw new InvalidArgumentException( 'greeting must be a string.' );
		}

		return [
			'greeting' => trim( (string) ( $attrs['greeting'] ?? 'Hello' ) ),
			'name'     => trim( (string) ( $attrs['name'] ?? 'World' ) ),
		];
	}

	public function render( array $attrs ): string
	{
		return sprintf(
			'<p>%s, %s!</p>',
			htmlspecialchars( $attrs['greeting'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			htmlspecialchars( $attrs['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8' )
		);
	}

	public function searchableText( array $attrs ): string
	{
		return ( $attrs['greeting'] ?? '' ) . ' ' . ( $attrs['name'] ?? '' );
	}

	public function authorize( ?Authenticatable $user, array $attrs ): bool
	{
		return 'secret' !== ( $attrs['name'] ?? null );
	}
}
