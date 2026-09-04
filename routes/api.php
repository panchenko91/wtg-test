<?php

use App\Http\Controllers\API\ImportController;

\Illuminate\Support\Facades\Route::post('imports', [ImportController::class, 'import']);
\Illuminate\Support\Facades\Route::get('imports/{import}', [ImportController::class, 'show']);

