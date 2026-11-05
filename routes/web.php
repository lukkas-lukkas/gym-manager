<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome', [
    'laravelVersion' => app()->version(),
]);
