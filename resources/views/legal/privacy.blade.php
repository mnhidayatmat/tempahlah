@extends('legal.layout')
@php $isBM = app()->getLocale() === 'ms'; $updated = 'Jul 11, 2026'; $updatedBM = '11 Julai 2026'; @endphp

@section('title', $isBM ? 'Polisi PDPA' : 'PDPA Privacy Policy')

@section('content')
@if ($isBM)
    <p class="lg-eyebrow">Notis Privasi</p>
    <h1>Polisi Privasi PDPA</h1>
    <p class="lg-meta">Berkuat kuasa {{ $updatedBM }}</p>
    <p class="lg-intro">Notis ini menerangkan cara Tempahlah mengumpul, menggunakan dan melindungi data peribadi anda selaras dengan Akta Perlindungan Data Peribadi 2010 (PDPA) Malaysia.</p>

    <h2><span class="lg-num">1.</span>Siapa kami</h2>
    <p>Tempahlah ("kami") mengendalikan platform di <a href="{{ url('/') }}">tempahlah.com</a> dan bertindak sebagai pengguna data bagi data peribadi yang dikumpul melalui perkhidmatan kami. Bagi data tetamu yang dikumpul untuk sesuatu tempahan, tuan rumah homestay yang berkenaan juga merupakan pengguna data.</p>

    <h2><span class="lg-num">2.</span>Data yang kami kumpul</h2>
    <ul>
        <li><strong>Akaun tuan rumah:</strong> nama, emel, nombor telefon, nama perniagaan, nombor pendaftaran SSM, lesen MOTAC, dan butiran bank untuk pembayaran.</li>
        <li><strong>Data tempahan tetamu:</strong> nama, emel, nombor telefon, warganegara, tarikh menginap dan permintaan khas.</li>
        <li><strong>Data pembayaran:</strong> jumlah, status dan rujukan urus niaga. Butiran kad penuh dikendalikan oleh gerbang pembayaran, bukan kami.</li>
        <li><strong>Data penggunaan:</strong> log, jenis peranti dan kuki bagi menjadikan platform berfungsi dan selamat.</li>
    </ul>

    <h2><span class="lg-num">3.</span>Bagaimana kami menggunakan data</h2>
    <ul>
        <li>Menyediakan dan mengendalikan perkhidmatan — mencipta tempahan, invois, resit dan penyegerakan kalendar.</li>
        <li>Memproses pembayaran dan komisen pasaran.</li>
        <li>Menghantar komunikasi berkaitan tempahan melalui emel dan WhatsApp (pengesahan, peringatan, resit).</li>
        <li>Menyokong, mengekalkan keselamatan, mencegah penipuan dan mematuhi kewajipan undang-undang.</li>
    </ul>

    <h2><span class="lg-num">4.</span>Persetujuan</h2>
    <p>Dengan memberikan data peribadi anda dan menggunakan perkhidmatan, anda memberi persetujuan untuk kami memproses data seperti yang diterangkan. Jika anda memberikan data orang lain (contohnya butiran tetamu), anda mengesahkan bahawa anda mempunyai kebenaran untuk berbuat demikian.</p>

    <h2><span class="lg-num">5.</span>Pendedahan kepada pihak ketiga</h2>
    <p>Kami mendedahkan data hanya seperti yang perlu:</p>
    <ul>
        <li><strong>Antara tuan rumah dan tetamu</strong> — untuk memudahkan tempahan.</li>
        <li><strong>Gerbang pembayaran</strong> (Toyyibpay, Billplz, SecurePay) untuk memproses bayaran.</li>
        <li><strong>Penyedia perkhidmatan</strong> seperti hosting awan (AWS), emel dan penghantar WhatsApp, yang terikat menjaga kerahsiaan data.</li>
        <li><strong>Pihak berkuasa</strong> apabila dikehendaki oleh undang-undang.</li>
    </ul>
    <p>Kami <strong>tidak menjual</strong> data peribadi anda.</p>

    <h2><span class="lg-num">6.</span>Pemindahan merentas sempadan</h2>
    <p>Sesetengah penyedia perkhidmatan kami mungkin memproses data di luar Malaysia. Apabila ini berlaku, kami mengambil langkah munasabah untuk memastikan data anda dilindungi pada tahap yang setara dengan PDPA.</p>

    <h2><span class="lg-num">7.</span>Keselamatan data</h2>
    <p>Kami mengambil langkah keselamatan yang munasabah. Data sensitif seperti butiran bank dan dokumen KYC disulitkan pada lapisan aplikasi, dan dokumen sulit dihidangkan melalui pautan bertandatangan yang tamat tempoh. Tiada sistem yang benar-benar selamat 100%, tetapi kami berusaha melindungi data anda.</p>

    <h2><span class="lg-num">8.</span>Pengekalan data</h2>
    <p>Kami menyimpan data peribadi selagi akaun anda aktif atau seperti yang diperlukan untuk menyediakan perkhidmatan, mematuhi kewajipan undang-undang (seperti rekod cukai) dan menyelesaikan pertikaian. Selepas itu, data akan dipadam atau dinyahkenal pasti.</p>

    <h2><span class="lg-num">9.</span>Hak anda di bawah PDPA</h2>
    <ul>
        <li><strong>Akses</strong> — meminta salinan data peribadi yang kami pegang tentang anda.</li>
        <li><strong>Pembetulan</strong> — meminta pembetulan data yang tidak tepat.</li>
        <li><strong>Menarik balik persetujuan</strong> — menarik balik persetujuan pemprosesan (yang mungkin menjejaskan penggunaan perkhidmatan).</li>
        <li><strong>Menghadkan pemprosesan</strong> untuk tujuan pemasaran.</li>
    </ul>
    <p>Untuk melaksanakan hak ini, emel <a href="mailto:drhidayatmat@gmail.com">drhidayatmat@gmail.com</a>. Tuan rumah juga boleh mengeksport data mereka dari papan pemuka.</p>

    <h2><span class="lg-num">10.</span>Kuki</h2>
    <p>Kami menggunakan kuki yang perlu untuk log masuk, keselamatan sesi dan pilihan bahasa. Ia penting untuk platform berfungsi dan bukan untuk pengiklanan pihak ketiga.</p>

    <h2><span class="lg-num">11.</span>Kanak-kanak</h2>
    <p>Perkhidmatan kami ditujukan kepada perniagaan dewasa dan bukan untuk kanak-kanak di bawah 18 tahun. Kami tidak mengumpul data kanak-kanak secara sedar.</p>

    <h2><span class="lg-num">12.</span>Perubahan notis ini</h2>
    <p>Kami boleh mengemas kini notis ini dari semasa ke semasa. Tarikh "berkuat kuasa" di atas menunjukkan versi terkini.</p>

    <h2><span class="lg-num">13.</span>Hubungi kami</h2>
    <p>Untuk sebarang soalan privasi atau permintaan data, emel <a href="mailto:drhidayatmat@gmail.com">drhidayatmat@gmail.com</a>.</p>
