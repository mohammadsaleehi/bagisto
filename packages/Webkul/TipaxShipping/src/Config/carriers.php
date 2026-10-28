<?php

return [
    'tipax' => [
        'code'        => 'tipax',
        'title'       => 'ارسال تیپاکس',
        'description' => 'هزینه ارسال بر اساس استعلام آنلاین تیپاکس محاسبه می‌شود.',
        'active'      => false,
        'class'       => Webkul\TipaxShipping\Carriers\Tipax::class,
    ],
];
