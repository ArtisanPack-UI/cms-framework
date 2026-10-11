<?php

declare( strict_types = 1 );

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Page Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to every admin page registered through the
    | AdminPageManager (including pages added by plugins). Set this to match
    | the stack your own admin routes use, e.g. add `verified` or your
    | two-factor middleware aliases, so plugin pages can't be reached by users
    | who haven't completed those checks. Each page's `can:{capability}`
    | middleware is always appended on top of this stack.
    |
    | Keep an authentication middleware (`auth` or `auth:<guard>`) in this
    | list. An empty or invalid list falls back to `web` + `auth`.
    |
    | The `ap.cmsFramework.admin.middleware` filter can modify this at runtime.
    */
    'middleware' => [
        'web',
        'auth',
    ],
];
