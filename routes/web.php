<?php

use Illuminate\Support\Facades\Route;

Route::post(
    config('singpass-login.post_singpass_callback_url'),
    config('singpass-login.post_singpass_callback_controller')
)->name('singpass.callback');

Route::get(
    config('singpass-login.get_jwks_endpoint_url'),
    config('singpass-login.get_jwks_endpoint_controller')
)->name('singpass.jwks');