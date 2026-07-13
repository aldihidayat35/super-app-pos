<?php

return [
    'required' => ':attribute wajib diisi.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'boolean' => ':attribute harus bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak sesuai.',
    'image' => ':attribute harus berupa gambar.',
    'numeric' => ':attribute harus berupa angka.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'date' => ':attribute harus berupa tanggal yang valid.',
    'date_format' => ':attribute tidak sesuai format :format.',
    'after_or_equal' => ':attribute harus sama dengan atau setelah :date.',
    'max' => [
        'numeric' => ':attribute maksimal :max.',
        'file' => 'Ukuran :attribute maksimal :max kilobita.',
        'string' => ':attribute maksimal :max karakter.',
        'array' => ':attribute maksimal memiliki :max item.',
    ],
    'min' => [
        'numeric' => ':attribute minimal :min.',
        'file' => 'Ukuran :attribute minimal :min kilobita.',
        'string' => ':attribute minimal :min karakter.',
        'array' => ':attribute minimal memiliki :min item.',
    ],
    'attributes' => [],
];
