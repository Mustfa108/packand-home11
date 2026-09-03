<?php

return [
    'required'  => 'حقل :attribute مطلوب.',
    'email'     => 'يجب أن يكون :attribute بريدًا إلكترونيًا صالحًا.',
    'unique'    => 'قيمة :attribute مستخدمة بالفعل.',
    'string'    => 'يجب أن يكون :attribute نصًا.',
    'integer'   => 'يجب أن يكون :attribute رقمًا صحيحًا.',
    'numeric'   => 'يجب أن يكون :attribute رقمًا.',
    'boolean'   => 'يجب أن تكون قيمة :attribute صحيحة أو خاطئة.',
    'array'     => 'يجب أن يكون :attribute مصفوفة.',

    'min' => [
        'string'  => 'يجب أن يكون :attribute على الأقل :min أحرف.',
        'numeric' => 'يجب أن تكون قيمة :attribute على الأقل :min.',
        'array'   => 'يجب أن يحتوي :attribute على الأقل على :min عناصر.',
    ],

    'max' => [
        'string'  => 'يجب ألا يتجاوز :attribute :max حرفًا.',
        'numeric' => 'يجب ألا تتجاوز قيمة :attribute :max.',
        'array'   => 'يجب ألا يحتوي :attribute على أكثر من :max عناصر.',
    ],

    'confirmed' => 'حقل :attribute وتأكيده غير متطابقَين.',

    'between' => [
        'numeric' => 'يجب أن تكون قيمة :attribute بين :min و :max.',
        'string'  => 'يجب أن يكون طول :attribute بين :min و :max أحرف.',
    ],

    'size' => [
        'numeric' => 'يجب أن تكون قيمة :attribute :size.',
        'string'  => 'يجب أن يكون طول :attribute :size أحرف.',
        'array'   => 'يجب أن يحتوي :attribute على :size عناصر بالضبط.',
    ],

    'exists'    => 'القيمة المختارة في :attribute غير موجودة.',
    'distinct'  => 'يوجد تكرار في قيمة :attribute.',
    'different' => 'يجب أن يختلف :attribute عن :other.',
    'in'        => 'القيمة المختارة في :attribute غير صالحة.',
    'not_in'    => 'القيمة المختارة في :attribute غير صالحة.',
    'date'      => 'يجب أن يكون :attribute تاريخًا صالحًا.',
    'url'       => 'يجب أن يكون :attribute رابطًا صالحًا.',
    'image'     => 'يجب أن يكون :attribute صورة.',
    'file'      => 'يجب أن يكون :attribute ملفًا.',
    'regex'     => 'صيغة :attribute غير صالحة.',

    'attributes' => [
        'name'              => 'الاسم',
        'email'             => 'البريد الإلكتروني',
        'password'          => 'كلمة المرور',
        'organization_name' => 'اسم المنظمة',
        'answers'           => 'الإجابات',
        'score'             => 'الدرجة',
        'question_id'       => 'معرّف السؤال',
        'current_password'  => 'كلمة المرور الحالية',
        'new_password'      => 'كلمة المرور الجديدة',
        'token'             => 'الرمز',
    ],
];
