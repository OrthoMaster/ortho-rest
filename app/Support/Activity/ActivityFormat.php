<?php

namespace App\Support\Activity;

use App\Enums\UserRole;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Đổi giá trị cũ, mới của một cột thành câu đọc được. */
class ActivityFormat
{
    /** @return list<string> */
    public static function lines(Model $model, string $field, string|array $kind): array
    {
        $label = self::label($field);
        $old = $model->getOriginal($field);
        $new = $model->getAttribute($field);

        if (is_array($kind)) {
            [, $class] = $kind;
            $name = fn ($id) => $id ? ($class::query()->whereKey($id)->value('name') ?? '#'.$id) : null;

            return [self::change($label, self::text($name($old)), self::text($name($new)))];
        }

        return match ($kind) {
            'long' => [__('activity.changed', ['field' => $label])],
            'image' => [__($old ? ($new ? 'activity.image_changed' : 'activity.image_removed') : 'activity.image_added')],
            'theme' => [__('activity.theme_changed')],
            'settings' => self::settings((array) $old, (array) $new),
            default => [self::change($label, self::value($old, $kind), self::value($new, $kind))],
        };
    }

    public static function label(string $field): string
    {
        $key = 'activity.fields.'.$field;

        return __($key) === $key ? $field : __($key);
    }

    public static function value(mixed $value, string $kind = 'text'): string
    {
        return match (true) {
            $value === null || $value === '' => __('activity.empty'),
            $kind === 'money' => vnd((int) $value),
            $kind === 'bool', is_bool($value) => __($value ? 'activity.yes' : 'activity.no'),
            $value instanceof BackedEnum => self::enum($value),
            $value instanceof CarbonInterface => $value->format('H:i d/m/Y'),
            is_array($value) => $value === [] ? __('activity.empty') : implode(', ', array_map(
                fn ($item) => is_string($item) ? (UserRole::tryFrom($item)?->label() ?? $item) : self::value($item),
                $value,
            )),
            $kind === 'text' => '“'.Str::limit((string) $value, 60).'”',
            default => (string) $value,
        };
    }

    private static function enum(BackedEnum $value): string
    {
        if (method_exists($value, 'label') && (new \ReflectionMethod($value, 'label'))->getNumberOfRequiredParameters() === 0) {
            return $value->label();
        }

        $key = 'activity.enums.'.$value->value;

        return __($key) === $key ? (string) $value->value : __($key);
    }

    private static function text(?string $value): string
    {
        return self::value($value);
    }

    private static function change(string $label, string $from, string $to): string
    {
        return __('activity.change', ['field' => $label, 'from' => $from, 'to' => $to]);
    }

    /** @return list<string> */
    private static function settings(array $old, array $new): array
    {
        $lines = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            if (($old[$key] ?? null) != ($new[$key] ?? null)) {
                $lines[] = self::change(self::label($key), self::value($old[$key] ?? null, 'plain'), self::value($new[$key] ?? null, 'plain'));
            }
        }

        return $lines;
    }
}
