<?php

/**
 * Minimal font-binary builder for Font Library tests.
 *
 * Assembles just enough of an OpenType font — a table directory pointing at a
 * real `fvar` and `name` table — for {@see \ArtisanPackUI\VisualEditor\Fonts\Support\VariableFontMetadataParser}
 * to exercise, in bare SFNT (`.ttf`/`.otf`) and WOFF wrappers. These are not
 * renderable fonts (they carry no `glyf`/`cmap`/`head`); they exist only to
 * carry variation metadata the parser reads, which keeps the fixtures tiny and
 * their expected axes obvious in the test.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace Tests\Fixtures\Fonts;

class FontBinaryFactory
{
	/**
	 * The default axis set used by the variable fixtures: a weight and an optical
	 * size axis with recognizable, easily-asserted ranges.
	 *
	 * @var array<string, array{min: float, default: float, max: float, name: string}>
	 */
	public const DEFAULT_AXES = [
		'wght' => [ 'min' => 100.0, 'default' => 400.0, 'max' => 900.0, 'name' => 'Weight' ],
		'opsz' => [ 'min' => 8.0, 'default' => 14.0, 'max' => 144.0, 'name' => 'Optical Size' ],
	];

	/**
	 * Build a bare TrueType-flavored variable SFNT carrying the given axes.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, array{min: float, default: float, max: float, name: string}>|null  $axes
	 */
	public static function variableTtf( ?array $axes = null ): string
	{
		return self::sfnt( "\x00\x01\x00\x00", self::variableTables( $axes ?? self::DEFAULT_AXES ) );
	}

	/**
	 * Build a bare CFF-flavored (`OTTO`) variable SFNT carrying the given axes.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, array{min: float, default: float, max: float, name: string}>|null  $axes
	 */
	public static function variableOtf( ?array $axes = null ): string
	{
		return self::sfnt( 'OTTO', self::variableTables( $axes ?? self::DEFAULT_AXES ) );
	}

	/**
	 * Build a WOFF-wrapped variable font carrying the given axes, with each table
	 * zlib-compressed so the parser's inflate path is exercised.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, array{min: float, default: float, max: float, name: string}>|null  $axes
	 */
	public static function variableWoff( ?array $axes = null ): string
	{
		return self::woff( "\x00\x01\x00\x00", self::variableTables( $axes ?? self::DEFAULT_AXES ) );
	}

	/**
	 * Build a bare static SFNT (a `name` table only, no `fvar`).
	 *
	 * @since 1.7.0
	 */
	public static function staticTtf(): string
	{
		return self::sfnt( "\x00\x01\x00\x00", [ 'name' => self::nameTable( [ 1 => 'Static Sans' ] ) ] );
	}

	/**
	 * Build a variable SFNT that carries only an `fvar` table — no `name` table —
	 * so its axis name ids do not resolve and the parser must fall back to the
	 * axis tag as the name.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, array{min: float, default: float, max: float, name: string}>|null  $axes
	 */
	public static function fvarOnlyTtf( ?array $axes = null ): string
	{
		$tables = self::variableTables( $axes ?? self::DEFAULT_AXES );

		return self::sfnt( "\x00\x01\x00\x00", [ 'fvar' => $tables['fvar'] ] );
	}

	/**
	 * Build a variable SFNT whose `fvar` header claims one axis but whose axis
	 * record is truncated (10 of the required 20 bytes), so the parser must
	 * reject the record and fall back to non-variable.
	 *
	 * @since 1.7.0
	 */
	public static function truncatedFvarTtf(): string
	{
		$header = pack( 'n', 1 )   // majorVersion
			. pack( 'n', 0 )       // minorVersion
			. pack( 'n', 16 )      // axesArrayOffset
			. pack( 'n', 2 )       // countSizePairs
			. pack( 'n', 1 )       // axisCount (claims one axis…)
			. pack( 'n', 20 )      // axisSize
			. pack( 'n', 0 )       // instanceCount
			. pack( 'n', 0 );      // instanceSize

		// …but only 10 bytes of the 20-byte record follow.
		$truncatedRecord = 'wght' . str_repeat( "\x00", 6 );

		return self::sfnt( "\x00\x01\x00\x00", [ 'fvar' => $header . $truncatedRecord ] );
	}

	/**
	 * The `fvar` + `name` table pair for a variable font over the given axes.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<string, array{min: float, default: float, max: float, name: string}>  $axes
	 *
	 * @return array<string, string>
	 */
	protected static function variableTables( array $axes ): array
	{
		$names   = [ 1 => 'Variable Sans' ];
		$fvarAxes = [];
		$nameId  = 256;

		foreach ( $axes as $tag => $axis ) {
			$names[ $nameId ] = $axis['name'];
			$fvarAxes[]       = [
				'tag'     => $tag,
				'min'     => (float) $axis['min'],
				'default' => (float) $axis['default'],
				'max'     => (float) $axis['max'],
				'nameId'  => $nameId,
			];
			$nameId++;
		}

		return [
			'fvar' => self::fvarTable( $fvarAxes ),
			'name' => self::nameTable( $names ),
		];
	}

	/**
	 * Pack an `fvar` table from a list of axis records.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, array{tag: string, min: float, default: float, max: float, nameId: int}>  $axes
	 */
	protected static function fvarTable( array $axes ): string
	{
		$axisCount  = count( $axes );
		$axesOffset = 16;
		$axisSize   = 20;

		$header = pack( 'n', 1 )            // majorVersion
			. pack( 'n', 0 )                // minorVersion
			. pack( 'n', $axesOffset )      // axesArrayOffset
			. pack( 'n', 2 )                // countSizePairs (reserved)
			. pack( 'n', $axisCount )       // axisCount
			. pack( 'n', $axisSize )        // axisSize
			. pack( 'n', 0 )                // instanceCount
			. pack( 'n', 0 );               // instanceSize

		$records = '';

		foreach ( $axes as $axis ) {
			$records .= str_pad( substr( $axis['tag'], 0, 4 ), 4, ' ' )
				. self::fixed( $axis['min'] )
				. self::fixed( $axis['default'] )
				. self::fixed( $axis['max'] )
				. pack( 'n', 0 )             // flags
				. pack( 'n', $axis['nameId'] );
		}

		return $header . $records;
	}

	/**
	 * Pack a `name` table with Windows (platform 3, UTF-16BE) records.
	 *
	 * @since 1.7.0
	 *
	 * @param  array<int, string>  $names  Name strings keyed by name id.
	 */
	protected static function nameTable( array $names ): string
	{
		$count         = count( $names );
		$recordsLength = $count * 12;
		$storageOffset = 6 + $recordsLength;

		$records = '';
		$storage = '';
		$cursor  = 0;

		foreach ( $names as $nameId => $value ) {
			$encoded = mb_convert_encoding( $value, 'UTF-16BE', 'UTF-8' );
			$length  = strlen( $encoded );

			$records .= pack( 'n', 3 )       // platformID (Windows)
				. pack( 'n', 1 )             // encodingID (Unicode BMP)
				. pack( 'n', 0x409 )         // languageID (en-US)
				. pack( 'n', $nameId )
				. pack( 'n', $length )
				. pack( 'n', $cursor );

			$storage .= $encoded;
			$cursor  += $length;
		}

		$header = pack( 'n', 0 )             // version
			. pack( 'n', $count )           // count
			. pack( 'n', $storageOffset );  // storageOffset

		return $header . $records . $storage;
	}

	/**
	 * Assemble a bare SFNT from a `tag => table bytes` map.
	 *
	 * @since 1.7.0
	 *
	 * @param  string                $signature  The 4-byte sfnt flavor.
	 * @param  array<string, string>  $tables     The tables to lay out.
	 */
	protected static function sfnt( string $signature, array $tables ): string
	{
		ksort( $tables );

		$numTables   = count( $tables );
		$offset      = 12 + ( $numTables * 16 );
		$records     = '';
		$body        = '';

		foreach ( $tables as $tag => $bytes ) {
			$length   = strlen( $bytes );
			$records .= str_pad( $tag, 4, ' ' )
				. pack( 'N', 0 )             // checksum (unchecked by the parser)
				. pack( 'N', $offset )
				. pack( 'N', $length );

			$padded  = $bytes . str_repeat( "\x00", ( 4 - ( $length % 4 ) ) % 4 );
			$body   .= $padded;
			$offset += strlen( $padded );
		}

		$searchRange   = self::searchRange( $numTables );
		$entrySelector = self::entrySelector( $numTables );
		$rangeShift    = ( $numTables * 16 ) - $searchRange;

		$header = $signature
			. pack( 'n', $numTables )
			. pack( 'n', $searchRange )
			. pack( 'n', $entrySelector )
			. pack( 'n', $rangeShift );

		return $header . $records . $body;
	}

	/**
	 * Wrap tables in a WOFF container, zlib-compressing each table.
	 *
	 * @since 1.7.0
	 *
	 * @param  string                $flavor  The original sfnt flavor.
	 * @param  array<string, string>  $tables  The tables to wrap.
	 */
	protected static function woff( string $flavor, array $tables ): string
	{
		ksort( $tables );

		$numTables = count( $tables );
		$offset    = 44 + ( $numTables * 20 );
		$records   = '';
		$body      = '';
		$sfntSize  = 12 + ( $numTables * 16 );

		foreach ( $tables as $bytes ) {
			$sfntSize += strlen( $bytes ) + ( ( 4 - ( strlen( $bytes ) % 4 ) ) % 4 );
		}

		foreach ( $tables as $tag => $bytes ) {
			$origLength  = strlen( $bytes );
			$compressed  = gzcompress( $bytes, 9 );
			$storeLength = strlen( $compressed );

			// WOFF stores a table verbatim when compression would not shrink it.
			if ( $storeLength >= $origLength ) {
				$compressed  = $bytes;
				$storeLength = $origLength;
			}

			$records .= str_pad( $tag, 4, ' ' )
				. pack( 'N', $offset )
				. pack( 'N', $storeLength )
				. pack( 'N', $origLength )
				. pack( 'N', 0 );           // origChecksum (unchecked by the parser)

			$padded  = $compressed . str_repeat( "\x00", ( 4 - ( $storeLength % 4 ) ) % 4 );
			$body   .= $padded;
			$offset += strlen( $padded );
		}

		$header = 'wOFF'
			. $flavor
			. pack( 'N', $offset )          // length
			. pack( 'n', $numTables )
			. pack( 'n', 0 )                // reserved
			. pack( 'N', $sfntSize )        // totalSfntSize
			. pack( 'n', 1 )                // majorVersion
			. pack( 'n', 0 )                // minorVersion
			. pack( 'N', 0 )                // metaOffset
			. pack( 'N', 0 )                // metaLength
			. pack( 'N', 0 )                // metaOrigLength
			. pack( 'N', 0 )                // privOffset
			. pack( 'N', 0 );               // privLength

		return $header . $records . $body;
	}

	/**
	 * Encode a float as a signed 16.16 fixed-point big-endian value.
	 *
	 * @since 1.7.0
	 */
	protected static function fixed( float $value ): string
	{
		$int = (int) round( $value * 65536 );

		if ( $int < 0 ) {
			$int += 0x100000000;
		}

		return pack( 'N', $int );
	}

	/**
	 * The `searchRange` header field: `(2 ** floor(log2(n))) * 16`.
	 *
	 * @since 1.7.0
	 */
	protected static function searchRange( int $numTables ): int
	{
		$power = 1;

		while ( ( $power * 2 ) <= $numTables ) {
			$power *= 2;
		}

		return $power * 16;
	}

	/**
	 * The `entrySelector` header field: `floor(log2(n))`.
	 *
	 * @since 1.7.0
	 */
	protected static function entrySelector( int $numTables ): int
	{
		$selector = 0;
		$power    = 1;

		while ( ( $power * 2 ) <= $numTables ) {
			$power *= 2;
			$selector++;
		}

		return $selector;
	}
}
