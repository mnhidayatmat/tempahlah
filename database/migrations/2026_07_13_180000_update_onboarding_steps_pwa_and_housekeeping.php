<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding content update (owner request 2026-07-13):
 *  - step 1 (welcome): PS tip that Tempahlah installs to the home screen (PWA)
 *  - step 8 (hidden gems): now "6 ciri" — adds the Add-to-Home-Screen app tip
 *  - step 11 (Pro automation): spells out the auto housekeeping SOP times —
 *    clean 30 min after checkout (2 cleaners rush / 1 relaxed), laundry pickup
 *    +2h with next-day expected return, all keyed to the property's checkout
 *    time (fact-checked against GenerateOperationalTasksForBooking).
 * Data migration because the series is DB content; the seeded rows already
 * exist on prod. Runs once — later admin edits are theirs to keep.
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

        DB::table('onboarding_emails')->where('step_no', 8)->update([
            'subject' => '6 ciri percuma yang ramai host terlepas 💎',
            'body_md' => self::step8Body(),
            'updated_at' => now(),
        ]);

        DB::table('onboarding_emails')->where('step_no', 11)->update([
            'body_md' => self::step11Body(),
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

Ada soalan? Balas sahaja email ini — kami jawab sendiri.

Terima kasih,
Pasukan Tempahlah
MD;
    }

    private static function step8Body(): string
    {
        return <<<'MD'
Salam {name},

Dah guna Tempahlah beberapa minggu — tapi tahukah anda 6 ciri **percuma** ini?

**1. Jadikan Tempahlah app di telefon** 📱
Buka papan pemuka di Chrome atau Safari telefon anda → menu pelayar → **"Add to Home Screen" / "Tambah ke Skrin Utama"**. Tempahlah terbuka macam app penuh — skrin penuh, terus ke papan pemuka, tanpa taip URL. Kongsi cara ini dengan staff anda juga!

**2. Send booking form** 📋
Tetamu WhatsApp anda terus? Di menu Tempahan, tekan "Send booking form" — sistem sediakan link dengan tarikh & harga siap diisi. Tetamu cuma sahkan. Tiada lagi salah taip tarikh.

**3. Block tarikh di kalendar** 🗓️
Rumah nak dipakai sendiri, atau ada tempahan luar? Block tarikh tu di Kalendar supaya tiada tetamu boleh tempah.

**4. Direktori crew** 🧹
Simpan nombor cleaner, dobi & tukang baiki di menu Direktori — kemudian satu tekan "Copy text" untuk hantar jadual kerja ke WhatsApp mereka.

**5. Rekod perbelanjaan** 💸
Menu Expenses: rekod kos cuci, dobi, baiki & belian rumah — nampak untung bersih {business_name} setiap bulan.

**6. Testimoni automatik** ⭐
Selepas setiap check-out, sistem hantar borang penilaian kepada tetamu. Ulasan terus terpapar di halaman tempahan anda: {booking_url}

[Buka papan pemuka](https://tempahlah.com/dashboard)

Terima kasih,
Pasukan Tempahlah
MD;
    }

    private static function step11Body(): string
    {
        return <<<'MD'
Salam {name},

Homestay yang maju bukan kerja lebih — automasinya lebih. Dengan **Pro**:

**🧹 Jadual cuci & dobi automatik — ikut SOP homestay sebenar**

Setiap tempahan yang disahkan terus jana jadual kerja untuk crew anda, dikira dari waktu check-out homestay anda:

- **Cuci penuh 30 minit selepas check-out** — sistem pandai kira: tetamu seterusnya masuk dalam 1–2 hari? Jadual **2 pencuci, 2 jam** (rush). Tiada tetamu terdekat? **1 pencuci, 4 jam** (santai)
- **Dobi diambil 2 jam selepas check-out**, jangka pulang **esoknya** — siap nombor vendor dobi dari Direktori anda
- Rumah lama kosong? **Cucian habuk ringan** dijadualkan sendiri 3 jam sebelum tetamu masuk
- Setiap jadual boleh diubah bila-bila — masa, crew, kos

**⏰ Peringatan automatik**
Baki bayaran dikejar sendiri sebelum check-in; panduan check-out dihantar sebelum tetamu pulang.

**📈 Harga dinamik**
Set sekali: hujung minggu +RM60, cuti sekolah +RM130. Sistem kira sendiri pada setiap sebut harga & tempahan.

**🥇 Keutamaan di marketplace**
Listing Pro naik atas listing biasa dalam carian tempahlah.com.

Kurang kerja manual, kurang terlepas, lebih tempahan.

[Cuba Pro percuma 7 hari]({upgrade_url})

Terima kasih,
Pasukan Tempahlah
MD;
    }
};
