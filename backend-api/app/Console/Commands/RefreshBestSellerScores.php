<?php

namespace App\Console\Commands;

use App\Services\BestSellerService;
use Illuminate\Console\Command;

class RefreshBestSellerScores extends Command
{
    protected $signature = 'best-seller:refresh';

    protected $description = 'Hitung ulang skor best seller seluruh produk (jendela penjualan 90 hari).';

    public function handle(BestSellerService $bestSellers): int
    {
        $result = $bestSellers->refresh();

        $this->info("Skor best seller diperbarui untuk {$result['updated']} produk pada {$result['calculated_at']}.");

        return self::SUCCESS;
    }
}
