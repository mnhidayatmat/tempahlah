<?php

namespace App\Support\Billing;

/**
 * Read-side of the 3-tier plan model (config/homestay.php → plans).
 *
 * Everything plan-related resolves through here so the additive feature
 * inheritance (`ultra` inherits `pro` inherits `free`) and the null-means-
 * unlimited limit convention live in exactly one place.
 */
class Plans
{
    public const FREE = 'free';
    public const PRO = 'pro';
    public const ULTRA = 'ultra';

    /** Tier order for "at least Pro"-style checks. Unknown plans rank as free. */
    public const RANKS = [self::FREE => 0, self::PRO => 1, self::ULTRA => 2];

    /** @var array<string, string[]>|null */
    protected static ?array $featureCache = null;

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(config('homestay.plans', []));
    }

    public static function config(string $plan): array
    {
        return config('homestay.plans.'.$plan)
            ?? config('homestay.plans.'.self::FREE, []);
    }

    public static function rank(string $plan): int
    {
        return self::RANKS[$plan] ?? 0;
    }

    public static function name(string $plan): string
    {
        return self::config($plan)['name'] ?? ucfirst($plan);
    }

    public static function price(string $plan): float
    {
        return (float) (self::config($plan)['price_monthly'] ?? 0);
    }

    public static function trialDays(string $plan): int
    {
        return (int) (self::config($plan)['trial_days'] ?? 0);
    }

    /**
     * Every feature key the plan holds, resolving the `inherits` chain.
     *
     * @return string[]
     */
    public static function features(string $plan): array
    {
        if (isset(self::$featureCache[$plan])) {
            return self::$featureCache[$plan];
        }

        $features = [];
        $key = $plan;
        $hops = 0;

        while ($key !== null && $hops++ < 5) {
            $cfg = config('homestay.plans.'.$key);
            if (! is_array($cfg)) {
                break;
            }
            $features = array_merge($cfg['features'] ?? [], $features);
            $key = $cfg['inherits'] ?? null;
        }

        return self::$featureCache[$plan] = array_values(array_unique($features));
    }

    public static function hasFeature(string $plan, string $feature): bool
    {
        return in_array($feature, self::features($plan), true);
    }

    /**
     * The cheapest plan that holds a feature — what an upsell should name
     * ("Pro — RM49/mo" vs "Ultra — RM89/mo"). Null when no plan has it.
     */
    public static function minTierFor(string $feature): ?string
    {
        $plans = self::keys();
        usort($plans, fn ($a, $b) => self::rank($a) <=> self::rank($b));

        foreach ($plans as $plan) {
            if (self::hasFeature($plan, $feature)) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * A numeric plan cap. `null` = unlimited.
     */
    public static function limit(string $plan, string $limit): ?int
    {
        $value = self::config($plan)['limits'][$limit] ?? null;

        return $value === null ? null : (int) $value;
    }

    /** Tests / config reloads only. */
    public static function flushCache(): void
    {
        self::$featureCache = null;
    }
}
