{{--
    Canonical admin layout for Blade admin pages.

    This is the layout `PluginServiceProvider::registerAdminPage()`'s Blade
    flavor is documented against, and it is deliberately plain: the framework
    is front-end agnostic and ships no CSS build, so hosts are expected to
    replace it with their own chrome. Publish it with

        php artisan vendor:publish --tag=cms-views

    which writes to `resources/views/vendor/cms/`, where Laravel resolves it
    ahead of this file — a plugin extending `cms::admin.layouts.app` then
    renders inside the host's chrome without changing a line of the plugin.

    Sections: `title` (rendered escaped — plain text only) and `content`
    (rendered as markup). Stacks: `styles`, `scripts`.

    @since 2.8.0
--}}
<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	{{-- Escaped rather than `@yield`: a page's title is the one section most
	     likely to interpolate a record name or other stored value, and `<title>`
	     never needs markup. --}}
	<title>{{ trim( $__env->yieldContent( 'title', __( 'Admin' ) ) ) }} &middot; {{ config( 'app.name' ) }}</title>
	<style>
		:root { color-scheme: light dark; }
		body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; line-height: 1.5; }
		.cms-admin { display: flex; min-height: 100vh; align-items: stretch; }
		.cms-admin__sidebar { flex: 0 0 16rem; padding: 1.5rem 1rem; border-right: 1px solid rgba(127, 127, 127, .3); }
		.cms-admin__brand { display: block; margin-bottom: 1.5rem; font-weight: 700; text-decoration: none; color: inherit; }
		.cms-admin__section-title { margin: 1.25rem 0 .5rem; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; opacity: .7; }
		.cms-admin__menu { margin: 0; padding: 0; list-style: none; }
		.cms-admin__menu .cms-admin__menu { margin-left: 1rem; }
		.cms-admin__menu a { display: block; padding: .25rem 0; text-decoration: none; color: inherit; }
		.cms-admin__menu a:hover, .cms-admin__menu a:focus { text-decoration: underline; }
		.cms-admin__content { flex: 1 1 auto; min-width: 0; padding: 1.5rem 2rem; }
	</style>
	@stack( 'styles' )
</head>
<body>
<div class="cms-admin">
	<aside class="cms-admin__sidebar">
		<a class="cms-admin__brand" href="{{ url( '/admin' ) }}">{{ config( 'app.name' ) }}</a>

		@include( 'cms::admin.partials.menu', ['menu' => apGetAdminMenu()] )
	</aside>

	<main class="cms-admin__content">
		@yield( 'content' )
	</main>
</div>
@stack( 'scripts' )
</body>
</html>
