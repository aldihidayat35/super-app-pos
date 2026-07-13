<?php

namespace App\Services\Warehouse;

use App\Enums\RestockRequestStatus;
use App\Exceptions\ServiceException;
use App\Models\Branch;
use App\Models\DocumentStatusHistory;
use App\Models\Product;
use App\Models\RestockRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organization\DocumentNumberService;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class RestockRequestService
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): RestockRequest
    {
        return DB::transaction(function () use ($data, $actor): RestockRequest {
            $branch = Branch::query()->with(['workLocation', 'primaryWarehouse.workLocation'])->findOrFail($data['branch_id']);
            $sourceWarehouse = filled($data['source_warehouse_id'] ?? null)
                ? Warehouse::query()->with('workLocation')->findOrFail($data['source_warehouse_id'])
                : $branch->primaryWarehouse;

            if (! $sourceWarehouse) {
                throw ServiceException::validation('Cabang belum memiliki gudang sumber.');
            }

            $request = RestockRequest::query()->create([
                'number' => $this->numbers->next('restock_request', $branch->workLocation),
                'branch_id' => $branch->id,
                'source_warehouse_id' => $sourceWarehouse->id,
                'requested_by' => $actor->id,
                'status' => RestockRequestStatus::DRAFT,
                'priority' => $data['priority'] ?? 'normal',
                'needed_at' => $data['needed_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->replaceItems($request, $data['items'] ?? []);
            $this->history($request, null, RestockRequestStatus::DRAFT, $actor, 'Request restock dibuat.');

            if (($data['action'] ?? null) === 'submit') {
                return $this->submit($request, $actor);
            }

            return $request->fresh(['items.product', 'branch', 'sourceWarehouse']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(RestockRequest $request, array $data, User $actor): RestockRequest
    {
        return DB::transaction(function () use ($request, $data, $actor): RestockRequest {
            $request = RestockRequest::query()->with('items')->lockForUpdate()->findOrFail($request->id);

            if (! $request->status->isEditable()) {
                throw ServiceException::validation('Request restock yang sudah diajukan tidak boleh diedit.');
            }

            $request->fill([
                'source_warehouse_id' => $data['source_warehouse_id'] ?? $request->source_warehouse_id,
                'priority' => $data['priority'] ?? 'normal',
                'needed_at' => $data['needed_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->replaceItems($request, $data['items'] ?? []);
            activity()->causedBy($actor)->performedOn($request)->log('restock_request.updated');

            if (($data['action'] ?? null) === 'submit') {
                return $this->submit($request, $actor);
            }

            return $request->fresh(['items.product', 'branch', 'sourceWarehouse']);
        });
    }

    public function submit(RestockRequest $request, User $actor): RestockRequest
    {
        return DB::transaction(function () use ($request, $actor): RestockRequest {
            $request = RestockRequest::query()->with('items')->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== RestockRequestStatus::DRAFT) {
                throw ServiceException::validation('Hanya draft yang dapat diajukan.');
            }

            $request->forceFill(['status' => RestockRequestStatus::PENDING_APPROVAL, 'submitted_at' => now()])->save();
            $this->history($request, RestockRequestStatus::DRAFT, RestockRequestStatus::PENDING_APPROVAL, $actor, 'Request restock diajukan.');

            return $request->fresh(['items.product', 'branch', 'sourceWarehouse']);
        });
    }

    /** @param array<int, string|int|float> $approvedQuantities */
    public function approve(RestockRequest $request, User $actor, array $approvedQuantities = []): RestockRequest
    {
        return DB::transaction(function () use ($request, $actor, $approvedQuantities): RestockRequest {
            $request = RestockRequest::query()->with('items')->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== RestockRequestStatus::PENDING_APPROVAL) {
                throw ServiceException::validation('Request restock belum menunggu approval.');
            }

            foreach ($request->items as $item) {
                $approved = Decimal::normalize($approvedQuantities[$item->id] ?? $item->quantity_requested);
                if (Decimal::compare($approved, (string) $item->quantity_requested) > 0) {
                    throw ServiceException::validation('Qty approved tidak boleh melebihi qty request.');
                }

                $item->forceFill(['quantity_approved' => $approved])->save();
            }

            $request->forceFill(['status' => RestockRequestStatus::APPROVED, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->history($request, RestockRequestStatus::PENDING_APPROVAL, RestockRequestStatus::APPROVED, $actor, 'Request restock disetujui.');

            return $request->fresh(['items.product', 'branch.workLocation', 'sourceWarehouse.workLocation']);
        });
    }

    public function reject(RestockRequest $request, User $actor, string $reason): RestockRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason): RestockRequest {
            $request = RestockRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== RestockRequestStatus::PENDING_APPROVAL) {
                throw ServiceException::validation('Hanya request pending yang dapat ditolak.');
            }

            $request->forceFill(['status' => RestockRequestStatus::REJECTED, 'approved_by' => $actor->id, 'rejected_at' => now(), 'reject_reason' => $reason])->save();
            $this->history($request, RestockRequestStatus::PENDING_APPROVAL, RestockRequestStatus::REJECTED, $actor, $reason);

            return $request->fresh(['items.product', 'branch', 'sourceWarehouse']);
        });
    }

    /** @param list<array<string, mixed>> $items */
    private function replaceItems(RestockRequest $request, array $items): void
    {
        if ($items === []) {
            throw ServiceException::validation('Minimal satu item request restock wajib diisi.');
        }

        $request->items()->delete();

        foreach ($items as $itemData) {
            $product = Product::query()->findOrFail($itemData['product_id']);
            $quantity = Decimal::normalize($itemData['quantity_requested']);

            if (Decimal::compare($quantity, '0') <= 0) {
                throw ServiceException::validation("Qty produk {$product->sku} harus lebih dari nol.");
            }

            $request->items()->create([
                'product_id' => $product->id,
                'quantity_requested' => $quantity,
                'quantity_approved' => '0.0000',
                'priority' => $itemData['priority'] ?? $request->priority,
                'notes' => $itemData['notes'] ?? null,
            ]);
        }
    }

    private function history(RestockRequest $request, ?RestockRequestStatus $from, RestockRequestStatus $to, User $actor, ?string $notes = null): void
    {
        DocumentStatusHistory::query()->create([
            'document_type' => 'restock_request',
            'document_id' => $request->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor->id,
            'notes' => $notes,
        ]);
    }
}
