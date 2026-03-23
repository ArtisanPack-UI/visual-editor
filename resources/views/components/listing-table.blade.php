{{--
 * Listing Table Component View
 *
 * Renders a sortable data table with row selection and per-row actions.
 * Used by template, template part, and pattern listing pages.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Components
 *
 * @since      1.0.0
 --}}

<div class="overflow-x-auto">
	<table class="table table-zebra w-full">
		<thead>
			<tr>
				<th class="w-10">
					<input
						type="checkbox"
						class="checkbox checkbox-sm"
						wire:click="toggleSelectAll"
						@checked( count( $selected ) > 0 && count( $selected ) === count( $rows ) )
					/>
				</th>
				@foreach ( $columns as $column )
					<th
						@if ( $column['sortable'] ?? false )
							class="cursor-pointer select-none hover:bg-base-200 transition-colors"
							wire:click="sort( '{{ $column['key'] }}' )"
						@endif
					>
						<div class="flex items-center gap-1">
							{{ $column['label'] }}
							@if ( ( $column['sortable'] ?? false ) && $sortField === $column['key'] )
								@if ( 'asc' === $sortDirection )
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
								@else
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
								@endif
							@endif
						</div>
					</th>
				@endforeach
				<th class="w-28 text-right">{{ __( 'visual-editor::ve.listing_actions' ) }}</th>
			</tr>
		</thead>
		<tbody>
			@forelse ( $rows as $row )
				<tr wire:key="row-{{ $row->id }}" class="hover">
					<td>
						<input
							type="checkbox"
							class="checkbox checkbox-sm"
							value="{{ $row->id }}"
							wire:model.live="selected"
						/>
					</td>
					{{ $slot }}
					<td class="text-right">
						<div class="flex items-center justify-end gap-1">
							<button
								class="btn btn-ghost btn-xs"
								wire:click="duplicate( {{ $row->id }} )"
								title="{{ __( 'visual-editor::ve.listing_duplicate' ) }}"
							>
								<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
							</button>
							@if ( $row->is_locked ?? false )
								<button
									class="btn btn-ghost btn-xs"
									wire:click="toggleLock( {{ $row->id }} )"
									title="{{ __( 'visual-editor::ve.listing_unlock' ) }}"
								>
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
								</button>
							@else
								<button
									class="btn btn-ghost btn-xs"
									wire:click="toggleLock( {{ $row->id }} )"
									title="{{ __( 'visual-editor::ve.listing_lock' ) }}"
								>
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
								</button>
							@endif
							<button
								class="btn btn-ghost btn-xs text-error"
								wire:click="delete( {{ $row->id }} )"
								wire:confirm="{{ __( 'visual-editor::ve.listing_delete_confirm' ) }}"
								title="{{ __( 'visual-editor::ve.listing_delete' ) }}"
							>
								<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
							</button>
						</div>
					</td>
				</tr>
			@empty
				<tr>
					<td colspan="{{ count( $columns ) + 2 }}" class="text-center py-8 text-base-content/50">
						{{ __( 'visual-editor::ve.listing_no_results' ) }}
					</td>
				</tr>
			@endforelse
		</tbody>
	</table>
</div>
