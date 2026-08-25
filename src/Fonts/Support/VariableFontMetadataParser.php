<?php

/**
 * Variable-font axis metadata parser.
 *
 * Reads the OpenType `fvar` table — and the `name` table entries it points at —
 * out of raw font bytes and reduces them to the same `tag => {min, max, default}`
 * axis map the Font Library uses everywhere else (see
 * {@see \ArtisanPackUI\VisualEditor\Fonts\Providers\GoogleFontsProvider::normalizeAxes()}),
 * with each axis's human-readable `name` folded in when the `name` table
 * resolves it. The result drives {@see \ArtisanPackUI\VisualEditor\Fonts\Models\Font::$is_variable}
 * and lands, JSON-encoded, in `ve_font_faces.axes`.
 *
 * Three container formats are understood: a bare SFNT (`.ttf`/`.otf`), a WOFF
 * wrapper (each table zlib-compressed), and — only when an output-bounded Brotli
 * decoder is available (the incremental `brotli_uncompress_add()` API) — a WOFF2
 * wrapper. `fvar` and `name` are never WOFF2-transformed, so once the Brotli
 * table stream is inflated they read as ordinary tables. Every
 * failure mode — an unknown signature, a truncated table, a WOFF2 upload with no
 * Brotli support, or a plain static font with no `fvar` — resolves to the same
 * non-variable fallback rather than an exception, so a malformed or static
 * upload installs cleanly with no axes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Support;

use Throwable;

class VariableFontMetadataParser
{
	/**
	 * SFNT flavor signatures that introduce a bare (uncompressed) font: TrueType
	 * outlines (`0x00010000`), CFF outlines (`OTTO`), and the legacy Apple
	 * `true`/`typ1` flavors.
	 */
	protected const SFNT_SIGNATURES = [ "\x00\x01\x00\x00", 'OTTO', 'true', 'typ1' ];

	/**
	 * The only tables this parser reads. Every other table is skipped without
	 * being sliced or decompressed, which keeps a hostile upload from making the
	 * parser inflate tables it has no use for.
	 */
	protected const WANTED_TABLES = [ 'fvar', 'name' ];

	/**
	 * Upper bound on a single decompressed table (WOFF) and on the whole
	 * decompressed WOFF2 stream. `fvar`/`name` are a few kilobytes in practice;
	 * the cap defuses a decompression bomb whose directory claims a vast original
	 * length by refusing to inflate past it.
	 */
	protected const MAX_DECOMPRESSED_BYTES = 8 * 1024 * 1024;

	/**
	 * Upper bound on the axis count the `fvar` loop will honor. Real fonts carry
	 * a handful of axes; the cap bounds work on a table that claims tens of
	 * thousands.
	 */
	protected const MAX_AXES = 256;

	/**
	 * Parse the variable-axis metadata from a font file's raw bytes.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $contents  The raw font-file bytes (SFNT, WOFF, or WOFF2).
	 *
	 * @return array{is_variable: bool, axes: array<string, array{min: float, max: float, default: float, name: string}>}
	 *         `is_variable` is true only when a non-empty `fvar` was read; `axes`
	 *         is keyed by four-character axis tag.
	 */
	public function parse( string $contents ): array
	{
		try {
			$tables = $this->tables( $contents );

			if ( null === $tables || ! isset( $tables['fvar'] ) ) {
				return $this->nonVariable();
			}

			$names = isset( $tables['name'] ) ? $this->parseNameTable( $tables['name'] ) : [];
			$axes  = $this->parseFvar( $tables['fvar'], $names );

			if ( [] === $axes ) {
				return $this->nonVariable();
			}

			return [ 'is_variable' => true, 'axes' => $axes ];
		} catch ( Throwable ) {
			// A truncated or malformed file must never fail an upload — it simply
			// installs as a non-variable font with no axis metadata.
			return $this->nonVariable();
		}
	}

	/**
	 * Whether the parser can recover axis metadata from the given container.
	 *
	 * WOFF2 depends on the `brotli` extension; SFNT and WOFF are always
	 * supported. Used to explain a WOFF2 upload that silently produced no axes
	 * rather than to gate parsing — {@see parse()} degrades cleanly regardless.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $contents  The raw font-file bytes.
	 */
	public function isSupportedContainer( string $contents ): bool
	{
		$signature = substr( $contents, 0, 4 );

		if ( 'wOF2' === $signature ) {
			return $this->hasBoundedBrotliDecoder();
		}

		return 'wOFF' === $signature || in_array( $signature, self::SFNT_SIGNATURES, true );
	}

	/**
	 * The empty, non-variable result.
	 *
	 * @since 1.7.0
	 *
	 * @return array{is_variable: bool, axes: array<string, array{min: float, max: float, default: float, name: string}>}
	 */
	protected function nonVariable(): array
	{
		return [ 'is_variable' => false, 'axes' => [] ];
	}

	/**
	 * Decode the container into a `tag => raw table bytes` map, or null when the
	 * signature is not a supported font container.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $contents  The raw font-file bytes.
	 *
	 * @return array<string, string>|null
	 */
	protected function tables( string $contents ): ?array
	{
		$signature = substr( $contents, 0, 4 );

		if ( in_array( $signature, self::SFNT_SIGNATURES, true ) ) {
			return $this->sfntTables( $contents );
		}

		if ( 'wOFF' === $signature ) {
			return $this->woffTables( $contents );
		}

		if ( 'wOF2' === $signature ) {
			return $this->woff2Tables( $contents );
		}

		return null;
	}

	/**
	 * Slice the table directory of a bare SFNT into raw table bytes.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $sfnt  The SFNT bytes.
	 *
	 * @return array<string, string>
	 */
	protected function sfntTables( string $sfnt ): array
	{
		$numTables = $this->u16( $sfnt, 4 );
		$tables    = [];

		for ( $i = 0; $i < $numTables; $i++ ) {
			$record = 12 + ( $i * 16 );
			$tag    = substr( $sfnt, $record, 4 );

			if ( 4 !== strlen( $tag ) ) {
				break;
			}

			if ( ! in_array( $tag, self::WANTED_TABLES, true ) ) {
				continue;
			}

			$offset = $this->u32( $sfnt, $record + 8 );
			$length = $this->u32( $sfnt, $record + 12 );
			$bytes  = substr( $sfnt, $offset, $length );

			if ( strlen( $bytes ) === $length ) {
				$tables[ $tag ] = $bytes;
			}
		}

		return $tables;
	}

	/**
	 * Decode a WOFF wrapper's table directory into raw (inflated) table bytes.
	 *
	 * Each WOFF table is zlib-compressed when its stored length is shorter than
	 * its original length, and stored verbatim otherwise.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $woff  The WOFF bytes.
	 *
	 * @return array<string, string>
	 */
	protected function woffTables( string $woff ): array
	{
		$numTables = $this->u16( $woff, 12 );
		$tables    = [];

		for ( $i = 0; $i < $numTables; $i++ ) {
			$record = 44 + ( $i * 20 );
			$tag    = substr( $woff, $record, 4 );

			if ( 4 !== strlen( $tag ) ) {
				break;
			}

			if ( ! in_array( $tag, self::WANTED_TABLES, true ) ) {
				continue;
			}

			$offset     = $this->u32( $woff, $record + 4 );
			$compLength = $this->u32( $woff, $record + 8 );
			$origLength = $this->u32( $woff, $record + 12 );
			$stored     = substr( $woff, $offset, $compLength );

			if ( strlen( $stored ) !== $compLength ) {
				continue;
			}

			if ( $compLength < $origLength ) {
				// Cap the inflate so a table whose directory claims a huge
				// original length cannot expand into a decompression bomb.
				$inflated = @gzuncompress( $stored, self::MAX_DECOMPRESSED_BYTES );

				if ( false === $inflated ) {
					continue;
				}

				$stored = $inflated;
			}

			$tables[ $tag ] = $stored;
		}

		return $tables;
	}

	/**
	 * Decode a WOFF2 wrapper into raw table bytes, when the `brotli` extension is
	 * available to inflate the shared table stream.
	 *
	 * `fvar` and `name` are never among WOFF2's transformed tables, so their
	 * bytes in the inflated stream are ordinary SFNT tables. Returns an empty map
	 * — a clean non-variable fallback — when Brotli is unavailable or the stream
	 * cannot be inflated.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $woff2  The WOFF2 bytes.
	 *
	 * @return array<string, string>
	 */
	protected function woff2Tables( string $woff2 ): array
	{
		if ( ! $this->hasBoundedBrotliDecoder() ) {
			return [];
		}

		$numTables        = $this->u16( $woff2, 12 );
		$totalCompression = $this->u32( $woff2, 20 );
		$directory        = $this->woff2Directory( $woff2, $numTables );

		if ( null === $directory ) {
			return [];
		}

		[ $entries, $dataOffset ] = $directory;

		$stream = $this->brotliDecompressBounded( substr( $woff2, $dataOffset, $totalCompression ) );

		if ( null === $stream || '' === $stream ) {
			return [];
		}

		$tables = [];
		$cursor = 0;

		foreach ( $entries as $entry ) {
			$length = $entry['length'];

			if ( in_array( $entry['tag'], self::WANTED_TABLES, true ) ) {
				$bytes = substr( $stream, $cursor, $length );

				if ( strlen( $bytes ) === $length ) {
					$tables[ $entry['tag'] ] = $bytes;
				}
			}

			$cursor += $length;
		}

		return $tables;
	}

	/**
	 * Whether an output-bounded Brotli decoder is available.
	 *
	 * WOFF2's table stream is a single Brotli blob; the one-shot
	 * `brotli_uncompress()` would inflate it whole into memory before any size
	 * could be checked, so a decompression bomb is only safe to attempt through
	 * the incremental `brotli_uncompress_add()` API, which lets the inflate abort
	 * the moment it exceeds {@see MAX_DECOMPRESSED_BYTES}. When only the one-shot
	 * function exists, WOFF2 axis parsing is skipped (a clean non-variable
	 * fallback) rather than risking an unbounded allocation.
	 *
	 * @since 1.7.0
	 */
	protected function hasBoundedBrotliDecoder(): bool
	{
		return function_exists( 'brotli_uncompress_init' ) && function_exists( 'brotli_uncompress_add' );
	}

	/**
	 * Incrementally Brotli-decompress a blob, aborting as soon as the output
	 * exceeds {@see MAX_DECOMPRESSED_BYTES} so a hostile WOFF2 cannot inflate the
	 * table stream into an unbounded allocation. Returns null on any decode error,
	 * on overflow, or when no bounded decoder is available.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $data  The compressed Brotli stream.
	 */
	protected function brotliDecompressBounded( string $data ): ?string
	{
		if ( ! $this->hasBoundedBrotliDecoder() ) {
			return null;
		}

		$context = brotli_uncompress_init();

		if ( false === $context ) {
			return null;
		}

		$finish    = defined( 'BROTLI_FINISH' ) ? BROTLI_FINISH : 2;
		$process   = defined( 'BROTLI_PROCESS' ) ? BROTLI_PROCESS : 0;
		$chunkSize = 65536;
		$length    = strlen( $data );
		$output    = '';

		for ( $offset = 0; $offset < $length; $offset += $chunkSize ) {
			$isLast = ( $offset + $chunkSize ) >= $length;
			$piece  = @brotli_uncompress_add( $context, substr( $data, $offset, $chunkSize ), $isLast ? $finish : $process );

			if ( ! is_string( $piece ) ) {
				return null;
			}

			$output .= $piece;

			if ( strlen( $output ) > self::MAX_DECOMPRESSED_BYTES ) {
				return null;
			}
		}

		return $output;
	}

	/**
	 * Read a WOFF2 table directory into `{tag, length}` entries in stream order,
	 * returning the offset at which the compressed data block begins.
	 *
	 * Only untransformed table lengths are needed here: `fvar`/`name` are never
	 * transformed, and the running offset into the inflated stream is the sum of
	 * each table's stream length in directory order.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $woff2      The WOFF2 bytes.
	 * @param  int     $numTables  The table count from the header.
	 *
	 * @return array{0: array<int, array{tag: string, length: int}>, 1: int}|null
	 */
	protected function woff2Directory( string $woff2, int $numTables ): ?array
	{
		// Known-tag lookup, indexed by the low 6 bits of each entry's flags byte.
		$knownTags = self::WOFF2_KNOWN_TAGS;

		$entries = [];
		$cursor  = 48; // WOFF2 header is 48 bytes; the table directory follows.

		for ( $i = 0; $i < $numTables; $i++ ) {
			$flags = ord( $woff2[ $cursor ] ?? "\x00" );
			$cursor++;

			$tagIndex = $flags & 0x3F;

			if ( 0x3F === $tagIndex ) {
				$tag     = substr( $woff2, $cursor, 4 );
				$cursor += 4;
			} else {
				$tag = $knownTags[ $tagIndex ] ?? '????';
			}

			[ $origLength, $cursor ] = $this->readUIntBase128( $woff2, $cursor );

			if ( null === $origLength ) {
				return null;
			}

			$transform = ( $flags >> 6 ) & 0x03;
			$length    = $origLength;

			// `glyf`/`loca` carry a transformed length; any other table with a
			// non-null transform (transform version != 3 for glyf/loca, != 0
			// otherwise) also stores a transformLength. `fvar`/`name` are never
			// transformed, so the presence of a transform length only shifts the
			// cursor — the untransformed stream length still equals origLength for
			// the tables this parser reads.
			$hasTransformLength = ( ( 'glyf' === $tag || 'loca' === $tag ) && 3 !== $transform )
				|| ( 'glyf' !== $tag && 'loca' !== $tag && 0 !== $transform );

			if ( $hasTransformLength ) {
				[ $transformLength, $cursor ] = $this->readUIntBase128( $woff2, $cursor );

				if ( null === $transformLength ) {
					return null;
				}

				$length = $transformLength;
			}

			$entries[] = [ 'tag' => $tag, 'length' => $length ];
		}

		return [ $entries, $cursor ];
	}

	/**
	 * Read a WOFF2 `UIntBase128` variable-length integer, returning the value and
	 * the advanced cursor. Returns a null value on an over-long or truncated
	 * encoding.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $bytes   The WOFF2 bytes.
	 * @param  int     $cursor  The read position.
	 *
	 * @return array{0: int|null, 1: int}
	 */
	protected function readUIntBase128( string $bytes, int $cursor ): array
	{
		$value = 0;

		for ( $i = 0; $i < 5; $i++ ) {
			if ( ! isset( $bytes[ $cursor ] ) ) {
				return [ null, $cursor ];
			}

			$byte = ord( $bytes[ $cursor ] );
			$cursor++;

			// A leading zero on a multi-byte encoding, or overflow past 32 bits,
			// is a malformed integer.
			if ( 0 === $i && 0x80 === $byte ) {
				return [ null, $cursor ];
			}

			if ( ( $value & 0xFE000000 ) !== 0 ) {
				return [ null, $cursor ];
			}

			$value = ( $value << 7 ) | ( $byte & 0x7F );

			if ( 0 === ( $byte & 0x80 ) ) {
				return [ $value, $cursor ];
			}
		}

		return [ null, $cursor ];
	}

	/**
	 * Parse an `fvar` table into a `tag => {min, max, default, name}` axis map.
	 *
	 * @since 1.7.0
	 *
	 * @param  string                $fvar   The raw `fvar` table bytes.
	 * @param  array<int, string>    $names  Resolved `name` table entries, keyed by name id.
	 *
	 * @return array<string, array{min: float, max: float, default: float, name: string}>
	 */
	protected function parseFvar( string $fvar, array $names ): array
	{
		$axesOffset = $this->u16( $fvar, 4 );
		$axisCount  = $this->u16( $fvar, 8 );
		$axisSize   = $this->u16( $fvar, 10 );

		// A record must hold the fixed VariationAxisRecord fields (20 bytes); a
		// zero count or size means there is nothing usable to read.
		if ( 0 === $axisCount || $axisSize < 20 ) {
			return [];
		}

		$axisCount = min( $axisCount, self::MAX_AXES );
		$axes      = [];

		for ( $i = 0; $i < $axisCount; $i++ ) {
			$record = $axesOffset + ( $i * $axisSize );

			// Require the full 20-byte VariationAxisRecord before reading any
			// field: a record truncated mid-way would otherwise read zeros for its
			// missing bytes and yield a bogus axis instead of being rejected.
			if ( strlen( $fvar ) < $record + 20 ) {
				break;
			}

			$tag = rtrim( substr( $fvar, $record, 4 ), " \0" );

			if ( '' === $tag ) {
				break;
			}

			$min     = $this->fixed( $fvar, $record + 4 );
			$default = $this->fixed( $fvar, $record + 8 );
			$max     = $this->fixed( $fvar, $record + 12 );
			$nameId  = $this->u16( $fvar, $record + 18 );

			$axes[ $tag ] = [
				'min'     => $min,
				'max'     => $max,
				'default' => $default,
				'name'    => $names[ $nameId ] ?? $tag,
			];
		}

		return $axes;
	}

	/**
	 * Parse the `name` table into a `nameId => decoded string` map.
	 *
	 * Windows (platform 3) UTF-16BE records are preferred; a Macintosh
	 * (platform 1) record fills in only where no Windows record exists for that
	 * name id.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $name  The raw `name` table bytes.
	 *
	 * @return array<int, string>
	 */
	protected function parseNameTable( string $name ): array
	{
		$count         = $this->u16( $name, 2 );
		$storageOffset = $this->u16( $name, 4 );

		$names = [];
		$macFallback = [];

		for ( $i = 0; $i < $count; $i++ ) {
			$record = 6 + ( $i * 12 );

			if ( strlen( substr( $name, $record, 12 ) ) < 12 ) {
				break;
			}

			$platformId = $this->u16( $name, $record );
			$nameId     = $this->u16( $name, $record + 6 );
			$length     = $this->u16( $name, $record + 8 );
			$stringAt   = $storageOffset + $this->u16( $name, $record + 10 );
			$raw        = substr( $name, $stringAt, $length );

			if ( strlen( $raw ) !== $length || 0 === $length ) {
				continue;
			}

			if ( 3 === $platformId || 0 === $platformId ) {
				$decoded = $this->decodeUtf16Be( $raw );

				if ( '' !== $decoded ) {
					$names[ $nameId ] = $decoded;
				}

				continue;
			}

			if ( 1 === $platformId && ! isset( $macFallback[ $nameId ] ) ) {
				$macFallback[ $nameId ] = $this->decodeMacRoman( $raw );
			}
		}

		// Windows/Unicode names win; a Mac-only name id fills the gap.
		return $names + $macFallback;
	}

	/**
	 * Decode a UTF-16BE `name` string into UTF-8.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $raw  The raw UTF-16BE bytes.
	 */
	protected function decodeUtf16Be( string $raw ): string
	{
		$decoded = @mb_convert_encoding( $raw, 'UTF-8', 'UTF-16BE' );

		return is_string( $decoded ) ? trim( $decoded ) : '';
	}

	/**
	 * Decode a Macintosh Roman `name` string into UTF-8, treating it as ASCII —
	 * axis names are ASCII in practice.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $raw  The raw Mac Roman bytes.
	 */
	protected function decodeMacRoman( string $raw ): string
	{
		$decoded = @mb_convert_encoding( $raw, 'UTF-8', 'ASCII' );

		return is_string( $decoded ) ? trim( $decoded ) : trim( $raw );
	}

	/**
	 * Read a big-endian unsigned 16-bit integer at an offset.
	 *
	 * @since 1.7.0
	 */
	protected function u16( string $bytes, int $offset ): int
	{
		$slice = substr( $bytes, $offset, 2 );

		if ( 2 !== strlen( $slice ) ) {
			return 0;
		}

		return (int) ( unpack( 'n', $slice )[1] ?? 0 );
	}

	/**
	 * Read a big-endian unsigned 32-bit integer at an offset.
	 *
	 * @since 1.7.0
	 */
	protected function u32( string $bytes, int $offset ): int
	{
		$slice = substr( $bytes, $offset, 4 );

		if ( 4 !== strlen( $slice ) ) {
			return 0;
		}

		return (int) ( unpack( 'N', $slice )[1] ?? 0 );
	}

	/**
	 * Read a signed 16.16 fixed-point value at an offset and return it as a float.
	 *
	 * @since 1.7.0
	 */
	protected function fixed( string $bytes, int $offset ): float
	{
		$raw = $this->u32( $bytes, $offset );

		// Reinterpret the 32-bit value as signed before scaling by 1/65536.
		if ( $raw >= 0x80000000 ) {
			$raw -= 0x100000000;
		}

		return $raw / 65536;
	}

	/**
	 * WOFF2 known-table tags, indexed by the 6-bit tag index encoded in each
	 * directory entry's flags byte (WOFF2 spec, "Known Table Tags").
	 *
	 * @var array<int, string>
	 */
	protected const WOFF2_KNOWN_TAGS = [
		'cmap', 'head', 'hhea', 'hmtx', 'maxp', 'name', 'OS/2', 'post',
		'cvt ', 'fpgm', 'glyf', 'loca', 'prep', 'CFF ', 'VORG', 'EBDT',
		'EBLC', 'gasp', 'hdmx', 'kern', 'LTSH', 'PCLT', 'VDMX', 'vhea',
		'vmtx', 'BASE', 'GDEF', 'GPOS', 'GSUB', 'EBSC', 'JSTF', 'MATH',
		'CBDT', 'CBLC', 'COLR', 'CPAL', 'SVG ', 'sbix', 'acnt', 'avar',
		'bdat', 'bloc', 'bsln', 'cvar', 'fdsc', 'feat', 'fmtx', 'fvar',
		'gvar', 'hsty', 'just', 'lcar', 'mort', 'morx', 'opbd', 'prop',
		'trak', 'Zapf', 'Silf', 'Glat', 'Gloc', 'Feat', 'Sill',
	];
}
