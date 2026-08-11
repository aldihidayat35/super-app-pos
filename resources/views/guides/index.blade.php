@extends('layouts.metronic.app')

@section('title', 'Dokumentasi Panduan')
@section('page_title', 'Dokumentasi Panduan')

@section('page_guide')
    <x-metronic.page-guide id="role-guide-index" title="Cara Menggunakan Dokumentasi">
        <x-slot:function><p>Menampilkan panduan yang sesuai dengan role akun yang sedang login.</p></x-slot:function>
        <x-slot:workflow><ol><li>Periksa role aktif.</li><li>Pilih panduan.</li><li>Ikuti alur secara berurutan.</li><li>Gunakan daftar isi pada halaman detail.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Panduan Umum:</strong> dasar penggunaan untuk semua akun.</li><li><strong>Panduan Role:</strong> prosedur operasional sesuai tugas.</li><li><strong>Akses:</strong> panduan lain disembunyikan bila tidak relevan.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Dokumentasi membantu user menjalankan transaksi dalam urutan yang benar tanpa melewati validasi, approval, atau rekonsiliasi.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Buka kartu panduan.</li><li>Cari bagian tugas.</li><li>Jalankan langkah satu per satu.</li><li>Periksa hasil akhir dan status dokumen.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0">Panduan tidak menambah permission. Jika menu operasional tidak terlihat, hubungi administrator untuk memeriksa role dan lokasi kerja.</div></x-slot:warnings>
        <x-slot:example><p>Kasir akan melihat Panduan Umum dan Toko Internal, sedangkan user Purchasing akan melihat Panduan Umum dan Purchasing & Supplier.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Dokumentasi Panduan" description="Panduan operasional yang disaring otomatis berdasarkan role akun Anda." />

    <x-metronic.card title="Role dan Cakupan Akses Anda" class="mb-6">
        <div class="row g-4">
            @forelse ($userRoles as $role)
                <div class="col-md-6 col-xl-4">
                    <div class="border border-dashed rounded p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <span class="symbol symbol-35px"><span class="symbol-label bg-light-primary text-primary"><i class="ki-outline ki-shield-tick fs-3"></i></span></span>
                            <div>
                                <div class="fw-bold text-gray-900">{{ $role['label'] }}</div>
                                <code class="fs-8">{{ $role['name'] }}</code>
                            </div>
                        </div>
                        <div class="text-muted fs-7">{{ $role['description'] }}</div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-warning mb-0">Akun belum mempunyai role. Hanya Panduan Umum yang tersedia.</div></div>
            @endforelse
        </div>
    </x-metronic.card>

    <div class="row g-5">
        @foreach ($guides as $guide)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('guides.show', $guide['slug']) }}" class="card card-flush border border-hover-{{ $guide['color'] }} h-100 text-decoration-none">
                    <div class="card-body d-flex flex-column p-7">
                        <div class="symbol symbol-55px mb-5">
                            <span class="symbol-label bg-light-{{ $guide['color'] }} text-{{ $guide['color'] }}">
                                <i class="{{ $guide['icon'] }} fs-1 text-{{ $guide['color'] }}"></i>
                            </span>
                        </div>
                        <h2 class="fs-4 text-gray-900 mb-3">{{ $guide['title'] }}</h2>
                        <p class="text-muted fs-7 flex-grow-1 mb-5">{{ $guide['description'] }}</p>
                        @if (($guide['matching_roles'] ?? []) !== [])
                            <div class="mb-4">
                                @foreach ($guide['matching_roles'] as $role)
                                    <span class="badge badge-light-{{ $guide['color'] }} me-1 mb-1">{{ config("rbac.roles.{$role}.label", str($role)->headline()) }}</span>
                                @endforeach
                            </div>
                        @endif
                        <span class="fw-semibold text-{{ $guide['color'] }}">Buka panduan <i class="ki-outline ki-arrow-right fs-5"></i></span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
