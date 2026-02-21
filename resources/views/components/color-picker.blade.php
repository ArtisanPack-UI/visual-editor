{{--
 * Color Picker Component
 *
 * A WordPress-style color picker with saturation/brightness canvas, hue slider,
 * optional alpha slider, format toggle (Hex/RGB/HSL), and copy-to-clipboard.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="veColorPicker( {{ Js::from( $value ) }}, {{ Js::from( $showAlpha ) }} )"
	{{ $attributes->merge( [ 'class' => 'flex flex-col gap-3', 'style' => 'width: ' . $width ] ) }}
>
	{{-- Saturation/Brightness Canvas --}}
	<div
		class="relative rounded-lg overflow-hidden cursor-crosshair select-none"
		style="height: 150px"
		x-ref="canvasWrap"
		role="application"
		aria-label="{{ __( 'visual-editor::ve.color_saturation_brightness_picker' ) }}"
		aria-roledescription="{{ __( 'visual-editor::ve.two_dimensional_slider' ) }}"
		tabindex="0"
		x-on:mousedown.prevent="onCanvasMouseDown( $event )"
		x-on:touchstart.prevent="onCanvasTouchStart( $event )"
		x-on:keydown="onCanvasKeyDown( $event )"
	>
		<canvas x-ref="canvas" class="w-full h-full block" aria-hidden="true"></canvas>
		{{-- Thumb --}}
		<div
			class="absolute w-4 h-4 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(0,0,0,0.3)] pointer-events-none -translate-x-1/2 -translate-y-1/2"
			:style="`left: ${saturation}%; top: ${100 - brightness}%; background-color: ${hex}`"
			aria-hidden="true"
		></div>
	</div>

	{{-- Hue Slider --}}
	<div class="flex items-center gap-2">
		<div
			class="h-5 w-5 rounded-full shrink-0 border border-base-300"
			:style="`background-color: ${hex}`"
			aria-hidden="true"
		></div>
		<input
			type="range"
			min="0"
			max="360"
			step="1"
			x-model.number="hue"
			x-on:input="onHueChange()"
			class="ve-color-picker-hue-slider w-full h-3 rounded-full appearance-none cursor-pointer"
			aria-label="{{ __( 'visual-editor::ve.hue' ) }}"
			:aria-valuenow="hue"
			aria-valuemin="0"
			aria-valuemax="360"
		/>
	</div>

	@if ( $showAlpha )
		{{-- Alpha Slider --}}
		<div class="flex items-center gap-2">
			<div
				class="h-3 w-3 rounded-full shrink-0 border border-base-300 bg-[repeating-conic-gradient(#d1d5db_0%_25%,transparent_0%_50%)] bg-[length:6px_6px]"
				aria-hidden="true"
			>
				<div
					class="w-full h-full rounded-full"
					:style="`background-color: ${hex}; opacity: ${alpha}`"
				></div>
			</div>
			<div class="relative w-full">
				<div
					class="absolute inset-0 h-3 rounded-full bg-[repeating-conic-gradient(#d1d5db_0%_25%,transparent_0%_50%)] bg-[length:6px_6px] pointer-events-none"
					style="top: 50%; transform: translateY(-50%)"
				></div>
				<input
					type="range"
					min="0"
					max="100"
					step="1"
					x-model.number="alphaPercent"
					x-on:input="onAlphaChange()"
					class="ve-color-picker-alpha-slider relative w-full h-3 rounded-full appearance-none cursor-pointer"
					:style="`--picker-alpha-color: ${hex}`"
					aria-label="{{ __( 'visual-editor::ve.opacity' ) }}"
					:aria-valuenow="alphaPercent"
					aria-valuemin="0"
					aria-valuemax="100"
				/>
			</div>
		</div>
	@endif

	{{-- Format Toggle + Copy --}}
	<div class="flex items-center gap-2">
		@if ( $showFormatToggle )
			<select
				x-model="format"
				class="select select-xs border-base-300 bg-base-100 text-xs font-medium min-h-0 h-7 pr-6"
				aria-label="{{ __( 'visual-editor::ve.color_format' ) }}"
			>
				<option value="hex">Hex</option>
				<option value="rgb">RGB</option>
				<option value="hsl">HSL</option>
			</select>
		@else
			<span class="text-xs font-medium text-base-content/60 uppercase">Hex</span>
		@endif

		<div class="flex-1"></div>

		@if ( $showCopyButton )
			<button
				type="button"
				x-on:click="copyToClipboard()"
				class="btn btn-xs btn-ghost min-h-0 h-7 w-7 p-0"
				aria-label="{{ __( 'visual-editor::ve.copy_color_value' ) }}"
				:title="copied ? {!! Js::from( __( 'visual-editor::ve.copied' ) ) !!} : {!! Js::from( __( 'visual-editor::ve.copy_to_clipboard' ) ) !!}"
			>
				<template x-if="!copied">
					<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
					</svg>
				</template>
				<template x-if="copied">
					<svg class="w-3.5 h-3.5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
					</svg>
				</template>
			</button>
		@endif
	</div>

	{{-- Color Value Input --}}
	<div class="flex items-center gap-2">
		<div
			class="h-7 w-7 rounded shrink-0 border border-base-300"
			:style="`background-color: ${hex}`"
			aria-hidden="true"
		></div>
		<input
			type="text"
			x-model="inputValue"
			x-on:change="onInputChange()"
			x-on:keydown.enter.prevent="onInputChange()"
			class="input input-xs input-bordered w-full font-mono text-xs min-h-0 h-7"
			aria-label="{{ __( 'visual-editor::ve.color_value' ) }}"
		/>
	</div>

	@if ( $hint )
		<div class="{{ $hintClass }}">{{ $hint }}</div>
	@endif
</div>

@once
<style>
	/* Hue slider - rainbow gradient */
	.ve-color-picker-hue-slider {
		background: linear-gradient(
			to right,
			hsl(0, 100%, 50%),
			hsl(60, 100%, 50%),
			hsl(120, 100%, 50%),
			hsl(180, 100%, 50%),
			hsl(240, 100%, 50%),
			hsl(300, 100%, 50%),
			hsl(360, 100%, 50%)
		);
	}

	.ve-color-picker-hue-slider::-webkit-slider-thumb {
		-webkit-appearance: none;
		appearance: none;
		width: 14px;
		height: 14px;
		border-radius: 50%;
		background: white;
		border: 2px solid white;
		box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2);
		cursor: pointer;
	}

	.ve-color-picker-hue-slider::-moz-range-thumb {
		width: 14px;
		height: 14px;
		border-radius: 50%;
		background: white;
		border: 2px solid white;
		box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2);
		cursor: pointer;
	}

	/* Alpha slider */
	.ve-color-picker-alpha-slider {
		background: linear-gradient(
			to right,
			transparent,
			var(--picker-alpha-color, #000)
		);
	}

	.ve-color-picker-alpha-slider::-webkit-slider-thumb {
		-webkit-appearance: none;
		appearance: none;
		width: 14px;
		height: 14px;
		border-radius: 50%;
		background: white;
		border: 2px solid white;
		box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2);
		cursor: pointer;
	}

	.ve-color-picker-alpha-slider::-moz-range-thumb {
		width: 14px;
		height: 14px;
		border-radius: 50%;
		background: white;
		border: 2px solid white;
		box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.3), 0 1px 3px rgba(0, 0, 0, 0.2);
		cursor: pointer;
	}

	/* Remove default slider track styling */
	.ve-color-picker-hue-slider::-webkit-slider-runnable-track,
	.ve-color-picker-alpha-slider::-webkit-slider-runnable-track {
		height: 12px;
		border-radius: 9999px;
	}

	.ve-color-picker-hue-slider::-moz-range-track,
	.ve-color-picker-alpha-slider::-moz-range-track {
		height: 12px;
		border-radius: 9999px;
		background: transparent;
	}
