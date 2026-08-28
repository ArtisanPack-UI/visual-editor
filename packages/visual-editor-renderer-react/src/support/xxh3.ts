/**
 * XXH3-64 (single-shot, 64-bit, seed 0, default secret) — React renderer.
 *
 * A byte-for-byte port of PHP's `hash( 'xxh3', $string )`, which the Blade
 * renderer uses to mint the `ve-w-<hash>` column-width scope class in
 * `blocks/core/column.blade.php` (#487). Porting the hash here lets the
 * React renderer produce the *same* class token for the same width map, so
 * a column renders an identical width through either renderer and the token
 * comes back under the #704 markup-parity check.
 *
 * Inputs of any length are supported: the short-key-map JSON this hashes
 * (`{"base":…,"sm":…,…}`) normally stays well under 240 bytes, but a column
 * carrying several long `calc(…)` breakpoint overrides can exceed it, so the
 * >240-byte accumulator/scramble long-hash path is ported too and matches
 * PHP for every input.
 *
 * The mirror lives at the matching path in the Vue renderer; the shared
 * vitest suite pins both against reference digests.
 *
 * @package @artisanpack-ui/visual-editor-renderer-react
 * @since 1.7.0
 */

const MASK64 = ( 1n << 64n ) - 1n;

const PRIME32_1 = 0x9e3779b1n;
const PRIME64_1 = 0x9e3779b185ebca87n;
const PRIME64_2 = 0xc2b2ae3d27d4eb4fn;
const PRIME64_3 = 0x165667b19e3779f9n;
const PRIME_MX1 = 0x165667919e3779f9n;
const PRIME_MX2 = 0x9fb21c651e98df25n;

// Long-input (>240 byte) accumulator geometry, all derived from the 192-byte
// default secret: eight 64-bit lanes, 64-byte stripes, one secret byte
// consumed per 8 input bytes.
const XXH_STRIPE_LEN = 64;
const XXH_ACC_NB = 8;
const XXH_SECRET_CONSUME_RATE = 8;
const XXH_SECRET_SIZE = 192;
const XXH_SECRET_LASTACC_START = 7;
const XXH_SECRET_MERGEACCS_START = 11;

// Initial accumulator lanes (XXH3_INIT_ACC).
const XXH3_INIT_ACC: readonly bigint[] = [
	0xc2b2ae3dn,
	0x9e3779b185ebca87n,
	0xc2b2ae3d27d4eb4fn,
	0x165667b19e3779f9n,
	0x85ebca77c2b2ae63n,
	0x85ebca77n,
	0x27d4eb2f165667c5n,
	0x9e3779b1n,
];

// The 192-byte default secret (`kSecret`) shared by every XXH3 variant.
const SECRET = Uint8Array.from( [
	0xb8, 0xfe, 0x6c, 0x39, 0x23, 0xa4, 0x4b, 0xbe, 0x7c, 0x01, 0x81, 0x2c, 0xf7, 0x21, 0xad, 0x1c,
	0xde, 0xd4, 0x6d, 0xe9, 0x83, 0x90, 0x97, 0xdb, 0x72, 0x40, 0xa4, 0xa4, 0xb7, 0xb3, 0x67, 0x1f,
	0xcb, 0x79, 0xe6, 0x4e, 0xcc, 0xc0, 0xe5, 0x78, 0x82, 0x5a, 0xd0, 0x7d, 0xcc, 0xff, 0x72, 0x21,
	0xb8, 0x08, 0x46, 0x74, 0xf7, 0x43, 0x24, 0x8e, 0xe0, 0x35, 0x90, 0xe6, 0x81, 0x3a, 0x26, 0x4c,
	0x3c, 0x28, 0x52, 0xbb, 0x91, 0xc3, 0x00, 0xcb, 0x88, 0xd0, 0x65, 0x8b, 0x1b, 0x53, 0x2e, 0xa3,
	0x71, 0x64, 0x48, 0x97, 0xa2, 0x0d, 0xf9, 0x4e, 0x38, 0x19, 0xef, 0x46, 0xa9, 0xde, 0xac, 0xd8,
	0xa8, 0xfa, 0x76, 0x3f, 0xe3, 0x9c, 0x34, 0x3f, 0xf9, 0xdc, 0xbb, 0xc7, 0xc7, 0x0b, 0x4f, 0x1d,
	0x8a, 0x51, 0xe0, 0x4b, 0xcd, 0xb4, 0x59, 0x31, 0xc8, 0x9f, 0x7e, 0xc9, 0xd9, 0x78, 0x73, 0x64,
	0xea, 0xc5, 0xac, 0x83, 0x34, 0xd3, 0xeb, 0xc3, 0xc5, 0x81, 0xa0, 0xff, 0xfa, 0x13, 0x63, 0xeb,
	0x17, 0x0d, 0xdd, 0x51, 0xb7, 0xf0, 0xda, 0x49, 0xd3, 0x16, 0x55, 0x26, 0x29, 0xd4, 0x68, 0x9e,
	0x2b, 0x16, 0xbe, 0x58, 0x7d, 0x47, 0xa1, 0xfc, 0x8f, 0xf8, 0xb8, 0xd1, 0x7a, 0xd0, 0x31, 0xce,
	0x45, 0xcb, 0x3a, 0x8f, 0x95, 0x16, 0x04, 0x28, 0xaf, 0xd7, 0xfb, 0xca, 0xbb, 0x4b, 0x40, 0x7e,
] );

