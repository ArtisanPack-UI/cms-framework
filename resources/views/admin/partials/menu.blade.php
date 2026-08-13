{{--
    Renders one level of the structured admin menu.

    Nodes carrying an `items` key are sections (a label plus children); every
    other node is a link, optionally with `subItems`. A node with
    `showInMenu => false` is routed but hidden, per `addSubPage()`'s contract —
    `getAdminMenu()` stores the flag but does not act on it, so the renderer
    is what has to honor it. URLs arrive already sanitized by
    `AdminMenuManager`, and Blade escapes them again here.

    @param array $menu The menu level to render.

    @since 2.8.0
--}}
@if ( ! empty( $menu ) )
	<ul class="cms-admin__menu">
		@foreach ( $menu as $slug => $node )
			@continue( false === ( $node['showInMenu'] ?? true ) )
			@php
				$children = $node['items'] ?? $node['subItems'] ?? [];
			@endphp
			<li>
				@if ( isset( $node['items'] ) )
					<span class="cms-admin__section-title">{{ $node['title'] ?? $slug }}</span>
				@else
					<a href="{{ $node['url'] ?? '#' }}">{{ $node['label'] ?? $node['title'] ?? $slug }}</a>
				@endif

				@if ( ! empty( $children ) )
					@include( 'cms::admin.partials.menu', ['menu' => $children] )
				@endif
			</li>
		@endforeach
	</ul>
@endif
