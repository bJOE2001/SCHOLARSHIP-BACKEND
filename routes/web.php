<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Scholarship API',
        'health' => url('/api/health'),
    ]);
});
