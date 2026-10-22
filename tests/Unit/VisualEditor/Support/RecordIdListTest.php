<?php

declare( strict_types=1 );

/**
 * Unit tests for RecordIdList (#1003).
 *
 * @since 1.16.0
 */

use ArtisanPackUI\VisualEditor\Support\RecordIdList;
use Illuminate\Database\Eloquent\Model;

it( 'parses, de-duplicates and caps a comma-separated id list', function (): void {
	expect( RecordIdList::parse( '3, 1,3,../etc,,2', 2 ) )->toBe( [ '3', '1' ] );
} );

it( 'returns an empty list for non-string, non-array input', function (): void {
	expect( RecordIdList::parse( null, 10 ) )->toBe( [] );
} );

it( 'drops non-numeric ids for an integer key', function (): void {
	$model = new class extends Model {};

	expect( RecordIdList::parseForModel( '12,abc,7', $model, 10 ) )->toBe( [ '12', '7' ] );
} );

it( 'keeps non-numeric ids for a string key', function (): void {
	$model = new class extends Model {
		protected $keyType = 'string';
	};

	expect( RecordIdList::parseForModel( '12,abc,01h-x_y', $model, 10 ) )->toBe( [ '12', 'abc', '01h-x_y' ] );
} );
