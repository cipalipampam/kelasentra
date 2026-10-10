<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Presensi Kehadiran — Kelasentra</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 24px;
            background: #ffffff;
            font-size: 11pt;
        }
        .header-kop {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-kop h2 {
            margin: 0 0 4px 0;
            font-size: 16pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #0f172a;
        }
        .header-kop h4 {
            margin: 0 0 4px 0;
            font-size: 12pt;
            font-weight: normal;
            color: #475569;
        }
        .header-kop p {
            margin: 0;
            font-size: 9pt;
            color: #64748b;
        }
        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
            font-size: 10pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
            font-size: 9.5pt;
        }
        th {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 8.5pt;
            letter-spacing: 0.5px;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-present { background-color: #ecfdf5; color: #065f46; }
        .badge-late { background-color: #fffbeb; color: #92400e; }
        .badge-permission { background-color: #eef2ff; color: #3730a3; }
        .badge-sick { background-color: #f0f9ff; color: #075985; }
        .badge-absent { background-color: #fff1f2; color: #9f1239; }
        
        .footer-ttd {
            margin-top: 40px;
            display: flex;
            justify-content: flex-end;
        }
        .ttd-box {
            text-align: center;
            width: 220px;
        }
        .ttd-space {
            height: 70px;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
            Cetak Dokumen
        </button>
        <button onclick="window.close()" style="padding: 8px 16px; background: #64748b; color: white; border: none; border-radius: 6px; cursor: pointer;">
            Tutup
        </button>
    </div>

    <div class="header-kop">
        <h2>KELASENTRA — SISTEM OPERASI AKADEMIK SEKOLAH</h2>
        <h4>LAPORAN REKAPITULASI KEHADIRAN</h4>
        <p>Dicetak pada: {{ now()->translatedFormat('l, d F Y - H:i') }} WIB oleh {{ Auth::user()->name ?? 'Administrator' }}</p>
    </div>

    <div class="meta-info">
        <div>
            @if(isset($date) && $date)
                <strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($date)->translatedFormat('d F Y') }}<br>
            @elseif(isset($month) && $month)
                <strong>Bulan:</strong> {{ \Carbon\Carbon::create(null, $month, 1)->translatedFormat('F') }} {{ $year ?? date('Y') }}<br>
            @endif
            @if(isset($role) && $role)
                <strong>Peran:</strong> {{ ucfirst($role) }}<br>
            @endif
            @if(isset($classroom) && $classroom)
                <strong>Rombel:</strong> {{ $classroom->name }} (TA {{ $classroom->academicYear?->name }})<br>
            @endif
        </div>
        <div class="text-end">
            <strong>Total Rekaman:</strong> {{ count($attendances) }} Catatan
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">No</th>
                <th>Nama Anggota</th>
                <th>Peran / Info</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendances as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $item->user->name ?? '-' }}</strong></td>
                    <td>
                        @if($item->user->student)
                            Siswa ({{ $item->user->student->class_name }})
                        @elseif($item->user->employee)
                            Pegawai ({{ $item->user->employee->position ?? 'Staff' }})
                        @else
                            Administrator
                        @endif
                    </td>
                    <td>{{ \Carbon\Carbon::parse($item->recorded_at)->format('d/m/Y') }}</td>
                    <td>
                        @if($item->status == 'present')
                            <span class="badge {{ $item->is_late ? 'badge-late' : 'badge-present' }}">
                                {{ $item->is_late ? 'Terlambat' : 'Hadir' }}
                            </span>
                        @elseif($item->status == 'permission')
                            <span class="badge badge-permission">Izin</span>
                        @elseif($item->status == 'sick')
                            <span class="badge badge-sick">Sakit</span>
                        @else
                            <span class="badge badge-absent">Alfa</span>
                        @endif
                    </td>
                    <td>{{ \Carbon\Carbon::parse($item->recorded_at)->format('H:i') }} WIB</td>
                    <td>{{ $item->check_out_time ? \Carbon\Carbon::parse($item->check_out_time)->format('H:i') . ' WIB' : '-' }}</td>
                    <td>{{ $item->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 24px;">Tidak ada data presensi yang sesuai dengan kriteria filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-ttd">
        <div class="ttd-box">
            <p style="margin: 0 0 6px 0;">Mengetahui,</p>
            <p style="margin: 0; font-weight: bold;">Kepala / Administrator</p>
            <div class="ttd-space"></div>
            <p style="margin: 0; font-weight: bold; text-decoration: underline;">( {{ Auth::user()->name ?? 'Administrator' }} )</p>
            <p style="margin: 2px 0 0 0; font-size: 8.5pt; color: #64748b;">NIP. .....................................</p>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            // Auto open print dialog if not cancelled
            // window.print();
        });
    </script>
</body>
</html>
