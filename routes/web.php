<?php

use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    // SingPass Login
    Route::get(
        config('singpass-login.get_authentication_endpoint_url'),
        config('singpass-login.get_authentication_endpoint_controller')
    )->name('singpass.login');

    Route::get(
        config('singpass-login.post_singpass_callback_url'),
        config('singpass-login.post_singpass_callback_controller')
    )->name('singpass.callback');

    // MyInfo
    Route::get(
        config('singpass-login.get_myinfo_authentication_endpoint_url'),
        config('singpass-login.get_myinfo_authentication_endpoint_controller')
    )->name('myinfo.login');

    Route::get(
        config('singpass-login.post_myinfo_callback_url'),
        config('singpass-login.post_myinfo_callback_controller')
    )->name('myinfo.callback');

    // JWKS
    Route::get(
        config('singpass-login.get_jwks_endpoint_url'),
        config('singpass-login.get_jwks_endpoint_controller')
    )->name('singpass.jwks');
});
