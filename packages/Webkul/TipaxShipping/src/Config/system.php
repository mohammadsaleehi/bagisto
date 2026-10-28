<?php

return [
    [
        'key'   => 'sales.carriers.tipax',
        'name'  => 'Tipax Shipping',
        'info'  => 'استعلام آنلاین هزینه ارسال تیپاکس. ثبت مرسوله از این بخش انجام نمی‌شود.',
        'sort'  => 7,
        'fields' => [
            [
                'name'          => 'active',
                'title'         => 'فعال‌سازی ارسال تیپاکس',
                'type'          => 'boolean',
                'channel_based' => true,
                'locale_based'  => false,
            ],
            [
                'name'          => 'title',
                'title'         => 'عنوان روش ارسال',
                'type'          => 'text',
                'depends'       => 'active:1',
                'validation'    => 'required_if:active,1',
                'channel_based' => true,
                'locale_based'  => true,
            ],
            [
                'name'          => 'description',
                'title'         => 'توضیحات روش ارسال',
                'type'          => 'textarea',
                'depends'       => 'active:1',
                'channel_based' => true,
                'locale_based'  => true,
            ],
        ],
    ],
];
