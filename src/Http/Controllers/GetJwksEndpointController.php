<?php

namespace Accredifysg\SingPassLogin\Http\Controllers;

use Accredifysg\SingPassLogin\Exceptions\JwksInvalidException;
use Exception;
use Illuminate\Support\Facades\File;

class GetJwksEndpointController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            return response()->json(json_decode(File::get(storage_path('jwks/jwks.json')), true, 512, JSON_THROW_ON_ERROR));
        } catch (Exception) {
            throw new JwksInvalidException;
        }
    }
}