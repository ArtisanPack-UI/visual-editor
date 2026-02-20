{{--
 * Keyboard Shortcuts Component
 *
 * Provides a keyboard shortcuts system with an Alpine.js store
 * for registering, executing, and displaying shortcuts.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		showHelp: false,
		init() {
			if ( ! Alpine.store( 'shortcuts' ) ) {
				Alpine.store( 'shortcuts', {
					registered: {},
					isMac: navigator.platform.toUpperCase().indexOf( 'MAC' ) >= 0,
					showHelp: false,

					register( name, config ) {
						if ( this.registered[ name ] ) {
							console.warn( 'Shortcut conflict: ' + name + ' is already registered.' );
						}
						this.registered[ name ] = {
							keys: config.keys || '',
							description: config.description || '',
							category: config.category || 'global',
							context: config.context || 'global',
							callback: config.callback || null,
						};
					},

					unregister( name ) {
						delete this.registered[ name ];
					},

					getAll() {
						return this.registered;
					},

					getByCategory() {
						const grouped = {};
						Object.entries( this.registered ).forEach( ( [ name, shortcut ] ) => {
							const cat = shortcut.category;
							if ( ! grouped[ cat ] ) {
								grouped[ cat ] = [];
							}
							grouped[ cat ].push( { name, ...shortcut } );
						} );
						return grouped;
					},

					formatKeys( keys ) {
						if ( ! keys ) return '';
						return keys.split( '+' ).map( ( key ) => {
							const k = key.trim().toLowerCase();
							if ( this.isMac ) {
								if ( k === 'mod' || k === 'ctrl' ) return '\u2318';
								if ( k === 'alt' ) return '\u2325';
								if ( k === 'shift' ) return '\u21E7';
								if ( k === 'enter' ) return '\u21A9';
								if ( k === 'backspace' || k === 'delete' ) return '\u232B';
								if ( k === 'escape' || k === 'esc' ) return '\u238B';
								if ( k === 'tab' ) return '\u21E5';
								if ( k === 'up' ) return '\u2191';
								if ( k === 'down' ) return '\u2193';
								if ( k === 'left' ) return '\u2190';
								if ( k === 'right' ) return '\u2192';
							} else {
								if ( k === 'mod' ) return 'Ctrl';
								if ( k === 'ctrl' ) return 'Ctrl';
								if ( k === 'alt' ) return 'Alt';
								if ( k === 'shift' ) return 'Shift';
								if ( k === 'enter' ) return 'Enter';
								if ( k === 'backspace' || k === 'delete' ) return 'Del';
								if ( k === 'escape' || k === 'esc' ) return 'Esc';
								if ( k === 'tab' ) return 'Tab';
								if ( k === 'up' ) return '\u2191';
								if ( k === 'down' ) return '\u2193';
								if ( k === 'left' ) return '\u2190';
								if ( k === 'right' ) return '\u2192';
							}
							return key.trim().toUpperCase();
						} ).join( this.isMac ? '' : ' + ' );
					},

					matchesEvent( keys, event ) {
						if ( ! keys ) return false;
						const parts = keys.toLowerCase().split( '+' ).map( ( p ) => p.trim() );
						const modKey = this.isMac ? event.metaKey : event.ctrlKey;
						const needsMod = parts.includes( 'mod' ) || parts.includes( 'ctrl' );
						const needsShift = parts.includes( 'shift' );
						const needsAlt = parts.includes( 'alt' );

						if ( needsMod !== modKey ) return false;
						if ( needsShift !== event.shiftKey ) return false;
						if ( needsAlt !== event.altKey ) return false;

						const mainKey = parts.filter(
							( p ) => ! [ 'mod', 'ctrl', 'shift', 'alt' ].includes( p )
						).pop();

						if ( ! mainKey ) return false;

						const eventKey = event.key.toLowerCase();
						if ( mainKey === eventKey ) return true;
						if ( mainKey === 'delete' && ( eventKey === 'delete' || eventKey === 'backspace' ) ) return true;
						if ( mainKey === 'escape' && eventKey === 'escape' ) return true;
						if ( mainKey === '?' && eventKey === '?' ) return true;

						return false;
					},

					isInputFocused() {
						const el = document.activeElement;
						if ( ! el ) return false;
						const tag = el.tagName.toLowerCase();
						return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
					}
				} );
			}

			@if ( count( $shortcuts ) > 0 )
				const store = Alpine.store( 'shortcuts' );
				@foreach ( $shortcuts as $shortcut )
					store.register(
						{{ Js::from( $shortcut['name'] ?? '' ) }},
						{
							keys: {{ Js::from( $shortcut['keys'] ?? '' ) }},
							description: {{ Js::from( $shortcut['description'] ?? '' ) }},
							category: {{ Js::from( $shortcut['category'] ?? 'global' ) }},
							context: {{ Js::from( $shortcut['context'] ?? 'global' ) }},
						}
					);
				@endforeach
			@endif
		},

		handleKeydown( event ) {
			const store = Alpine.store( 'shortcuts' );
			if ( ! store ) return;

			{{-- Help shortcut: always listen for ? --}}
			@if ( $showHelpModal )
				if ( event.key === {{ Js::from( $helpShortcut ) }} && ! store.isInputFocused() ) {
					event.preventDefault();
					this.showHelp = ! this.showHelp;
					return;
				}
			@endif

			{{-- Check registered shortcuts --}}
			for ( const [ name, shortcut ] of Object.entries( store.registered ) ) {
				if ( store.matchesEvent( shortcut.keys, event ) ) {
					{{-- Context check --}}
					if ( shortcut.context === 'input' && ! store.isInputFocused() ) continue;
					if ( shortcut.context === 'global' && store.isInputFocused() ) continue;

					event.preventDefault();
					if ( shortcut.callback ) {
						shortcut.callback();
					}
					$dispatch( 've-shortcut-' + name, { name, keys: shortcut.keys } );
					return;
				}
			}
		}
	}"
	x-on:keydown.window="handleKeydown( $event )"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	@if ( $showHelpModal )
		<x-artisanpack-modal
			wire:model="showHelp"
			x-model="showHelp"
			:title="$title"
		>
			<div
				x-data="{ categories: Alpine.store( 'shortcuts' )?.getByCategory() || {} }"
				class="space-y-4"
			>
				<template x-for="( shortcuts, category ) in categories" :key="category">
					<div>
						<h4
							class="text-sm font-semibold text-base-content/70 uppercase tracking-wide mb-2"
							x-text="category"
						></h4>
						<div class="space-y-1">
							<template x-for="shortcut in shortcuts" :key="shortcut.name">
								<div class="flex items-center justify-between py-1.5 px-2 rounded hover:bg-base-200">
									<span
										class="text-sm text-base-content"
										x-text="shortcut.description"
									></span>
									<kbd
										class="kbd kbd-sm font-mono"
										x-text="Alpine.store( 'shortcuts' ).formatKeys( shortcut.keys )"
									></kbd>
								</div>
							</template>
						</div>
					</div>
				</template>

				<template x-if="Object.keys( categories ).length === 0">
					<p class="text-sm text-base-content/50 text-center py-4">
						{{ __( 'No shortcuts registered.' ) }}
					</p>
				</template>
			</div>

			<x-slot:actions>
				<x-artisanpack-button x-on:click="showHelp = false">
					{{ __( 'Close' ) }}
				</x-artisanpack-button>
			</x-slot:actions>
		</x-artisanpack-modal>
	@endif
</div>
