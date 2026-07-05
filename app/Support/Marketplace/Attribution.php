<?php

namespace App\Support\Marketplace;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Marketplace booking attribution. When a traveller clicks through from the
 * tempahlah.com marketplace to a host's subdomain, the link carries
 * ?src=marketplace — we stash that in the session (bound to the host's tenant
 * id so it can't leak across hosts) and read it back when the booking is
 * created, so the booking is flagged channel=marketplace (→ 3% commission)
 * instead of a zero-commission direct booking.
 */
class Attribution
{
    public const SESSION_KEY = 'marketplace_attribution';
    public const SOURCE = 'marketplace';

    /** Capture ?src=marketplace on a tenant-subdomain landing into the session. */
    public static function capture(Request $request, Tenant $tenant): void
    {
        if ($request->query('src') !== self::SOURCE) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, [
            'tenant_id'  => $tenant->id,
            'listing_id' => $request->query('listing_id'),
            'ref'        => substr((string) ($request->query('ref') ?: 'tempahlah_mp'), 0, 60),
        ]);
    }

    /**
     * Attribution for THIS tenant only (guards against a session set on one
     * host's subdomain being applied to another host's booking), or null.
     */
    public static function for(Tenant $tenant): ?array
    {
        $a = session(self::SESSION_KEY);

        if (! is_array($a) || (int) ($a['tenant_id'] ?? 0) !== (int) $tenant->id) {
            return null;
        }

        return $a;
    }

    /** Clear the attribution — call after a booking has consumed it. */
    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
