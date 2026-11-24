<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PPL-24: segarkan skor best seller agar penjualan di luar jendela 90 hari
// berhenti memengaruhi peringkat walaupun tidak ada transaksi baru.
Schedule::command('best-seller:refresh')->dailyAt('02:00');
