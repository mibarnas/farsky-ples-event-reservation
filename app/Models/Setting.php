<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple application-wide key/value settings managed by the administrator.
 */
class Setting extends Model
{
    public const ALIAS_EVENT_ID = 'alias_event_id';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * The event that visitors of the alias domain(s) are redirected to.
     */
    public static function aliasEvent(): ?Event
    {
        $id = static::getValue(self::ALIAS_EVENT_ID);

        return $id ? Event::find($id) : null;
    }
}
