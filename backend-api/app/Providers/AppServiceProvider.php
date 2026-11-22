<?php

namespace App\Providers;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\TarifPengiriman;
use App\Models\ZonaPengiriman;
use App\Services\AiCatalogContextService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $flushAiCatalog = static function (): void {
            DB::afterCommit(static function (): void {
                AiCatalogContextService::flush();
            });
        };

        foreach ([Produk::class, ProdukVarian::class, Kategori::class, TarifPengiriman::class, ZonaPengiriman::class] as $modelClass) {
            $modelClass::saved($flushAiCatalog);
            $modelClass::updated($flushAiCatalog);
            $modelClass::deleted($flushAiCatalog);
        }

        $resolveAiLimiterKey = static function (Request $request): string {
            $sanctumId = Auth::guard('sanctum')->id();

            if ($sanctumId !== null) {
                return 'cust:'.$sanctumId;
            }

            return 'ip:'.(string) $request->ip();
        };

        RateLimiter::for('ai-chat', static function (Request $request) use ($resolveAiLimiterKey): Limit {
            return Limit::perMinute(20)->by('ai-chat|'.$resolveAiLimiterKey($request));
        });

        RateLimiter::for('ai-read', static function (Request $request) use ($resolveAiLimiterKey): Limit {
            return Limit::perMinute(60)->by('ai-read|'.$resolveAiLimiterKey($request));
        });

        RateLimiter::for('ai-write', static function (Request $request) use ($resolveAiLimiterKey): Limit {
            return Limit::perMinute(30)->by('ai-write|'.$resolveAiLimiterKey($request));
        });

        RateLimiter::for('order-track', static function (Request $request): Limit {
            return Limit::perMinute(30)->by('order-track|'.(string) $request->ip());
        });
    }
}
