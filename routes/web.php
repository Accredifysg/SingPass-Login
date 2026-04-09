<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    // JWKS (shared across all providers — always registered)
    Route::get(
        config('singpass-login.get_jwks_endpoint_url'),
        config('singpass-login.get_jwks_endpoint_controller')
    )->name('singpass.jwks');

    if (config('singpass-login.enable_default_singpass_routes')) {
        // SingPass Login
        Route::get(
            config('singpass-login.get_authentication_endpoint_url'),
            config('singpass-login.get_authentication_endpoint_controller')
        )->name('singpass.login');

        Route::get(
            config('singpass-login.post_singpass_callback_url'),
            config('singpass-login.post_singpass_callback_controller')
        )->name('singpass.callback');
    }

    if (config('singpass-login.enable_default_myinfo_routes')) {
        // MyInfo
        Route::get(
            config('singpass-login.get_myinfo_authentication_endpoint_url'),
            config('singpass-login.get_myinfo_authentication_endpoint_controller')
        )->name('myinfo.login');

        Route::get(
            config('singpass-login.post_myinfo_callback_url'),
            config('singpass-login.post_myinfo_callback_controller')
        )->name('myinfo.callback');
    }

    if (config('corppass-login.enable_default_corppass_routes')) {
        // CorpPass
        Route::get(
            config('corppass-login.get_authentication_endpoint_url'),
            config('corppass-login.get_authentication_endpoint_controller')
        )->name('corppass.login');

        Route::get(
            config('corppass-login.post_corppass_callback_url'),
            config('corppass-login.post_corppass_callback_controller')
        )->name('corppass.callback');
    }
});
