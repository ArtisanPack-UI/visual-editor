<?php

/**
 * Covers the Code block's language class (#1044).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\CodeLanguage;

it( 'prints a class for each known language', function ( string $language ): void {
	expect( CodeLanguage::className( $language ) )->toBe( 'language-' . $language );
} )->with( CodeLanguage::LANGUAGES );

it( 'prints nothing for anything else', function ( mixed $language ): void {
	expect( CodeLanguage::className( $language ) )->toBe( '' );
} )->with( [
	'missing'    => [ null ],
	'empty'      => [ '' ],
	'unknown'    => [ 'cobol' ],
	'wrong case' => [ 'PHP' ],
	'hostile'    => [ 'php" onclick="alert(1)' ],
	'array'      => [ [ 'php' ] ],
	'number'     => [ 1 ],
] );