</style>
@endonce

@once
<script>
	document.addEventListener( 'alpine:init', () => {
		if ( Alpine.data && ! Alpine._veColorPickerRegistered ) {
			Alpine._veColorPickerRegistered = true;

			Alpine.data( 'veColorPicker', ( initialValue, showAlpha ) => ( {
				hue: 0,
				saturation: 100,
				brightness: 100,
				alpha: 1,
				alphaPercent: 100,
				hex: initialValue || '#000000',
				format: 'hex',
				inputValue: '',
				dragging: null,
				copied: false,
				_resizeObserver: null,

				init() {
					this.updateFromHex( this.hex );
					this.updateInputValue();
					this.$nextTick( () => this.drawCanvas() );

					this.$watch( 'format', () => this.updateInputValue() );

					/* Redraw canvas when it becomes visible (e.g. inside a hidden dropdown) */
					this._resizeObserver = new ResizeObserver( () => {
						const wrap = this.$refs.canvasWrap;
						if ( wrap && wrap.offsetWidth > 0 && wrap.offsetHeight > 0 ) {
							this.drawCanvas();
						}
					} );
					this._resizeObserver.observe( this.$refs.canvasWrap );
				},

				destroy() {
					if ( this._resizeObserver ) {
						this._resizeObserver.disconnect();
						this._resizeObserver = null;
					}
				},

				/* ---- Color Math ---- */
				hsvToRgb( h, s, v ) {
					s /= 100;
					v /= 100;
					const c = v * s;
					const x = c * ( 1 - Math.abs( ( ( h / 60 ) % 2 ) - 1 ) );
					const m = v - c;
					let r = 0, g = 0, b = 0;

					if ( h < 60 )      { r = c; g = x; b = 0; }
					else if ( h < 120 ) { r = x; g = c; b = 0; }
					else if ( h < 180 ) { r = 0; g = c; b = x; }
					else if ( h < 240 ) { r = 0; g = x; b = c; }
					else if ( h < 300 ) { r = x; g = 0; b = c; }
					else                { r = c; g = 0; b = x; }

					return {
						r: Math.round( ( r + m ) * 255 ),
						g: Math.round( ( g + m ) * 255 ),
						b: Math.round( ( b + m ) * 255 ),
					};
				},

				rgbToHsv( r, g, b ) {
					r /= 255; g /= 255; b /= 255;
					const max = Math.max( r, g, b );
					const min = Math.min( r, g, b );
					const d   = max - min;
					let h = 0;

					if ( 0 !== d ) {
						if ( max === r )      { h = ( ( g - b ) / d ) % 6; }
						else if ( max === g ) { h = ( b - r ) / d + 2; }
						else                  { h = ( r - g ) / d + 4; }
						h = Math.round( h * 60 );
						if ( h < 0 ) { h += 360; }
					}

					return {
						h: h,
						s: 0 === max ? 0 : Math.round( ( d / max ) * 100 ),
						v: Math.round( max * 100 ),
					};
				},

				rgbToHex( r, g, b ) {
					return '#' + [ r, g, b ].map( c =>
						c.toString( 16 ).padStart( 2, '0' )
					).join( '' );
				},

				hexToRgb( hex ) {
					hex = hex.replace( /^#/, '' );
					if ( 3 === hex.length ) {
						hex = hex.split( '' ).map( c => c + c ).join( '' );
					}
					if ( 6 !== hex.length ) { return null; }
					const num = parseInt( hex, 16 );
					if ( isNaN( num ) ) { return null; }
					return {
						r: ( num >> 16 ) & 255,
						g: ( num >> 8 ) & 255,
						b: num & 255,
					};
				},

				rgbToHsl( r, g, b ) {
					r /= 255; g /= 255; b /= 255;
					const max = Math.max( r, g, b );
					const min = Math.min( r, g, b );
					const l   = ( max + min ) / 2;
					let h = 0, s = 0;

					if ( max !== min ) {
						const d = max - min;
						s = l > 0.5 ? d / ( 2 - max - min ) : d / ( max + min );
						if ( max === r )      { h = ( ( g - b ) / d + ( g < b ? 6 : 0 ) ) / 6; }
						else if ( max === g ) { h = ( ( b - r ) / d + 2 ) / 6; }
						else                  { h = ( ( r - g ) / d + 4 ) / 6; }
					}

					return {
						h: Math.round( h * 360 ),
						s: Math.round( s * 100 ),
						l: Math.round( l * 100 ),
					};
				},

				/* ---- Canvas ---- */
				drawCanvas() {
					const canvas = this.$refs.canvas;
					if ( ! canvas ) { return; }
					const wrap = this.$refs.canvasWrap;
					if ( ! wrap || 0 === wrap.offsetWidth || 0 === wrap.offsetHeight ) { return; }
					canvas.width  = wrap.offsetWidth;
					canvas.height = wrap.offsetHeight;
					const ctx = canvas.getContext( '2d' );
					const w   = canvas.width;
					const h   = canvas.height;

					/* Base hue fill */
					const rgb = this.hsvToRgb( this.hue, 100, 100 );
					ctx.fillStyle = `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
					ctx.fillRect( 0, 0, w, h );

					/* White → transparent (left to right = saturation) */
					const whiteGrad = ctx.createLinearGradient( 0, 0, w, 0 );
					whiteGrad.addColorStop( 0, 'rgba(255, 255, 255, 1)' );
					whiteGrad.addColorStop( 1, 'rgba(255, 255, 255, 0)' );
					ctx.fillStyle = whiteGrad;
					ctx.fillRect( 0, 0, w, h );

					/* Black → transparent (bottom to top = brightness) */
					const blackGrad = ctx.createLinearGradient( 0, 0, 0, h );
					blackGrad.addColorStop( 0, 'rgba(0, 0, 0, 0)' );
					blackGrad.addColorStop( 1, 'rgba(0, 0, 0, 1)' );
					ctx.fillStyle = blackGrad;
					ctx.fillRect( 0, 0, w, h );
				},

				/* ---- Interactions ---- */
				onCanvasMouseDown( e ) {
					this.dragging = 'canvas';
					this.updateCanvasFromEvent( e );

					const onMove = ( ev ) => this.updateCanvasFromEvent( ev );
					const onUp   = () => {
						this.dragging = null;
						document.removeEventListener( 'mousemove', onMove );
						document.removeEventListener( 'mouseup', onUp );
					};
					document.addEventListener( 'mousemove', onMove );
					document.addEventListener( 'mouseup', onUp );
				},

				onCanvasTouchStart( e ) {
					this.dragging = 'canvas';
					this.updateCanvasFromEvent( e.touches[0] );

					const onMove = ( ev ) => {
						ev.preventDefault();
						this.updateCanvasFromEvent( ev.touches[0] );
					};
					const cleanup = () => {
						this.dragging = null;
						document.removeEventListener( 'touchmove', onMove );
						document.removeEventListener( 'touchend', cleanup );
						document.removeEventListener( 'touchcancel', cleanup );
					};
					document.addEventListener( 'touchmove', onMove, { passive: false } );
					document.addEventListener( 'touchend', cleanup );
					document.addEventListener( 'touchcancel', cleanup );
				},

				updateCanvasFromEvent( e ) {
					const rect = this.$refs.canvasWrap.getBoundingClientRect();
					const x    = Math.max( 0, Math.min( e.clientX - rect.left, rect.width ) );
					const y    = Math.max( 0, Math.min( e.clientY - rect.top, rect.height ) );

					this.saturation = Math.round( ( x / rect.width ) * 100 );
					this.brightness = Math.round( ( 1 - y / rect.height ) * 100 );
					this.updateHex();
				},

				onCanvasKeyDown( e ) {
					const step = e.shiftKey ? 10 : 1;
					let handled = false;

					if ( 'ArrowRight' === e.key ) {
						this.saturation = Math.min( 100, this.saturation + step );
						handled = true;
					} else if ( 'ArrowLeft' === e.key ) {
						this.saturation = Math.max( 0, this.saturation - step );
						handled = true;
					} else if ( 'ArrowUp' === e.key ) {
						this.brightness = Math.min( 100, this.brightness + step );
						handled = true;
					} else if ( 'ArrowDown' === e.key ) {
						this.brightness = Math.max( 0, this.brightness - step );
						handled = true;
					}

					if ( handled ) {
						e.preventDefault();
						this.updateHex();
					}
				},

				onHueChange() {
					this.drawCanvas();
					this.updateHex();
				},

				onAlphaChange() {
					this.alpha = this.alphaPercent / 100;
					this.dispatchChange();
					this.updateInputValue();
				},

				/* ---- State Sync ---- */
				updateHex() {
					const rgb  = this.hsvToRgb( this.hue, this.saturation, this.brightness );
					this.hex   = this.rgbToHex( rgb.r, rgb.g, rgb.b );
					this.updateInputValue();
					this.dispatchChange();
				},

				updateFromHex( hex ) {
					const rgb = this.hexToRgb( hex );
					if ( ! rgb ) { return; }
					const hsv       = this.rgbToHsv( rgb.r, rgb.g, rgb.b );
					this.hue        = hsv.h;
					this.saturation = hsv.s;
					this.brightness = hsv.v;
					this.hex        = this.rgbToHex( rgb.r, rgb.g, rgb.b );
				},

				updateInputValue() {
					const rgb = this.hsvToRgb( this.hue, this.saturation, this.brightness );

					if ( 'rgb' === this.format ) {
						this.inputValue = `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
					} else if ( 'hsl' === this.format ) {
						const hsl       = this.rgbToHsl( rgb.r, rgb.g, rgb.b );
						this.inputValue = `hsl(${hsl.h}, ${hsl.s}%, ${hsl.l}%)`;
					} else {
						this.inputValue = this.hex;
					}
				},

				onInputChange() {
					const val = this.inputValue.trim();

					/* Try hex */
					if ( val.startsWith( '#' ) || /^[0-9a-f]{3,6}$/i.test( val ) ) {
						const hex = val.startsWith( '#' ) ? val : '#' + val;
						if ( this.hexToRgb( hex ) ) {
							this.updateFromHex( hex );
							this.updateInputValue();
							this.drawCanvas();
							this.dispatchChange();
							return;
						}
					}

					/* Try rgb() */
					const rgbMatch = val.match( /^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i );
					if ( rgbMatch ) {
						const r = parseInt( rgbMatch[1], 10 );
						const g = parseInt( rgbMatch[2], 10 );
						const b = parseInt( rgbMatch[3], 10 );
						if ( r <= 255 && g <= 255 && b <= 255 ) {
							this.updateFromHex( this.rgbToHex( r, g, b ) );
							this.updateInputValue();
							this.drawCanvas();
							this.dispatchChange();
							return;
						}
					}

					/* Try hsl() — convert HSL to HSV before assigning */
					const hslMatch = val.match( /^hsl\(\s*(\d+)\s*,\s*(\d+)%?\s*,\s*(\d+)%?\s*\)$/i );
					if ( hslMatch ) {
						const h  = parseInt( hslMatch[1], 10 ) % 360;
						const sH = Math.min( 100, parseInt( hslMatch[2], 10 ) ) / 100;
						const l  = Math.min( 100, parseInt( hslMatch[3], 10 ) ) / 100;

						/* HSL -> HSV conversion */
						const v  = l + sH * Math.min( l, 1 - l );
						const sV = 0 === v ? 0 : 2 * ( 1 - l / v );

						this.hue        = h;
						this.saturation = Math.round( sV * 100 );
						this.brightness = Math.round( v * 100 );
						this.updateHex();
						this.drawCanvas();
						return;
					}

					/* Invalid - revert */
					this.updateInputValue();
				},

				/* ---- Dispatch ---- */
				dispatchChange() {
					const rgb = this.hsvToRgb( this.hue, this.saturation, this.brightness );
					const hsl = this.rgbToHsl( rgb.r, rgb.g, rgb.b );
					this.$dispatch( 've-color-picker-change', {
						hex: this.hex,
						rgb: { r: rgb.r, g: rgb.g, b: rgb.b },
						hsl: { h: hsl.h, s: hsl.s, l: hsl.l },
						alpha: this.alpha,
					} );
				},

				/* ---- Copy ---- */
				copyToClipboard() {
					if ( ! navigator.clipboard ) { return; }
					navigator.clipboard.writeText( this.inputValue ).then( () => {
						this.copied = true;
						setTimeout( () => { this.copied = false; }, 1500 );
					} ).catch( () => {} );
				},
			} ) );
		}
	} );
</script>
@endonce
