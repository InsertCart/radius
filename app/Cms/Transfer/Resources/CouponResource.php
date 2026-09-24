<?php

namespace App\Cms\Transfer\Resources;

use App\Cms\Transfer\ExportContext;
use App\Cms\Transfer\ImportContext;
use App\Cms\Transfer\ImportReport;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Model;

/**
 * Discount codes.
 *
 * The usage counter is not exported. It belongs to the orders on the site that
 * took them, and carrying it across would either lock a fresh code that nobody
 * has used or quietly unlock one that is meant to be spent.
 */
class CouponResource extends TransferResource
{
    public function key(): string
    {
        return 'coupons';
    }

    public function label(): string
    {
        return 'Coupons';
    }

    public function modelClass(): string
    {
        return Coupon::class;
    }

    public function module(): ?string
    {
        return 'shop';
    }

    public function hint(): string
    {
        return 'Discount codes and their limits. Usage counts start again at zero.';
    }

    public function record(Model $model, ExportContext $context): array
    {
        /** @var Coupon $model */
        return array_filter([
            'code' => $model->code,
            'description' => $model->description,
            'type' => $model->type,
            'value' => (int) $model->value,
            'min_order_total' => (int) $model->min_order_total,
            'max_discount' => $model->max_discount !== null ? (int) $model->max_discount : null,
            'usage_limit' => $model->usage_limit,
            'usage_limit_per_user' => $model->usage_limit_per_user,
            'starts_at' => $model->starts_at?->toIso8601String(),
            'expires_at' => $model->expires_at?->toIso8601String(),
            'is_active' => (bool) $model->is_active,
        ] + $this->timestamps($model), fn ($value) => $value !== null);
    }

    public function import(array $record, ImportContext $context): string
    {
        $code = strtoupper(trim((string) ($record['code'] ?? '')));

        if ($code === '') {
            return ImportReport::SKIPPED;
        }

        $existing = Coupon::where('code', $code)->first();

        if ($existing && ! $context->options->updatesExisting()) {
            return ImportReport::SKIPPED;
        }

        $coupon = $existing ?: new Coupon(['code' => $code]);

        $coupon->fill([
            'code' => $code,
            'description' => $record['description'] ?? null,
            'type' => in_array($record['type'] ?? null, ['percent', 'fixed', 'free_shipping'], true) ? $record['type'] : 'percent',
            'value' => max(0, (int) ($record['value'] ?? 0)),
            'min_order_total' => max(0, (int) ($record['min_order_total'] ?? 0)),
            'max_discount' => isset($record['max_discount']) ? max(0, (int) $record['max_discount']) : null,
            'usage_limit' => isset($record['usage_limit']) ? (int) $record['usage_limit'] : null,
            'usage_limit_per_user' => isset($record['usage_limit_per_user']) ? (int) $record['usage_limit_per_user'] : null,
            'starts_at' => $this->date($record['starts_at'] ?? null),
            'expires_at' => $this->date($record['expires_at'] ?? null),
            'is_active' => (bool) ($record['is_active'] ?? true),
        ]);

        $this->applyTimestamps($coupon, $record);
        $coupon->save();

        return $this->outcome(! $existing);
    }

    public function csvColumns(): array
    {
        return [
            'code' => 'Code',
            'type' => 'Type',
            'value' => 'Value',
            'min_order_total' => 'Minimum order',
            'usage_limit' => 'Usage limit',
            'starts_at' => 'Starts',
            'expires_at' => 'Expires',
            'is_active' => 'Active',
        ];
    }
}
