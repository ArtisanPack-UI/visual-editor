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

@php
	$easingMap = [
		'ease-in-out' => 'ease-in-out',
		'ease-in'     => 'ease-in',
		'ease-out'    => 'ease-out',
		'linear'      => 'ease-linear',
	];
	$easingClass = $easingMap[ $easing ] ?? 'ease-in-out';

	$transitionClasses = match ( $animation ) {
		'fade' => [
			'enter'       => "transition-opacity {$easingClass}",
			'enter-start' => 'opacity-0',
			'enter-end'   => 'opacity-100',
			'leave'       => "transition-opacity {$easingClass}",
			'leave-start' => 'opacity-100',
			'leave-end'   => 'opacity-0',
		],
		'slide-up' => [
			'enter'       => "transition {$easingClass}",
			'enter-start' => 'opacity-0 translate-y-2',
			'enter-end'   => 'opacity-100 translate-y-0',
			'leave'       => "transition {$easingClass}",
			'leave-start' => 'opacity-100 translate-y-0',
			'leave-end'   => 'opacity-0 translate-y-2',
		],
		'slide-down' => [
			'enter'       => "transition {$easingClass}",
			'enter-start' => 'opacity-0 -translate-y-2',
			'enter-end'   => 'opacity-100 translate-y-0',
			'leave'       => "transition {$easingClass}",
			'leave-start' => 'opacity-100 translate-y-0',
			'leave-end'   => 'opacity-0 -translate-y-2',
		],
		'slide-left' => [
			'enter'       => "transition {$easingClass}",
			'enter-start' => 'opacity-0 -translate-x-2',
			'enter-end'   => 'opacity-100 translate-x-0',
			'leave'       => "transition {$easingClass}",
			'leave-start' => 'opacity-100 translate-x-0',
			'leave-end'   => 'opacity-0 -translate-x-2',
		],
		'slide-right' => [
			'enter'       => "transition {$easingClass}",
			'enter-start' => 'opacity-0 translate-x-2',
			'enter-end'   => 'opacity-100 translate-x-0',
			'leave'       => "transition {$easingClass}",
			'leave-start' => 'opacity-100 translate-x-0',
			'leave-end'   => 'opacity-0 translate-x-2',
		],
		'scale' => [
			'enter'       => "transition {$easingClass}",
			'enter-start' => 'opacity-0 scale-95',
			'enter-end'   => 'opacity-100 scale-100',
			'leave'       => "transition {$easingClass}",
			'leave-start' => 'opacity-100 scale-100',
			'leave-end'   => 'opacity-0 scale-95',
		],
		default => [
			'enter'       => "transition-opacity {$easingClass}",
			'enter-start' => 'opacity-0',
			'enter-end'   => 'opacity-100',
			'leave'       => "transition-opacity {$easingClass}",
			'leave-start' => 'opacity-100',
			'leave-end'   => 'opacity-0',
		],
	};
@endphp

<div
	id="{{ $uuid }}"
	x-data="{ visible: {{ Js::from( $show ) }} }"
	x-modelable="visible"
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
			style="--tw-duration: {{ $duration }}ms"
			x-transition:enter="{{ $transitionClasses['enter'] }}"
			x-transition:enter-start="{{ $transitionClasses['enter-start'] }}"
			x-transition:enter-end="{{ $transitionClasses['enter-end'] }}"
			x-transition:leave="{{ $transitionClasses['leave'] }}"
			x-transition:leave-start="{{ $transitionClasses['leave-start'] }}"
			x-transition:leave-end="{{ $transitionClasses['leave-end'] }}"
		>
			{{ $slot }}
		</div>
	@endif
</div>
