<?php

namespace App\Support\Activity;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Tự ghi nhật ký khi model được thêm, sửa, xoá. Chỉ các cột khai trong
 * activityFields() được ghi, cột kỹ thuật như sort_order thì bỏ qua.
 */
trait RecordsActivity
{
    /** @var list<string> Dòng thêm cho lần ghi kế tiếp, ví dụ đổi nhãn (bảng phụ không bắn sự kiện). */
    public array $activityExtra = [];

    public static function bootRecordsActivity(): void
    {
        foreach (['created', 'updated', 'deleted'] as $action) {
            static::$action(fn (Model $model) => $model->recordActivity($action));
        }
    }

    /** menu, reservation, takeaway hoặc settings. */
    abstract public function activityArea(): string;

    /** Tên đọc được, ví dụ "món Phở bò". */
    abstract public function activityName(): string;

    /** @return array<string, string|array{0: string, 1: class-string}> cột => kiểu hiển thị */
    abstract public function activityFields(): array;

    public function activityRestaurantId(): int
    {
        return (int) $this->restaurant_id;
    }

    /** @return list<string> */
    protected function activityActions(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    /** Câu riêng cho một số thay đổi, ví dụ "Báo hết món". Có câu riêng thì bỏ dòng chi tiết. */
    protected function activitySummary(string $action, array $changed): ?string
    {
        return null;
    }

    public function recordActivity(string $action): void
    {
        if (! in_array($action, $this->activityActions(), true)) {
            return;
        }

        $changed = [];
        $lines = [];

        if ($action === 'updated') {
            $fields = $this->activityFields();
            $changed = array_values(array_intersect(array_keys($fields), array_keys($this->getChanges())));

            foreach ($changed as $field) {
                array_push($lines, ...ActivityFormat::lines($this, $field, $fields[$field]));
            }
        }

        $special = $this->activitySummary($action, $changed);
        $lines = [...($special === null ? $lines : []), ...$this->activityExtra];
        $this->activityExtra = [];

        if ($action === 'updated' && $special === null && $lines === []) {
            return;
        }

        ActivityLog::record(
            $this->activityRestaurantId(),
            $this->activityArea(),
            $special ?? __('activity.'.$action, ['name' => $this->activityName()]),
            $lines,
            $this,
        );
    }
}