function mul( a: bigint, b: bigint ): bigint {
	return ( a * b ) & MASK64;
}

function add( a: bigint, b: bigint ): bigint {
	return ( a + b ) & MASK64;
}

function sub( a: bigint, b: bigint ): bigint {
	return ( a - b ) & MASK64;
}

function rotl( x: bigint, r: bigint ): bigint {
	return ( ( x << r ) | ( x >> ( 64n - r ) ) ) & MASK64;
}

function xorshift( v: bigint, s: bigint ): bigint {
	return v ^ ( v >> s );
}

function readLE32( buf: Uint8Array, off: number ): bigint {
	return (
		BigInt( buf[ off ] | ( buf[ off + 1 ] << 8 ) | ( buf[ off + 2 ] << 16 ) | ( buf[ off + 3 ] << 24 ) ) &
		0xffffffffn
	);
}

function readLE64( buf: Uint8Array, off: number ): bigint {
	let r = 0n;
	for ( let i = 7; i >= 0; i-- ) {
		r = ( r << 8n ) | BigInt( buf[ off + i ] );
	}
	return r;
}

function swap32( x: bigint ): bigint {
	return (
		( ( x << 24n ) & 0xff000000n ) |
		( ( x << 8n ) & 0x00ff0000n ) |
		( ( x >> 8n ) & 0x0000ff00n ) |
		( ( x >> 24n ) & 0x000000ffn )
	) & 0xffffffffn;
}

function swap64( x: bigint ): bigint {
	let r = 0n;
	let v = x;
	for ( let i = 0; i < 8; i++ ) {
		r = ( r << 8n ) | ( v & 0xffn );
		v >>= 8n;
	}
	return r & MASK64;
}

function mul128Fold64( a: bigint, b: bigint ): bigint {
	const product = a * b;
	const lo = product & MASK64;
	const hi = ( product >> 64n ) & MASK64;
	return lo ^ hi;
}

function xxh64Avalanche( input: bigint ): bigint {
	let h = xorshift( input, 33n );
	h = mul( h, PRIME64_2 );
	h = xorshift( h, 29n );
	h = mul( h, PRIME64_3 );
	return xorshift( h, 32n );
}

function xxh3Avalanche( input: bigint ): bigint {
	let h = xorshift( input, 37n );
	h = mul( h, PRIME_MX1 );
	return xorshift( h, 32n );
}

function rrmxmx( input: bigint, len: number ): bigint {
	let h = input;
	h ^= rotl( h, 49n ) ^ rotl( h, 24n );
	h = mul( h, PRIME_MX2 );
	h ^= add( h >> 35n, BigInt( len ) );
	h = mul( h, PRIME_MX2 );
	return xorshift( h, 28n );
}

