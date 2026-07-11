<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide key-value settings the super-admin manages from the UI. Values
 * are encrypted at rest (app-layer, via the cast). Read through get()/put(),
 * which keep a per-request cache so a page that reads many keys (the Stripe
 * config) doesn't issue a query each time.
 *
 * NOT tenant-scoped — these are platform-level.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        // Charges money if leaked — never stored in plaintext.
        'value' => 'encrypted',
    ];

    /** Per-request decrypted cache: key => value. Null until first load. */
    protected static ?array $cache = null;

    /**
     * Decrypted value for a key, or $default when unset/blank. Loads (and
     * decrypts) every setting once per request. `all()` applies the encrypted
     * cast on retrieval; `pluck()` would return ciphertext, so don't use it.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (static::$cache === null) {
            static::$cache = static::all()->mapWithKeys(
                fn (self $s) => [$s->key => $s->value],
            )->all();
        }

        $value = static::$cache[$key] ?? null;

        return filled($value) ? $value : $default;
    }

    /**
     * Store (or clear) a setting. A blank value is stored as null so get()'s
     * `filled()` fallback lets config()/env take over again.
     */
    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => filled($value) ? $value : null],
        );

        static::$cache = null;
    }
}
