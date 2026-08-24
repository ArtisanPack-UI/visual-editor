/**
 * SHA-1 (single-shot, hex digest) — Vue renderer.
 *
 * A byte-for-byte port of PHP's `sha1( $string )`, which the Blade
 * renderer uses to mint the `photo-grid-<12-char-sha1>` scope class in
 * `PhotoGridSupport::wrapperForBlock()` (#594). Porting the hash here lets
 * the Vue renderer produce the *same* class token for the same wrapper
 * declaration, so a Photo Grid block renders identically through either
 * renderer and the token comes back under the #704 markup-parity check.
 *
 * The mirror lives at the matching path in the React renderer; the shared
 * vitest suite pins both against reference digests.
 *
 * @package @artisanpack-ui/visual-editor-renderer-vue
 * @since 1.7.0
 */

/**
 * Compute the lowercase 40-character hex SHA-1 digest of a string. The
 * input is UTF-8 encoded first so multi-byte characters hash the same
 * bytes PHP's `sha1()` sees.
 */
export function sha1Hex( input: string ): string {
	const bytes = new TextEncoder().encode( input );

	// Message length in bits. Kept as a JS number: the declaration
	// strings this hashes are a few dozen bytes, far below the 2^53
	// safe-integer ceiling.
	const bitLength = bytes.length * 8;

	// Pad to a multiple of 64 bytes: append 0x80, then zeros, then the
	// 64-bit big-endian bit length in the final eight bytes.
	const withMarker = bytes.length + 1;
	const totalLength = withMarker + ( ( 56 - ( withMarker % 64 ) + 64 ) % 64 ) + 8;
	const message = new Uint8Array( totalLength );

	message.set( bytes );
	message[ bytes.length ] = 0x80;

	const view = new DataView( message.buffer );

	view.setUint32( totalLength - 8, Math.floor( bitLength / 0x100000000 ) );
	view.setUint32( totalLength - 4, bitLength >>> 0 );

	let h0 = 0x67452301;
	let h1 = 0xefcdab89;
	let h2 = 0x98badcfe;
	let h3 = 0x10325476;
	let h4 = 0xc3d2e1f0;

	const w = new Uint32Array( 80 );

	for ( let chunk = 0; chunk < totalLength; chunk += 64 ) {
		for ( let i = 0; i < 16; i++ ) {
			w[ i ] = view.getUint32( chunk + i * 4 );
		}

		for ( let i = 16; i < 80; i++ ) {
			const value = w[ i - 3 ] ^ w[ i - 8 ] ^ w[ i - 14 ] ^ w[ i - 16 ];
			w[ i ] = ( ( value << 1 ) | ( value >>> 31 ) ) >>> 0;
		}

		let a = h0;
		let b = h1;
		let c = h2;
		let d = h3;
		let e = h4;

		for ( let i = 0; i < 80; i++ ) {
			let f: number;
			let k: number;

			if ( i < 20 ) {
				f = ( b & c ) | ( ~b & d );
				k = 0x5a827999;
			} else if ( i < 40 ) {
				f = b ^ c ^ d;
				k = 0x6ed9eba1;
			} else if ( i < 60 ) {
				f = ( b & c ) | ( b & d ) | ( c & d );
				k = 0x8f1bbcdc;
			} else {
				f = b ^ c ^ d;
				k = 0xca62c1d6;
			}

			const temp = ( ( ( a << 5 ) | ( a >>> 27 ) ) + f + e + k + w[ i ] ) >>> 0;

			e = d;
			d = c;
			c = ( ( b << 30 ) | ( b >>> 2 ) ) >>> 0;
			b = a;
			a = temp;
		}

		h0 = ( h0 + a ) >>> 0;
		h1 = ( h1 + b ) >>> 0;
		h2 = ( h2 + c ) >>> 0;
		h3 = ( h3 + d ) >>> 0;
		h4 = ( h4 + e ) >>> 0;
	}

	return [ h0, h1, h2, h3, h4 ]
		.map( ( part ) => part.toString( 16 ).padStart( 8, '0' ) )
		.join( '' );
}
