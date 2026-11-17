<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
  return response()->json([
    'message' => 'API Admin Dashboard',
  ]);
});