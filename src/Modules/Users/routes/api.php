<?php

use Illuminate\Support\Facades\Route;
use ArtisanPackUI\CMSFramework\Modules\Users\Http\Controllers\UserController;

Route::apiResource('users', UserController::class);