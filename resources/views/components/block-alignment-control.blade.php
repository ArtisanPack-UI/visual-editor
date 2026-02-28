@php
	$alignmentIcons = [
		'none' => '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="1" stroke="currentColor" stroke-width="2" /></svg>',
		'left' => '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="1" y="5" width="12" height="14" rx="1" fill="currentColor" /><line x1="23" y1="5" x2="23" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>',
		'center' => '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><line x1="1" y1="5" x2="1" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" /><rect x="6" y="5" width="12" height="14" rx="1" fill="currentColor" /><line x1="23" y1="5" x2="23" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>',
		'right' => '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><line x1="1" y1="5" x2="1" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" /><rect x="11" y="5" width="12" height="14" rx="1" fill="currentColor" /></svg>',
		'wide' => '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><line x1="1" y1="5" x2="1" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" /><rect x="3" y="8" width="18" height="8" rx="1" fill="currentColor" /><line x1="23" y1="5" x2="23" y2="19" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>',
		'full' => '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="1" y="5" width="22" height="14" rx="1" fill="currentColor" /></svg>',
	];

	$alignmentLabels = [
		'none'   => __( 'visual-editor::ve.align_none' ),
		'left'   => __( 'visual-editor::ve.align_left' ),
		'center' => __( 'visual-editor::ve.align_center' ),
		'right'  => __( 'visual-editor::ve.align_right' ),
		'wide'   => __( 'visual-editor::ve.align_wide' ),
		'full'   => __( 'visual-editor::ve.align_full' ),
	];
@endphp

<div
	x-data="{ open: false }"
	{{ $attributes->merge( [ 'class' => 'relative inline-flex' ] ) }}
>
	{{-- Trigger button --}}
	<button
		type="button"
		class="btn btn-ghost btn-xs btn-square"
		x-on:click="open = ! open"
		:aria-expanded="open"
		aria-haspopup="listbox"
		aria-label="{{ $label ?? __( 'visual-editor::ve.block_alignment' ) }}"
		title="{{ $label ?? __( 'visual-editor::ve.block_alignment' ) }}"
	>
		@foreach ( $resolvedOptions as $opt )
			<span x-show="'{{ $opt }}' === '{{ $value }}'" class="flex items-center justify-center">
				{!! $alignmentIcons[ $opt ] ?? $alignmentIcons['none'] !!}
			</span>
		@endforeach
	</button>

	{{-- Dropdown --}}
	<div
		x-show="open"
		x-on:click.outside="open = false"
		x-transition
		class="absolute left-0 top-full mt-1 w-44 rounded-lg border border-base-300 bg-base-100 shadow-lg py-1 z-50"
		role="listbox"
		aria-label="{{ $label ?? __( 'visual-editor::ve.block_alignment' ) }}"
	>
		@foreach ( $resolvedOptions as $opt )
			<button
				type="button"
				class="flex w-full items-center gap-2 px-3 py-1.5 text-sm hover:bg-base-200 {{ $opt === $value ? 'bg-primary/10 text-primary' : '' }}"
				role="option"
				aria-selected="{{ $opt === $value ? 'true' : 'false' }}"
				x-on:click="open = false; $dispatch( 've-block-alignment-change', { value: '{{ $opt }}' } )"
			>
				<span class="flex items-center justify-center shrink-0">
					{!! $alignmentIcons[ $opt ] ?? $alignmentIcons['none'] !!}
				</span>
				<span>{{ $alignmentLabels[ $opt ] ?? ucfirst( $opt ) }}</span>
			</button>
		@endforeach
	</div>
</div>
