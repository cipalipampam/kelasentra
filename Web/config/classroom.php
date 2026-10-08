<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kapasitas Rombel
    |--------------------------------------------------------------------------
    |
    | Jumlah maksimal siswa aktif yang boleh menempati satu rombongan belajar.
    | Dipatuhi saat proses kenaikan kelas massal.
    |
    */
    'student_capacity' => 30,

    /*
    |--------------------------------------------------------------------------
    | Daftar Jurusan
    |--------------------------------------------------------------------------
    |
    | Harus sinkron dengan enum kolom `classrooms.major`.
    | Kelas X boleh tanpa jurusan, kelas XI dan XII wajib berjurusan.
    |
    */
    'majors' => ['MIPA', 'IPS', 'BAHASA'],
];
