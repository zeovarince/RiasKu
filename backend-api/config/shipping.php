<?php

return [
    'origin_city' => env('SHIPPING_ORIGIN_CITY', 'Surabaya'),
    'default_weight_grams' => (int) env('SHIPPING_DEFAULT_WEIGHT_GRAMS', 500),
    'packaging_weight_grams' => (int) env('SHIPPING_PACKAGING_WEIGHT_GRAMS', 0),
    'volumetric_divisor_cm3_per_kg' => (int) env('SHIPPING_VOLUMETRIC_DIVISOR', 6000),
    'default_weight_rule' => [
        'min_billable_weight_grams' => (int) env('SHIPPING_DEFAULT_MIN_BILLABLE_WEIGHT_GRAMS', 1000),
        'rounding_step_grams' => (int) env('SHIPPING_DEFAULT_ROUNDING_STEP_GRAMS', 1000),
    ],
    'courier_weight_rules' => [
        'jne' => [
            'min_billable_weight_grams' => (int) env('SHIPPING_JNE_MIN_BILLABLE_WEIGHT_GRAMS', 1000),
            'rounding_step_grams' => (int) env('SHIPPING_JNE_ROUNDING_STEP_GRAMS', 1000),
        ],
        'j&t' => [
            'min_billable_weight_grams' => (int) env('SHIPPING_JNT_MIN_BILLABLE_WEIGHT_GRAMS', 1000),
            'rounding_step_grams' => (int) env('SHIPPING_JNT_ROUNDING_STEP_GRAMS', 1000),
        ],
    ],
    'free_shipping_min_subtotal' => (int) env('SHIPPING_FREE_MIN_SUBTOTAL', 0),
    'cod_cities' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SHIPPING_COD_CITIES', 'Surabaya'))
    ))),
];
