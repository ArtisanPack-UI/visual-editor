{{--
 * Animate Presence Component
 *
 * Wrapper component providing consistent enter/leave animations
 * with prefers-reduced-motion support.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<style>
	[data-ve-animate] {
		--ve-duration: {{ $duration }}ms;
		--ve-easing: {{ $easing }};
	}

	@media ( prefers-reduced-motion: reduce ) {
		[data-ve-animate] {
			--ve-duration: 0ms !important;
		}
	}

	/* Fade */
	.ve-enter-fade {
		transition: opacity var(--ve-duration) var(--ve-easing);
	}
	.ve-enter-fade-from {
		opacity: 0;
	}
	.ve-enter-fade-to {
		opacity: 1;
	}
	.ve-leave-fade {
		transition: opacity var(--ve-duration) var(--ve-easing);
	}
	.ve-leave-fade-from {
		opacity: 1;
	}
	.ve-leave-fade-to {
		opacity: 0;
	}

	/* Slide Up */
	.ve-enter-slide-up {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-enter-slide-up-from {
		opacity: 0;
		transform: translateY(0.5rem);
	}
	.ve-enter-slide-up-to {
		opacity: 1;
		transform: translateY(0);
	}
	.ve-leave-slide-up {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-leave-slide-up-from {
		opacity: 1;
		transform: translateY(0);
	}
	.ve-leave-slide-up-to {
		opacity: 0;
		transform: translateY(0.5rem);
	}

	/* Slide Down */
	.ve-enter-slide-down {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-enter-slide-down-from {
		opacity: 0;
		transform: translateY(-0.5rem);
	}
	.ve-enter-slide-down-to {
		opacity: 1;
		transform: translateY(0);
	}
	.ve-leave-slide-down {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-leave-slide-down-from {
		opacity: 1;
		transform: translateY(0);
	}
	.ve-leave-slide-down-to {
		opacity: 0;
		transform: translateY(-0.5rem);
	}

	/* Slide Left */
	.ve-enter-slide-left {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-enter-slide-left-from {
		opacity: 0;
		transform: translateX(-0.5rem);
	}
	.ve-enter-slide-left-to {
		opacity: 1;
		transform: translateX(0);
	}
	.ve-leave-slide-left {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-leave-slide-left-from {
		opacity: 1;
		transform: translateX(0);
	}
	.ve-leave-slide-left-to {
		opacity: 0;
		transform: translateX(-0.5rem);
	}

	/* Slide Right */
	.ve-enter-slide-right {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-enter-slide-right-from {
		opacity: 0;
		transform: translateX(0.5rem);
	}
	.ve-enter-slide-right-to {
		opacity: 1;
		transform: translateX(0);
	}
	.ve-leave-slide-right {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-leave-slide-right-from {
		opacity: 1;
		transform: translateX(0);
	}
	.ve-leave-slide-right-to {
		opacity: 0;
		transform: translateX(0.5rem);
	}

	/* Scale */
	.ve-enter-scale {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-enter-scale-from {
		opacity: 0;
		transform: scale(0.95);
	}
	.ve-enter-scale-to {
		opacity: 1;
		transform: scale(1);
	}
	.ve-leave-scale {
		transition: opacity var(--ve-duration) var(--ve-easing), transform var(--ve-duration) var(--ve-easing);
	}
	.ve-leave-scale-from {
		opacity: 1;
		transform: scale(1);
	}
	.ve-leave-scale-to {
		opacity: 0;
		transform: scale(0.95);
	}
</style>

<div
	id="{{ $uuid }}"
	x-data="{ visible: {{ Js::from( $show ) }} }"
	data-ve-animate="{{ $animation }}"
	{{ $attributes->merge( [ 'class' => '' ] ) }}
>
	@if ( 'collapse' === $animation )
		<div
			x-show="visible"
			x-collapse.duration.{{ $duration }}ms
		>
			{{ $slot }}
		</div>
	@else
		<div
			x-show="visible"
			x-transition:enter="ve-enter-{{ $animation }}"
			x-transition:enter-start="ve-enter-{{ $animation }}-from"
			x-transition:enter-end="ve-enter-{{ $animation }}-to"
			x-transition:leave="ve-leave-{{ $animation }}"
			x-transition:leave-start="ve-leave-{{ $animation }}-from"
			x-transition:leave-end="ve-leave-{{ $animation }}-to"
		>
			{{ $slot }}
		</div>
	@endif
</div>
