{{--
    Default route action for the federated ( React ) flavor of
    `PluginServiceProvider::registerAdminPage()`.

    A `component`-only admin page has no server-rendered body of its own — the
    real UI is a Module Federation module only the host's federation runtime can
    resolve. This layout renders inside the framework's admin chrome
    (`cms::admin.layouts.app`) and emits a single mount point,
    `<div data-cms-federated-module="…">`, for that runtime to hydrate.

    The framework does not own the frontend and ships no federation runtime, so
    a host is responsible for hydrating the mount. There are two supported ways:

    1. Scan for `data-cms-federated-module` in the host front end and mount the
       named module into the div, replacing the fallback notice below.
    2. Override the `ap.cmsFramework.admin.federatedPageAction` filter to bridge
       to the host's own runtime ( e.g. an Inertia `plugins/<remote>/<page>`
       response ), in which case this file never renders. Inertia-based hosts
       such as Keystone CMS take this path.

    Until a host hydrates the mount, the fallback notice renders in place of a
    blank page, so an operator sees a clear "needs a host runtime" message rather
    than a silent dead div. See `docs/plugin-authoring.md`.

    Hosts that only want to restyle the shell can publish it with

        php artisan vendor:publish --tag=cms-views

    which writes to `resources/views/vendor/cms/`, where Laravel resolves it
    ahead of this copy.

    Variables: `component` ( the federation identifier, rendered into a data
    attribute ) and `title` ( optional, plain text ).

    @since 2.9.0
--}}
@extends( 'cms::admin.layouts.app' )

@section( 'title', $title ?? __( 'Admin' ) )

@push( 'styles' )
	<style>
		.cms-admin__federated-fallback { max-width: 40rem; padding: 1rem 1.25rem; border: 1px solid rgba( 127, 127, 127, .3 ); border-radius: .5rem; opacity: .85; }
	</style>
@endpush

@section( 'content' )
	<div data-cms-federated-module="{{ $component }}">
		<p class="cms-admin__federated-fallback" role="status">
			{{ __( 'This admin page loads the federated module ":component", which the host application must hydrate. If you are seeing this message, no host federation runtime has mounted the module yet.', ['component' => $component] ) }}
		</p>
	</div>
@endsection
