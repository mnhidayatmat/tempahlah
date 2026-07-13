<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding series part 2 — feature-education emails (steps 8–12, days
 * 18–30, one every 3 days): the free "hidden gems", three Pro deep-dives
 * (AI training, branded documents, operations automation), and an Ultra
 * finale. Idempotent: inserts only step numbers that don't exist, so an
 * admin-edited series is never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('onboarding_emails')) {
            return;
        }

        $now = now();
        foreach (self::steps() as $step) {
            if (DB::table('onboarding_emails')->where('step_no', $step['step_no'])->exists()) {
                continue;
            }
            DB::table('onboarding_emails')->insert($step + ['created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('onboarding_emails')) {
            DB::table('onboarding_emails')->whereIn('step_no', [8, 9, 10, 11, 12])->delete();
        }
    }

    private static function steps(): array
    {
        return [
            [
                'step_no' => 8, 'day_offset' => 18, 'skip_if_paid' => false, 'enabled' => true,
                'subject' => '5 ciri percuma yang ramai host terlepas 💎',
                'body_md' => <<<'MD'
Salam {name},

Dah guna Tempahlah beberapa minggu — tapi tahukah anda 5 ciri **percuma** ini?

**1. Send booking form** 📋
Tetamu WhatsApp anda terus? Di menu Tempahan, tekan "Send booking form" — sistem sediakan link dengan tarikh & harga siap diisi. Tetamu cuma sahkan. Tiada lagi salah taip tarikh.

**2. Block tarikh di kalendar** 🗓️
Rumah nak dipakai sendiri, atau ada tempahan luar? Block tarikh tu di Kalendar supaya tiada tetamu boleh tempah.

**3. Direktori crew** 🧹
Simpan nombor cleaner, dobi & tukang baiki di menu Direktori — kemudian satu tekan "Copy text" untuk hantar jadual kerja ke WhatsApp mereka.

**4. Rekod perbelanjaan** 💸
Menu Expenses: rekod kos cuci, dobi, baiki & belian rumah — nampak untung bersih {business_name} setiap bulan.

**5. Testimoni automatik** ⭐
Selepas setiap check-out, sistem hantar borang penilaian kepada tetamu. Ulasan terus terpapar di halaman tempahan anda: {booking_url}

[Buka papan pemuka](https://tempahlah.com/dashboard)

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 9, 'day_offset' => 21, 'skip_if_paid' => true, 'enabled' => true,
                'subject' => 'AI Agent boleh dilatih ikut cara anda jawab 🎓',
                'body_md' => <<<'MD'
Salam {name},

Ramai host risau: *"AI ni jawab betul ke? Nanti salah bagi info macam mana?"*

Sebab itu AI Agent Tempahlah **boleh dilatih**:

- 📚 **Soalan-jawapan sedia terisi dari data anda** — kadar, waktu check-in/out, deposit, cara bayar, lokasi. Anda semak & ubah ikut suka
- ✍️ **Tambah jawapan anda sendiri** — "boleh bawa kucing tak?", "ada pelamin tak?" — AI jawab ikut ayat anda
- 🚫 **Kenal crew anda** — nombor cleaner & dobi dalam Direktori tidak akan dilayan macam tetamu
- 🤝 **Serah kepada anda** bila tetamu minta manusia atau ada isu sensitif
- 🧪 **Playground** — uji jawapan AI dulu sebelum aktifkan

AI jawab dalam BM atau English, ikut bahasa tetamu. 24 jam. Setiap hari.

**Cuba percuma 7 hari** — tetapan AI siap dalam 10 minit.

[Mula percubaan percuma]({upgrade_url})

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 10, 'day_offset' => 24, 'skip_if_paid' => true, 'enabled' => true,
                'subject' => 'Invois berjenama = homestay yang nampak profesional 🧾',
                'body_md' => <<<'MD'
Salam {name},

Tetamu korporat tanya invois rasmi? Tetamu nak resit untuk claim? Dengan **Pro**, {business_name} hantar dokumen yang nampak profesional — secara automatik:

- 🧾 **Invois & resit PDF berjenama** — logo anda, QR bayaran, terma anda — dihantar sendiri ke email + WhatsApp tetamu pada setiap tempahan & bayaran
- 🎨 **Warna jenama anda** di halaman tempahan — bukan warna standard platform
- 📊 **Laporan & analitik** — pendapatan, okupansi, ADR bulan demi bulan + eksport PDF untuk rekod cukai
- 🌐 **Subdomain sendiri** — nama-anda.tempahlah.com, senang disebut & diingat

Kesan pertama tetamu bermula dari link & dokumen anda.

[Cuba Pro percuma 7 hari]({upgrade_url})

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 11, 'day_offset' => 27, 'skip_if_paid' => true, 'enabled' => true,
                'subject' => 'Harga hujung minggu naik sendiri, kerja cuci terjadual sendiri ⚙️',
                'body_md' => <<<'MD'
Salam {name},

Homestay yang maju bukan kerja lebih — automasinya lebih. Dengan **Pro**:

- 📈 **Harga dinamik** — set sekali: hujung minggu +RM60, cuti sekolah +RM130, musim perayaan ikut suka. Sistem kira sendiri pada setiap sebut harga & tempahan
- 🧹 **Jadual cuci & dobi automatik** — setiap tempahan yang disahkan terus jana kerja post-checkout untuk crew anda (rush 2 orang bila back-to-back, santai 1 orang bila ada masa)
- ⏰ **Peringatan automatik** — baki bayaran dikejar sendiri sebelum check-in, panduan check-out dihantar sebelum tetamu pulang
- 🥇 **Keutamaan di marketplace** — listing Pro naik atas listing biasa dalam carian tempahlah.com

Kurang kerja manual, kurang terlepas, lebih tempahan.

[Cuba Pro percuma 7 hari]({upgrade_url})

Terima kasih,
Pasukan Tempahlah
MD,
            ],
            [
                'step_no' => 12, 'day_offset' => 30, 'skip_if_paid' => false, 'enabled' => true,
                'subject' => 'Sebulan bersama Tempahlah — apa seterusnya untuk {business_name}? 🚀',
                'body_md' => <<<'MD'
Salam {name},

Genap sebulan {business_name} di Tempahlah — terima kasih! 🙏

Ringkasan pilihan anda ke hadapan:

**Pro — RM49/bulan** (7 hari percuma)
AI Agent WhatsApp 24/7 · gateway bayaran sendiri · invois berjenama · sync Airbnb & Booking.com · harga dinamik · sehingga 3 homestay.

**Ultra — RM89/bulan** (7 hari percuma) — untuk jenama yang lebih besar:
- 🏘️ **Homestay & akaun pekerja tanpa had**
- 🏷️ **White-label** — tiada "Powered by Tempahlah" di halaman & invois anda
- 🥇 **Tempat teratas (featured)** di marketplace
- 📊 Laporan lanjutan berbilang homestay + sokongan khas

Kedua-duanya: **0% komisen, tiada kontrak, batal bila-bila.**

[Lihat pelan & mula percubaan]({upgrade_url})

Selepas ini kami hanya hantar berita penting. Semoga {business_name} terus maju! 💪

Pasukan Tempahlah
MD,
            ],
        ];
    }
};