@else
    <p class="lg-eyebrow">Privacy Notice</p>
    <h1>PDPA Privacy Policy</h1>
    <p class="lg-meta">Effective {{ $updated }}</p>
    <p class="lg-intro">This notice explains how Tempahlah collects, uses and protects your personal data, in line with Malaysia's Personal Data Protection Act 2010 (PDPA).</p>

    <h2><span class="lg-num">1.</span>Who we are</h2>
    <p>Tempahlah ("we", "us") operates the platform at <a href="{{ url('/') }}">tempahlah.com</a> and acts as a data user for personal data collected through our service. For guest data collected for a booking, the relevant homestay host is also a data user.</p>

    <h2><span class="lg-num">2.</span>Data we collect</h2>
    <ul>
        <li><strong>Host account:</strong> name, email, phone number, business name, SSM registration number, MOTAC licence, and bank details for payouts.</li>
        <li><strong>Guest booking data:</strong> name, email, phone number, nationality, stay dates and special requests.</li>
        <li><strong>Payment data:</strong> amounts, status and transaction references. Full card details are handled by the payment gateway, not by us.</li>
        <li><strong>Usage data:</strong> logs, device type and cookies to keep the platform working and secure.</li>
    </ul>

    <h2><span class="lg-num">3.</span>How we use data</h2>
    <ul>
        <li>Provide and operate the service — creating bookings, invoices, receipts and calendar sync.</li>
        <li>Process payments and marketplace commission.</li>
        <li>Send booking-related communications by email and WhatsApp (confirmations, reminders, receipts).</li>
        <li>Support, security, fraud prevention and legal compliance.</li>
    </ul>

    <h2><span class="lg-num">4.</span>Consent</h2>
    <p>By providing your personal data and using the service, you consent to us processing it as described. If you provide someone else's data (for example a guest's details), you confirm you have their permission to do so.</p>

    <h2><span class="lg-num">5.</span>Disclosure to third parties</h2>
    <p>We disclose data only as needed:</p>
    <ul>
        <li><strong>Between host and guest</strong> — to facilitate a booking.</li>
        <li><strong>Payment gateways</strong> (Toyyibpay, Billplz, SecurePay) to process payments.</li>
        <li><strong>Service providers</strong> such as cloud hosting (AWS), email and WhatsApp delivery, bound to keep data confidential.</li>
        <li><strong>Authorities</strong> where required by law.</li>
    </ul>
    <p>We <strong>do not sell</strong> your personal data.</p>

    <h2><span class="lg-num">6.</span>Cross-border transfer</h2>
    <p>Some of our service providers may process data outside Malaysia. Where this happens, we take reasonable steps to ensure your data is protected to a standard comparable with the PDPA.</p>

    <h2><span class="lg-num">7.</span>Data security</h2>
    <p>We take reasonable security measures. Sensitive data such as bank details and KYC documents is encrypted at the application layer, and confidential documents are served through signed links that expire. No system is ever 100% secure, but we work to protect your data.</p>

    <h2><span class="lg-num">8.</span>Data retention</h2>
    <p>We keep personal data for as long as your account is active or as needed to provide the service, meet legal obligations (such as tax records) and resolve disputes. After that, data is deleted or de-identified.</p>

    <h2><span class="lg-num">9.</span>Your rights under the PDPA</h2>
    <ul>
        <li><strong>Access</strong> — request a copy of the personal data we hold about you.</li>
        <li><strong>Correction</strong> — ask us to correct inaccurate data.</li>
        <li><strong>Withdraw consent</strong> — withdraw consent to processing (which may affect your use of the service).</li>
        <li><strong>Limit processing</strong> for marketing purposes.</li>
    </ul>
    <p>To exercise these rights, email <a href="mailto:drhidayatmat@gmail.com">drhidayatmat@gmail.com</a>. Hosts can also export their data from the dashboard.</p>

    <h2><span class="lg-num">10.</span>Cookies</h2>
    <p>We use cookies necessary for login, session security and language preference. They are essential for the platform to work and are not used for third-party advertising.</p>

    <h2><span class="lg-num">11.</span>Children</h2>
    <p>Our service is aimed at adult businesses and is not intended for anyone under 18. We do not knowingly collect children's data.</p>

    <h2><span class="lg-num">12.</span>Changes to this notice</h2>
    <p>We may update this notice from time to time. The "effective" date above shows the current version.</p>

    <h2><span class="lg-num">13.</span>Contact us</h2>
    <p>For any privacy question or data request, email <a href="mailto:drhidayatmat@gmail.com">drhidayatmat@gmail.com</a>.</p>
@endif
@endsection