function mix16B( buf: Uint8Array, boff: number, soff: number, seed: bigint ): bigint {
	const lo = readLE64( buf, boff );
	const hi = readLE64( buf, boff + 8 );
	return mul128Fold64(
		lo ^ add( readLE64( SECRET, soff ), seed ),
		hi ^ sub( readLE64( SECRET, soff + 8 ), seed )
	);
}

function len1to3( buf: Uint8Array, len: number, seed: bigint ): bigint {
	const c1 = BigInt( buf[ 0 ] );
	const c2 = BigInt( buf[ len >> 1 ] );
	const c3 = BigInt( buf[ len - 1 ] );
	const combined = ( ( c1 << 16n ) | ( c2 << 24n ) | c3 | ( BigInt( len ) << 8n ) ) & 0xffffffffn;
	const bitflip = add( readLE32( SECRET, 0 ) ^ readLE32( SECRET, 4 ), seed );
	return xxh64Avalanche( combined ^ bitflip );
}

function len4to8( buf: Uint8Array, len: number, seed: bigint ): bigint {
	const s = seed ^ ( ( swap32( seed & 0xffffffffn ) << 32n ) & MASK64 );
	const input1 = readLE32( buf, 0 );
	const input2 = readLE32( buf, len - 4 );
	const bitflip = sub( readLE64( SECRET, 8 ) ^ readLE64( SECRET, 16 ), s );
	const input64 = add( input2, ( input1 << 32n ) & MASK64 );
	return rrmxmx( input64 ^ bitflip, len );
}

function len9to16( buf: Uint8Array, len: number, seed: bigint ): bigint {
	const bitflip1 = add( readLE64( SECRET, 24 ) ^ readLE64( SECRET, 32 ), seed );
	const bitflip2 = sub( readLE64( SECRET, 40 ) ^ readLE64( SECRET, 48 ), seed );
	const inputLo = readLE64( buf, 0 ) ^ bitflip1;
	const inputHi = readLE64( buf, len - 8 ) ^ bitflip2;
	let acc = add( BigInt( len ), swap64( inputLo ) );
	acc = add( acc, inputHi );
	acc = add( acc, mul128Fold64( inputLo, inputHi ) );
	return xxh3Avalanche( acc );
}

function len0to16( buf: Uint8Array, len: number, seed: bigint ): bigint {
	if ( len > 8 ) {
		return len9to16( buf, len, seed );
	}
	if ( len >= 4 ) {
		return len4to8( buf, len, seed );
	}
	if ( len > 0 ) {
		return len1to3( buf, len, seed );
	}
	return xxh64Avalanche( seed ^ ( readLE64( SECRET, 56 ) ^ readLE64( SECRET, 64 ) ) );
}

function len17to128( buf: Uint8Array, len: number, seed: bigint ): bigint {
	let acc = mul( BigInt( len ), PRIME64_1 );
	if ( len > 32 ) {
		if ( len > 64 ) {
			if ( len > 96 ) {
				acc = add( acc, mix16B( buf, 48, 96, seed ) );
				acc = add( acc, mix16B( buf, len - 64, 112, seed ) );
			}
			acc = add( acc, mix16B( buf, 32, 64, seed ) );
			acc = add( acc, mix16B( buf, len - 48, 80, seed ) );
		}
		acc = add( acc, mix16B( buf, 16, 32, seed ) );
		acc = add( acc, mix16B( buf, len - 32, 48, seed ) );
	}
	acc = add( acc, mix16B( buf, 0, 0, seed ) );
	acc = add( acc, mix16B( buf, len - 16, 16, seed ) );
	return xxh3Avalanche( acc );
}

function len129to240( buf: Uint8Array, len: number, seed: bigint ): bigint {
	let acc = mul( BigInt( len ), PRIME64_1 );
	const nbRounds = Math.floor( len / 16 );
	for ( let i = 0; i < 8; i++ ) {
		acc = add( acc, mix16B( buf, 16 * i, 16 * i, seed ) );
	}
	// XXH3_SECRET_SIZE_MIN (136) − XXH3_MIDSIZE_LASTOFFSET (17).
	let accEnd = mix16B( buf, len - 16, 119, seed );
	acc = xxh3Avalanche( acc );
	for ( let i = 8; i < nbRounds; i++ ) {
		// + XXH3_MIDSIZE_STARTOFFSET (3).
		accEnd = add( accEnd, mix16B( buf, 16 * i, 16 * ( i - 8 ) + 3, seed ) );
	}
	return xxh3Avalanche( add( acc, accEnd ) );
}

