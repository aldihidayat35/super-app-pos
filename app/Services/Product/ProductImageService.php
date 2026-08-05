<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    public function setPrimary(Product $product, ProductImage $image, User $actor): ProductImage
    {
        return DB::transaction(function () use ($product, $image, $actor): ProductImage {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $selected = ProductImage::query()->lockForUpdate()
                ->where('product_id', $lockedProduct->id)
                ->find($image->id);

            if (! $selected instanceof ProductImage) {
                throw ValidationException::withMessages(['image' => 'Foto yang dipilih bukan milik produk ini.']);
            }

            $previous = ProductImage::query()->where('product_id', $lockedProduct->id)->where('is_primary', true)->first();
            ProductImage::query()->where('product_id', $lockedProduct->id)->update(['is_primary' => false]);
            $selected->forceFill(['is_primary' => true])->save();
            $lockedProduct->forceFill(['main_image_path' => $selected->path])->save();

            if ($previous?->id !== $selected->id) {
                activity()->causedBy($actor)->performedOn($lockedProduct)->withProperties([
                    'old' => ['primary_image' => $previous?->path],
                    'attributes' => ['primary_image' => $selected->path],
                ])->log('product.photo.primary_changed');
            }

            return $selected->fresh();
        });
    }

    public function add(Product $product, string $path, User $actor, bool $makePrimary = false): ProductImage
    {
        return DB::transaction(function () use ($product, $path, $actor, $makePrimary): ProductImage {
            $hasPrimary = $product->images()->where('is_primary', true)->exists();
            $image = $product->images()->create([
                'path' => $path,
                'alt_text' => $product->name,
                'sort_order' => ((int) $product->images()->max('sort_order')) + 1,
                'is_primary' => false,
            ]);

            activity()->causedBy($actor)->performedOn($product)->withProperties([
                'attributes' => ['product_image' => $path],
            ])->log('product.photo.added');

            if ($makePrimary || ! $hasPrimary) {
                return $this->setPrimary($product, $image, $actor);
            }

            return $image;
        });
    }

    public function remove(Product $product, ProductImage $image, User $actor): void
    {
        DB::transaction(function () use ($product, $image, $actor): void {
            $locked = ProductImage::query()->lockForUpdate()->where('product_id', $product->id)->findOrFail($image->id);
            $wasPrimary = $locked->is_primary;
            $path = $locked->path;
            $locked->delete();

            activity()->causedBy($actor)->performedOn($product)->withProperties([
                'old' => ['product_image' => $path],
                'attributes' => ['product_image' => null],
            ])->log('product.photo.deleted');

            if ($wasPrimary) {
                $replacement = $product->images()->orderBy('sort_order')->orderBy('id')->first();
                if ($replacement instanceof ProductImage) {
                    $this->setPrimary($product, $replacement, $actor);
                } else {
                    $product->forceFill(['main_image_path' => null])->save();
                }
            }
        });
    }
}
