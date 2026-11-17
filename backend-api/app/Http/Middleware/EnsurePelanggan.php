<?php

namespace App\Http\Middleware;

use App\Models\Pelanggan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePelanggan
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() instanceof Pelanggan, 403, 'Akses hanya untuk pelanggan.');

        return $next($request);
    }
}