function accumulate512( acc: bigint[], buf: Uint8Array, inOff: number, secretOff: number ): void {
	for ( let i = 0; i < XXH_ACC_NB; i++ ) {
		const dataVal = readLE64( buf, inOff + 8 * i );
		const dataKey = dataVal ^ readLE64( SECRET, secretOff + 8 * i );
		const lo = dataKey & 0xffffffffn;
		const hi = dataKey >> 32n;
		acc[ i ^ 1 ] = add( acc[ i ^ 1 ], dataVal );
		acc[ i ] = add( acc[ i ], mul( lo, hi ) );
	}
}

function scrambleAcc( acc: bigint[], secretOff: number ): void {
	for ( let i = 0; i < XXH_ACC_NB; i++ ) {
		let a = xorshift( acc[ i ], 47n );
		a ^= readLE64( SECRET, secretOff + 8 * i );
		acc[ i ] = mul( a, PRIME32_1 );
	}
}

function mergeAccs( acc: bigint[], secretOff: number, start: bigint ): bigint {
	let result = start;
	for ( let i = 0; i < 4; i++ ) {
		result = add(
			result,
			mul128Fold64(
				acc[ 2 * i ] ^ readLE64( SECRET, secretOff + 16 * i ),
				acc[ 2 * i + 1 ] ^ readLE64( SECRET, secretOff + 16 * i + 8 )
			)
		);
	}
	return xxh3Avalanche( result );
}

function hashLong( buf: Uint8Array, len: number ): bigint {
	const acc = XXH3_INIT_ACC.slice();
	const nbStripesPerBlock = ( XXH_SECRET_SIZE - XXH_STRIPE_LEN ) / XXH_SECRET_CONSUME_RATE;
	const blockLen = XXH_STRIPE_LEN * nbStripesPerBlock;
	const nbBlocks = Math.floor( ( len - 1 ) / blockLen );

	for ( let n = 0; n < nbBlocks; n++ ) {
		const blockOff = n * blockLen;
		for ( let s = 0; s < nbStripesPerBlock; s++ ) {
			accumulate512( acc, buf, blockOff + s * XXH_STRIPE_LEN, s * XXH_SECRET_CONSUME_RATE );
		}
		scrambleAcc( acc, XXH_SECRET_SIZE - XXH_STRIPE_LEN );
	}

	const nbStripes = Math.floor( ( ( len - 1 ) - blockLen * nbBlocks ) / XXH_STRIPE_LEN );
	const lastBlockOff = nbBlocks * blockLen;
	for ( let s = 0; s < nbStripes; s++ ) {
		accumulate512( acc, buf, lastBlockOff + s * XXH_STRIPE_LEN, s * XXH_SECRET_CONSUME_RATE );
	}

	accumulate512( acc, buf, len - XXH_STRIPE_LEN, XXH_SECRET_SIZE - XXH_STRIPE_LEN - XXH_SECRET_LASTACC_START );

	return mergeAccs( acc, XXH_SECRET_MERGEACCS_START, mul( BigInt( len ), PRIME64_1 ) );
}

/**
 * Compute the XXH3-64 digest (seed 0, default secret) of a UTF-8 string,
 * formatted as 16 lowercase hex characters — the exact shape PHP's
 * `hash( 'xxh3', $string )` returns.
 *
 * Every input length is supported: inputs over 240 bytes take the
 * accumulator/scramble long-hash path, which matches PHP for arbitrarily
 * long width maps.
 */
export function xxh3_64_hex( input: string ): string {
	const buf = new TextEncoder().encode( input );
	const len = buf.length;
	const seed = 0n;

	let hash: bigint;
	if ( len <= 16 ) {
		hash = len0to16( buf, len, seed );
	} else if ( len <= 128 ) {
		hash = len17to128( buf, len, seed );
	} else if ( len <= 240 ) {
		hash = len129to240( buf, len, seed );
	} else {
		hash = hashLong( buf, len );
	}

	return hash.toString( 16 ).padStart( 16, '0' );
}
