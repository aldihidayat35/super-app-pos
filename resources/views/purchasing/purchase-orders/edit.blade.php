@extends('layouts.metronic.app')

@section('title', 'Edit Pesanan ke Supplier ' . $purchaseOrder->number . ' - ' . config('app.name'))
@section('page_title', 'Edit Pesanan ke Supplier (PO)')

@section('content')
    <div class="card card-flush shadow-sm">
        <div class="card-header border-bottom border-gray-200 py-4">
            <div class="card-title">
                <div class="d-flex align-items-center">
                    <span class="symbol symbol-40px bg-light-primary me-3 d-flex align-items-center justify-content-center">
                        <i class="ki-outline ki-notepad-edit fs-2 text-primary"></i>
                    </span>
                    <div>
                        <h2 class="fs-4 fw-bolder mb-0 text-gray-900">Edit Pesanan {{ $purchaseOrder->number }}</h2>
                        <div class="text-muted fs-8">Perbarui data rekanan supplier, jadwal pengiriman, dan rincian barang pesanan.</div>
                    </div>
                </div>
            </div>
            <div class="card-toolbar">
                <x-metronic.status-badge :status="$purchaseOrder->status" />
                <a href="{{ route('purchasing.purchase-orders.show', $purchaseOrder) }}" class="btn btn-sm btn-light ms-3">
                    <i class="ki-outline ki-arrow-left fs-5 me-1"></i> Batal / Kembali
                </a>
            </div>
        </div>

        <div class="card-body p-6">
            <form method="POST" action="{{ route('purchasing.purchase-orders.update', $purchaseOrder) }}" id="form-purchase-order-edit">
                @include('purchasing.purchase-orders._form', ['method' => 'PUT'])
            </form>
        </div>
    </div>
@endsection
