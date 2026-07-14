<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding content update (owner request 2026-07-13): the welcome email
 * (step 1, day 0) now points new hosts at the Telegram support group.
 * Data migration because the series is DB content; runs once — later admin
 * edits in Platform Admin → Email marketing are theirs to keep.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('onboarding_emails')) {
            return;
        }

        DB::table('onboarding_emails')->where('step_no', 1)->update([
            'body_md' => self::step1Body(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Content-only change — no structural rollback needed.
    }

    private static function step1Body(): string
    {
        return <<<'MD'
Salam {name},

Terima kasih kerana mendaftarkan **{business_name}** di Tempahlah! 🙏

Untuk mula terima tempahan, cuma 3 langkah:

1. **Tambah homestay & bilik anda** — letak kadar semalam
2. **Beritahu tetamu cara nak bayar** — masukkan detail bank anda (percuma)
3. **Terbitkan & kongsi link tempahan anda** — tetamu boleh tempah terus, tiada orang tengah

Semua ini ambil masa kurang 10 minit. Senarai semak "Get set up" di papan pemuka akan pandu anda satu-satu.

[Buka papan pemuka](https://tempahlah.com/dashboard)

📱 **Tip:** Buka papan pemuka di telefon anda, kemudian pilih **"Add to Home Screen" / "Tambah ke Skrin Utama"** dari menu pelayar — Tempahlah terus jadi app di telefon anda, buka terus ke papan pemuka tanpa taip URL.

💬 **Sertai group sokongan Telegram kami** — tanya soalan, dapat bantuan pantas & berhubung dengan host lain: [t.me/tempahla](https://t.me/tempahla)

Ada soalan? Balas sahaja email ini — kami jawab sendiri.

Terima kasih,
Pasukan Tempahlah
MD;
    }
};
