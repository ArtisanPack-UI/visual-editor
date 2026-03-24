{{--
 * Template Listing Page
 *
 * Paginated, searchable listing of templates with table and grid views.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Views\Livewire\SiteEditor
 *
 * @since      1.0.0
 --}}

@php
	$prefix = (string) config( 'artisanpack.visual-editor.site_editor.route_prefix', 'site-editor' );
@endphp

<div class="min-h-screen bg-base-200">
	<div class="max-w-6xl mx-auto px-6 py-12">
		{{-- Back link --}}
		<a href="{{ url( $prefix ) }}" class="inline-flex items-center gap-1 text-sm text-base-content/60 hover:text-primary transition-colors mb-6" wire:navigate>
			<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
			{{ __( 'visual-editor::ve.hub_back' ) }}
		</a>

		{{-- Header --}}
		<div class="flex items-center justify-between mb-8">
			<div>
				<h1 class="text-3xl font-bold text-base-content">
					{{ __( 'visual-editor::ve.template_listing_title' ) }}
				</h1>
				<p class="mt-2 text-base-content/60">
					{{ __( 'visual-editor::ve.template_listing_description' ) }}
				</p>
			</div>
			<a href="{{ route( 'visual-editor.templates.create' ) }}" class="btn btn-primary" wire:navigate>
				{{ __( 'visual-editor::ve.template_listing_new' ) }}
			</a>
		</div>

		{{-- Toolbar: search, filters, view toggle --}}
		<div class="flex flex-wrap items-center gap-3 mb-6">
			{{-- Search --}}
			<div class="flex-1 min-w-48">
				<input
					type="text"
					wire:model.live.debounce.300ms="search"
					placeholder="{{ __( 'visual-editor::ve.listing_search_placeholder' ) }}"
					class="input input-bordered input-sm w-full"
				/>
			</div>

			{{-- Status filter --}}
			<select wire:model.live="filterStatus" class="select select-bordered select-sm">
				<option value="">{{ __( 'visual-editor::ve.listing_filter_all_statuses' ) }}</option>
				<option value="active">{{ __( 'visual-editor::ve.listing_filter_active' ) }}</option>
				<option value="draft">{{ __( 'visual-editor::ve.listing_filter_draft' ) }}</option>
			</select>

			{{-- Type filter --}}
			<select wire:model.live="filterType" class="select select-bordered select-sm">
				<option value="">{{ __( 'visual-editor::ve.template_listing_filter_all_types' ) }}</option>
				<option value="page">page</option>
				<option value="post">post</option>
				<option value="custom">custom</option>
			</select>

			{{-- View toggle --}}
			<div class="join">
				<button
					wire:click="setViewMode( 'table' )"
					@class([ 'btn btn-sm join-item', 'btn-active' => 'table' === $viewMode ])
					title="{{ __( 'visual-editor::ve.listing_view_table' ) }}"
				>
					<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M10.875 12h-7.5m8.625 0h7.5M12 12v1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 15c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 0v1.5" /></svg>
				</button>
				<button
					wire:click="setViewMode( 'grid' )"
					@class([ 'btn btn-sm join-item', 'btn-active' => 'grid' === $viewMode ])
					title="{{ __( 'visual-editor::ve.listing_view_grid' ) }}"
				>
					<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" /></svg>
				</button>
			</div>
		</div>

		{{-- Bulk actions --}}
		@if ( count( $selected ) > 0 )
			<div class="flex items-center gap-3 mb-4 p-3 bg-base-100 rounded-lg border border-base-300">
				<span class="text-sm font-medium">
					{{ __( 'visual-editor::ve.listing_selected_count', [ 'count' => count( $selected ) ] ) }}
				</span>
				<button wire:click="bulkDelete" wire:confirm="{{ __( 'visual-editor::ve.listing_delete_confirm' ) }}" class="btn btn-error btn-xs">
					{{ __( 'visual-editor::ve.listing_bulk_delete' ) }}
				</button>
				<button wire:click="bulkChangeStatus( 'active' )" class="btn btn-success btn-xs">
					{{ __( 'visual-editor::ve.listing_filter_active' ) }}
				</button>
				<button wire:click="bulkChangeStatus( 'draft' )" class="btn btn-warning btn-xs">
					{{ __( 'visual-editor::ve.listing_filter_draft' ) }}
				</button>
			</div>
		@endif

		{{-- Table view --}}
		@if ( 'table' === $viewMode )
			<div class="overflow-x-auto">
				<table class="table table-zebra w-full">
					<thead>
						<tr>
							<th class="w-10">
								<input type="checkbox" class="checkbox checkbox-sm" wire:click="toggleSelectAll" @checked( count( $selected ) > 0 && count( $selected ) === $templates->count() ) />
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
						@forelse ( $templates as $template )
							<tr wire:key="row-{{ $template->id }}" class="hover">
								<td>
									<input type="checkbox" class="checkbox checkbox-sm" value="{{ $template->id }}" wire:model.live="selected" />
								</td>
								<td>{{ $template->name }}</td>
								<td>{{ ucfirst( $template->type ) }}</td>
								<td>
									<span @class([ 'badge badge-sm', 'badge-success' => 'active' === $template->status, 'badge-warning' => 'draft' === $template->status ])>
										{{ ucfirst( $template->status ) }}
									</span>
								</td>
								<td>{{ $template->updated_at ? ( is_string( $template->updated_at ) ? \Carbon\Carbon::parse( $template->updated_at )->diffForHumans() : $template->updated_at->diffForHumans() ) : '—' }}</td>
								<td class="text-right">
									<div class="flex items-center justify-end gap-1">
										<a href="{{ route( 'visual-editor.templates.edit', [ 'slug' => $template->slug ] ) }}" class="btn btn-ghost btn-xs" title="{{ __( 'visual-editor::ve.template_listing_edit' ) }}" wire:navigate>
											<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
										</a>
										@if ( is_numeric( $template->id ) )
										<button class="btn btn-ghost btn-xs" wire:click="duplicate( {{ $template->id }} )" title="{{ __( 'visual-editor::ve.listing_duplicate' ) }}">
											<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
										</button>
										@if ( $template->is_locked ?? false )
											<button class="btn btn-ghost btn-xs" wire:click="toggleLock( {{ $template->id }} )" title="{{ __( 'visual-editor::ve.listing_unlock' ) }}">
												<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
											</button>
										@else
											<button class="btn btn-ghost btn-xs" wire:click="toggleLock( {{ $template->id }} )" title="{{ __( 'visual-editor::ve.listing_lock' ) }}">
												<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
											</button>
										@endif
										<button class="btn btn-ghost btn-xs text-error" wire:click="delete( {{ $template->id }} )" wire:confirm="{{ __( 'visual-editor::ve.listing_delete_confirm' ) }}" title="{{ __( 'visual-editor::ve.listing_delete' ) }}">
											<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
										</button>
										@endif
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
		@else
			{{-- Grid view --}}
			<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
				@forelse ( $templates as $template )
					<div wire:key="card-{{ $template->id }}" class="group relative card bg-base-100 border border-base-300 shadow-sm hover:shadow-md hover:border-primary/30 transition-all">
						<div class="absolute top-3 left-3 z-10">
							<input type="checkbox" class="checkbox checkbox-sm" value="{{ $template->id }}" wire:model.live="selected" />
						</div>
						<div class="card-body p-5">
							<div class="w-full h-28 bg-base-200 rounded-lg mb-3 flex items-center justify-center">
								<svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
							</div>
							<div class="flex items-center gap-2">
								<h3 class="font-semibold text-base-content truncate flex-1">{{ $template->name }}</h3>
								<span @class([ 'badge badge-sm', 'badge-success' => 'active' === $template->status, 'badge-warning' => 'draft' === $template->status ])>
									{{ ucfirst( $template->status ) }}
								</span>
							</div>
							<p class="text-xs text-base-content/50 mt-1">{{ ucfirst( $template->type ) }}</p>
							<div class="card-actions justify-end mt-2 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
								<a href="{{ route( 'visual-editor.templates.edit', [ 'slug' => $template->slug ] ) }}" class="btn btn-ghost btn-xs" title="{{ __( 'visual-editor::ve.template_listing_edit' ) }}" wire:navigate>
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>
								</a>
								@if ( is_numeric( $template->id ) )
								<button class="btn btn-ghost btn-xs" wire:click="duplicate( {{ $template->id }} )" title="{{ __( 'visual-editor::ve.listing_duplicate' ) }}">
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" /></svg>
								</button>
								@if ( $template->is_locked ?? false )
									<button class="btn btn-ghost btn-xs" wire:click="toggleLock( {{ $template->id }} )" title="{{ __( 'visual-editor::ve.listing_unlock' ) }}">
										<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
									</button>
								@else
									<button class="btn btn-ghost btn-xs" wire:click="toggleLock( {{ $template->id }} )" title="{{ __( 'visual-editor::ve.listing_lock' ) }}">
										<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
									</button>
								@endif
								<button class="btn btn-ghost btn-xs text-error" wire:click="delete( {{ $template->id }} )" wire:confirm="{{ __( 'visual-editor::ve.listing_delete_confirm' ) }}" title="{{ __( 'visual-editor::ve.listing_delete' ) }}">
									<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
								</button>
								@endif
							</div>
						</div>
					</div>
				@empty
					<div class="col-span-full text-center py-12 text-base-content/50">
						{{ __( 'visual-editor::ve.listing_no_results' ) }}
					</div>
				@endforelse
			</div>
		@endif

		{{-- Pagination --}}
		<div class="mt-6">
			{{ $templates->links() }}
		</div>
	</div>
</div>
