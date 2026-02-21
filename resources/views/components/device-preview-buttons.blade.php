{{--
 * Device Preview Buttons Component
 *
 * A toolbar group with desktop, tablet, and mobile toggle buttons
 * for switching the device preview mode.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div
	id="{{ $uuid }}"
	x-data="{
		get currentDevice() {
			return Alpine.store( 'editor' ) ? Alpine.store( 'editor' ).devicePreview : 'desktop';
		},

		setDevice( device ) {
			if ( Alpine.store( 'editor' ) ) {
				Alpine.store( 'editor' ).devicePreview = device;

				if ( Alpine.store( 'announcer' ) ) {
					const labels = {
						desktop: {{ Js::from( __( 'visual-editor::ve.device_desktop' ) ) }},
						tablet: {{ Js::from( __( 'visual-editor::ve.device_tablet' ) ) }},
						mobile: {{ Js::from( __( 'visual-editor::ve.device_mobile' ) ) }},
					};
					Alpine.store( 'announcer' ).announce(
						{{ Js::from( __( 'visual-editor::ve.switched_to_device', [ 'device' => '__DEVICE__' ] ) ) }}.replaceAll( '__DEVICE__', labels[ device ] || device )
					);
				}
			}
		},
	}"
	{{ $attributes->merge( [ 'class' => 'flex items-center gap-0.5' ] ) }}
	role="radiogroup"
	aria-label="{{ $label ?? __( 'visual-editor::ve.device_preview' ) }}"
>
	{{-- Desktop --}}
	<button
		type="button"
		class="btn btn-ghost btn-xs btn-square"
		:class="'desktop' === currentDevice ? 'btn-active' : ''"
		x-on:click="setDevice( 'desktop' )"
		role="radio"
		:aria-checked="'desktop' === currentDevice"
		aria-label="{{ __( 'visual-editor::ve.device_desktop' ) }}"
	>
		<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
			<path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25A2.25 2.25 0 0 1 5.25 3h13.5A2.25 2.25 0 0 1 21 5.25Z" />
		</svg>
	</button>

	{{-- Tablet --}}
	<button
		type="button"
		class="btn btn-ghost btn-xs btn-square"
		:class="'tablet' === currentDevice ? 'btn-active' : ''"
		x-on:click="setDevice( 'tablet' )"
		role="radio"
		:aria-checked="'tablet' === currentDevice"
		aria-label="{{ __( 'visual-editor::ve.device_tablet' ) }}"
	>
		<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
			<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.5a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" />
		</svg>
	</button>

	{{-- Mobile --}}
	<button
		type="button"
		class="btn btn-ghost btn-xs btn-square"
		:class="'mobile' === currentDevice ? 'btn-active' : ''"
		x-on:click="setDevice( 'mobile' )"
		role="radio"
		:aria-checked="'mobile' === currentDevice"
		aria-label="{{ __( 'visual-editor::ve.device_mobile' ) }}"
	>
		<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false">
			<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
		</svg>
	</button>
</div>
