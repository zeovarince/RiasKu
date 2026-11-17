<?php

namespace Database\Seeders;

use App\Models\TarifPengiriman;
use App\Models\ZonaPengiriman;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        // District/postal reference derived from Open Admin Data (CC-BY-4.0):
        // https://github.com/open-admin-data/indonesia-administrative-divisions
        // TODO: Verify these per-kilogram estimates against current official JNE/J&T rates.
        $cities = [
            'Surabaya' => [
                'rates' => [
                    'JNE' => [
                        'REG' => [9000, 1, 2],
                        'YES' => [14000, 1, 1],
                        'OKE' => [8000, 2, 3],
                    ],
                    'J&T' => [
                        'EZ' => [8500, 1, 2],
                    ],
                ],
                'districts' => [
                    'Asemrowo' => '60182',
                    'Benowo' => '60191',
                    'Bubutan' => '60171',
                    'Bulak' => '60122',
                    'Dukuh Pakis' => '60224',
                    'Gayungan' => '60234',
                    'Genteng' => '60271',
                    'Gubeng' => '60281',
                    'Gunung Anyar' => '60293',
                    'Jambangan' => '60232',
                    'Karang Pilang' => '60221',
                    'Kenjeran' => '60126',
                    'Krembangan' => '60175',
                    'Lakarsantri' => '60211',
                    'Mulyorejo' => '60112',
                    'Pabean Cantian' => '60161',
                    'Pakal' => '60196',
                    'Rungkut' => '60295',
                    'Sambikerep' => '60216',
                    'Sawahan' => '60251',
                    'Semampir' => '60151',
                    'Simokerto' => '60141',
                    'Suko Manunggal' => '60187',
                    'Sukolilo' => '60117',
                    'Tambaksari' => '60131',
                    'Tandes' => '60185',
                    'Tegalsari' => '60261',
                    'Tenggilis Mejoyo' => '60291',
                    'Wiyung' => '60227',
                    'Wonocolo' => '60236',
                    'Wonokromo' => '60241',
                ],
            ],
            'Sidoarjo' => [
                'rates' => [
                    'JNE' => [
                        'REG' => [10000, 1, 2],
                        'YES' => [15000, 1, 1],
                        'OKE' => [9000, 2, 3],
                    ],
                    'J&T' => [
                        'EZ' => [9500, 1, 2],
                    ],
                ],
                'districts' => [
                    'Balong Bendo' => '61263',
                    'Buduran' => '61252',
                    'Candi' => '61271',
                    'Gedangan' => '61254',
                    'Jabon' => '61276',
                    'Krembung' => '61275',
                    'Krian' => '61262',
                    'Porong' => '61274',
                    'Prambon' => '61262',
                    'Sedati' => '61253',
                    'Sidoarjo' => '61211',
                    'Sukodono' => '61258',
                    'Taman' => '61257',
                    'Tanggulangin' => '61272',
                    'Tarik' => '61265',
                    'Tulangan' => '61273',
                    'Waru' => '61256',
                    'Wonoayu' => '61261',
                ],
            ],
            'Gresik' => [
                'rates' => [
                    'JNE' => [
                        'REG' => [11000, 1, 2],
                        'YES' => [16000, 1, 1],
                        'OKE' => [10000, 2, 3],
                    ],
                    'J&T' => [
                        'EZ' => [10500, 1, 2],
                    ],
                ],
                'districts' => [
                    'Balongpanggang' => '61173',
                    'Benjeng' => '61172',
                    'Bungah' => '61152',
                    'Cerme' => '61171',
                    'Driyorejo' => '61177',
                    'Duduksampeyan' => '61162',
                    'Dukun' => '61155',
                    'Gresik' => '61111',
                    'Kebomas' => '61121',
                    'Kedamean' => '61175',
                    'Manyar' => '61151',
                    'Menganti' => '61174',
                    'Panceng' => '61156',
                    'Sangkapura' => '61181',
                    'Sidayu' => '61152',
                    'Tambak' => '61182',
                    'Ujungpangkah' => '61154',
                    'Wringinanom' => '61176',
                ],
            ],
            'Bangkalan' => [
                'rates' => [
                    'JNE' => [
                        'REG' => [15000, 1, 2],
                        'YES' => [22000, 1, 1],
                        'OKE' => [13000, 2, 3],
                    ],
                    'J&T' => [
                        'EZ' => [14000, 1, 2],
                    ],
                ],
                'districts' => [
                    'Arosbaya' => '69151',
                    'Bangkalan' => '69112',
                    'Blega' => '69174',
                    'Burneh' => '69171',
                    'Galis' => '69173',
                    'Geger' => '69152',
                    'Kamal' => '69162',
                    'Klampis' => '69153',
                    'Kokop' => '69155',
                    'Konang' => '69175',
                    'Kwanyar' => '69164',
                    'Labang' => '69163',
                    'Modung' => '69164',
                    'Sepulu' => '69154',
                    'Socah' => '69161',
                    'Tanah Merah' => '69172',
                    'Tanjungbumi' => '69156',
                    'Tragah' => '69165',
                ],
            ],
        ];

        ZonaPengiriman::query()
            ->whereIn('city', array_keys($cities))
            ->whereNull('district')
            ->whereNull('postal_code')
            ->update(['is_active' => false]);

        TarifPengiriman::query()
            ->whereNotIn('courier', ['JNE', 'J&T'])
            ->update(['is_active' => false]);

        foreach ($cities as $city => $configuration) {
            foreach ($configuration['districts'] as $district => $postalCode) {
                $zone = ZonaPengiriman::query()->updateOrCreate(
                    [
                        'city' => $city,
                        'district' => $district,
                        'postal_code' => $postalCode,
                    ],
                    [
                        'province' => 'Jawa Timur',
                        'is_active' => true,
                    ],
                );

                foreach ($configuration['rates'] as $courier => $services) {
                    foreach ($services as $service => [$price, $minDays, $maxDays]) {
                        TarifPengiriman::query()->updateOrCreate(
                            [
                                'zona_pengiriman_id' => $zone->id,
                                'courier' => $courier,
                                'service' => $service,
                            ],
                            [
                                'price_per_kg' => $price,
                                'etd_min_days' => $minDays,
                                'etd_max_days' => $maxDays,
                                'is_active' => true,
                            ],
                        );
                    }
                }
            }
        }
    }
}
