<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Automated onboarding email series (Platform Admin → Email marketing →
 * Onboarding series): a fixed drip sent to each NEW host by signup age
 * (day 0 welcome → day 15 last Pro nudge). Steps are editable in the admin
 * UI; the daily `marketing:send-onboarding` command does the sending.
 * Content is seeded here so prod gets the series on deploy with no seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('onboarding_emails')) {
            Schema::create('onboarding_emails', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('step_no')->unique();
                // Days after tenant signup this step becomes due.
                $table->unsignedSmallInteger('day_offset');
                $table->string('subject', 200);
                $table->text('body_md');
                // Pro-pitch steps are pointless for a tenant who already
                // upgraded — mark them so the sender skips paid tenants.
                $table->boolean('skip_if_paid')->default(false);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('onboarding_email_sends')) {
            Schema::create('onboarding_email_sends', function (Blueprint $table) {
                $table->id();
                $table->foreignId('onboarding_email_id')->constrained('onboarding_emails')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('email', 190)->nullable();
                // sent | failed | skipped
                $table->string('status', 20)->default('sent');
                $table->string('error', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                // One row per tenant per step = the idempotency guard.
                $table->unique(['onboarding_email_id', 'tenant_id']);
            });
        }

        if (DB::table('onboarding_emails')->count() === 0) {
            $now = now();
            DB::table('onboarding_emails')->insert(array_map(
                fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now],
                self::defaultSeries(),
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_email_sends');
        Schema::dropIfExists('onboarding_emails');
    }

    /**
     * The launch series (BM primary — the host persona's language). Editable
     * afterwards in Platform Admin; this is only the starting content.
     */
    private static function defaultSeries(): array
    {
        return [
            [
                'step_no' => 1, 'day_offset' => 0, 'skip_if_paid' => false, 'enabled' => true,
                'subject' => 'Selamat datang ke Tempahlah, {name}! 3 langkah untuk mula 🏡',
                'body_md' => <<<'MD'
Salam {name},

Terima kasih kerana mendaftarkan **{business_name}** di Tempahlah! 🙏

Untuk mula terima tempahan, cuma 3 langkah:

1. **Tambah homestay & bilik anda** — letak kadar semalam
2. **Beritahu tetamu cara nak bayar** — masukkan detail bank anda (percuma)
3. **Terbitkan & kongsi link tempahan anda** — tetamu boleh tempah terus, tiada orang tengah

Semua ini ambil masa kurang 10 minit. Senarai semak "Get set up" di papan pemuka akan pandu anda satu-satu.

[Buka papan pemuka](https://tempahlah.com/dashboard)

Ada soalan? Balas sahaja email ini — kami jawab sendiri.

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 2, 'day_offset' => 2, 'skip_if_paid' => false, 'enabled' => true,
                'subject' => '{business_name} dah sedia terima tempahan?',
                'body_md' => <<<'MD'
Salam {name},

Tahukah anda — kebanyakan tempahan pertama datang daripada **pelanggan lama anda sendiri**?

Cuba ini hari ini:

- 📲 Letak link tempahan anda di **status WhatsApp**
- 👥 Hantar ke **group keluarga & kenalan** yang selalu tanya "bila ada kosong?"
- 📌 Letak di **bio Facebook / Instagram** homestay anda

Link tempahan anda: {booking_url}

Bila tetamu tempah melalui link, kalendar anda **terus block tarikh tu** — tiada lagi tempahan bertindih.

💡 Tip: ada tetamu WhatsApp anda terus? Guna **"Send booking form"** di menu Tempahan — sistem isi tarikh & harga untuk anda, tetamu cuma sahkan.

[Buka papan pemuka](https://tempahlah.com/dashboard)

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 3, 'day_offset' => 4, 'skip_if_paid' => false, 'enabled' => true,
                'subject' => 'Senaraikan {business_name} di tempahlah.com — percuma',
                'body_md' => <<<'MD'
Salam {name},

Homestay anda boleh disenaraikan di **marketplace tempahlah.com** — tempat pengunjung cari homestay ikut negeri & daerah. **Percuma, 0% komisen.**

Homestay yang aktif akan tersenarai secara automatik. Pastikan:

- 📸 Gambar cantik (gambar pertama = muka depan anda)
- 📍 Negeri & daerah betul (tetamu cari ikut lokasi)
- ⭐ **Testimoni tetamu** — selepas setiap check-out, sistem hantar borang penilaian kepada tetamu secara automatik. Ulasan 5 bintang naikkan kedudukan anda.

[Semak listing anda](https://tempahlah.com/dashboard)

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 4, 'day_offset' => 7, 'skip_if_paid' => true, 'enabled' => true,
                'subject' => 'Penat balas WhatsApp soalan sama? Biar AI jawab 24/7 🤖',
                'body_md' => <<<'MD'
Salam {name},

"Ada kosong tak weekend ni?" — berapa kali sehari anda taip jawapan yang sama?

Dengan **Tempahlah Pro**, AI Agent jawab WhatsApp {business_name} untuk anda — **24 jam, setiap hari**:

- ✅ Semak kekosongan tarikh secara live
- 💰 Bagi sebut harga tepat (termasuk musim & hujung minggu)
- 📸 Hantar gambar & lokasi homestay
- 🤝 Serah kepada anda bila ada soalan sensitif

Anda jaga rumah, jaga keluarga, tidur cukup — tempahan tetap tak terlepas.

**Cuba percuma 7 hari.** Batal bila-bila sebelum hari ke-7, tiada caj langsung.

[Mula percubaan percuma]({upgrade_url})

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 5, 'day_offset' => 10, 'skip_if_paid' => true, 'enabled' => true,
                'subject' => '"Dah transfer ke belum?" — tak perlu tanya lagi',
                'body_md' => <<<'MD'
Salam {name},

Kejar resit transfer, semak akaun bank, sahkan tempahan satu-satu... ada cara lebih senang.

Dengan **Pro**, tetamu bayar online (FPX / kad / e-wallet melalui SecurePay, Toyyibpay atau Billplz — akaun gateway anda sendiri):

- 💳 Tetamu bayar → tempahan **disahkan secara automatik**
- 🧾 Invois & resit PDF berjenama dihantar sendiri ke email + WhatsApp tetamu
- ⏰ Peringatan baki bayaran dihantar automatik sebelum check-in
- 🏦 Duit masuk **terus ke akaun anda** — Tempahlah ambil 0% komisen

**7 hari percuma.** Kalau tak sesuai, batal — tiada caj.

[Mula percubaan percuma]({upgrade_url})

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 6, 'day_offset' => 13, 'skip_if_paid' => true, 'enabled' => true,
                'subject' => 'Guna Airbnb / Booking.com sekali? Elak double-booking',
                'body_md' => <<<'MD'
Salam {name},

Kalau {business_name} tersenarai juga di Airbnb atau Booking.com, ini penting:

Dengan **Pro**, kalendar anda **sync 2 hala** — bila Airbnb terima tempahan, tarikh di Tempahlah terus block (dan sebaliknya). Tiada lagi dua tetamu untuk satu malam. 😅

Ini yang dikatakan oleh host sebenar kami:

> "Dulu saya bazir 2 jam sehari balas WhatsApp pelanggan tanya benda sama. Sekarang AI buat semua — saya boleh urus rumah, jaga anak, tidur cukup. Tempahan tak terlepas pun."
> — **Wafa M., Wafa Homestay Kluang**

[Cuba Pro percuma 7 hari]({upgrade_url})

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 7, 'day_offset' => 15, 'skip_if_paid' => true, 'enabled' => true,
                'subject' => '3 soalan yang selalu ditanya sebelum naik taraf',
                'body_md' => <<<'MD'
Salam {name},

Sebelum kami berhenti "promote" 😄 — tiga soalan yang paling kerap host tanya:

**"Kalau saya batal, kena caj tak?"**
Tidak. Batal bila-bila dalam 7 hari pertama — RM0. Selepas itu pun boleh berhenti bila-bila, tiada kontrak.

**"Kalau saya turun semula ke Free, data saya hilang?"**
Tidak. Semua tempahan & tetamu kekal. Ciri Pro sahaja yang tertutup.

**"Betul ke 0% komisen?"**
Betul. RM49/bulan sahaja — setiap sen tempahan masuk akaun anda, termasuk tempahan dari marketplace.

[Mula percubaan 7 hari — percuma]({upgrade_url})

Selepas ini kami hanya hantar berita penting sahaja. Terima kasih kerana bersama Tempahlah! 🙏

Pasukan Tempahlah
MD,
            ],
        ];
    }
};
