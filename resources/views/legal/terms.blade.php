@extends('legal.layout')
@php $isBM = app()->getLocale() === 'ms'; $updated = 'Jul 11, 2026'; $updatedBM = '11 Julai 2026'; @endphp

@section('title', $isBM ? 'Terma Perkhidmatan' : 'Terms of Service')

@section('content')
@if ($isBM)
    <p class="lg-eyebrow">Terma Perkhidmatan</p>
    <h1>Terma Perkhidmatan</h1>
    <p class="lg-meta">Berkuat kuasa {{ $updatedBM }}</p>
    <p class="lg-intro">Terma ini mengawal penggunaan anda terhadap Tempahlah — platform pengurusan homestay dan pasaran (marketplace) di <a href="{{ url('/') }}">tempahlah.com</a>. Dengan mendaftar akaun atau menggunakan perkhidmatan kami, anda bersetuju dengan terma ini.</p>

    <h2><span class="lg-num">1.</span>Tentang Tempahlah</h2>
    <p>Tempahlah menyediakan perisian (SaaS) untuk pengendali homestay di Malaysia menguruskan hartanah, tempahan, pembayaran, invois dan operasi, serta sebuah pasaran di tempahlah.com yang menghubungkan tetamu dengan homestay. Dalam terma ini, "kami" merujuk kepada Tempahlah, "tuan rumah" merujuk kepada pengendali homestay yang mendaftar akaun, dan "tetamu" merujuk kepada orang yang membuat tempahan.</p>

    <h2><span class="lg-num">2.</span>Kelayakan dan akaun</h2>
    <p>Anda mesti berumur sekurang-kurangnya 18 tahun dan berupaya membuat kontrak yang sah. Anda bertanggungjawab menjaga kerahsiaan kata laluan akaun anda dan semua aktiviti di bawah akaun tersebut. Beritahu kami dengan segera jika anda mengesyaki penggunaan tanpa kebenaran.</p>

    <h2><span class="lg-num">3.</span>Pelan dan bayaran</h2>
    <ul>
        <li><strong>Pelan Percuma (RM 0):</strong> 1 hartanah, 3 bilik, dan sehingga 20 tempahan sebulan.</li>
        <li><strong>Pelan Pro (RM 49/bulan):</strong> hartanah dan tempahan tanpa had, gerbang pembayaran, invois tersuai, WhatsApp automatik, penyegerakan kalendar, penyenaraian pasaran dan banyak lagi. Percubaan 7 hari tanpa kad.</li>
        <li><strong>Komisen pasaran:</strong> untuk tempahan yang berasal dari pasaran tempahlah.com, kami mengenakan komisen <strong>3%</strong>. Tempahan terus melalui halaman anda sendiri <strong>tidak dikenakan komisen</strong>.</li>
    </ul>
    <p>Yuran langganan dibayar pendahuluan setiap bulan dan tidak dikembalikan kecuali dinyatakan sebaliknya. Kami boleh menukar harga dengan notis munasabah; perubahan tidak akan menjejaskan kitaran yang telah dibayar.</p>

    <h2><span class="lg-num">4.</span>Pembayaran tetamu dan pembayaran balik</h2>
    <p>Tuan rumah menyambungkan akaun gerbang pembayaran mereka sendiri (seperti Toyyibpay, Billplz atau SecurePay) atau menerima pembayaran manual (pindahan bank / tunai). Kami memudahkan tempahan tetapi <strong>bukan pihak</strong> kepada urus niaga pembayaran antara tetamu dan tuan rumah bagi tempahan terus. Dasar deposit, pembayaran penuh dan pembayaran balik ditetapkan oleh setiap tuan rumah dan dipaparkan kepada tetamu sebelum tempahan.</p>

    <h2><span class="lg-num">5.</span>Tanggungjawab tuan rumah</h2>
    <ul>
        <li>Memastikan semua maklumat hartanah, harga dan ketersediaan adalah tepat dan terkini.</li>
        <li>Mematuhi semua undang-undang yang berkenaan, termasuk pelesenan MOTAC, cukai (SST dan cukai pelancongan jika berkenaan), serta peraturan tempatan.</li>
        <li>Melayani tetamu dengan adil dan menghormati pembayaran balik serta dasar pembatalan yang anda paparkan.</li>
        <li>Mengendalikan data peribadi tetamu mengikut undang-undang perlindungan data (lihat <a href="{{ url('/privacy') }}">Polisi PDPA</a> kami).</li>
    </ul>
    <div class="lg-callout">Tempahlah tidak memberi nasihat undang-undang, cukai atau pelesenan. Anda bertanggungjawab sepenuhnya untuk mendapatkan lesen dan kelulusan yang diperlukan bagi mengendalikan homestay anda.</div>

    <h2><span class="lg-num">6.</span>Penggunaan yang dilarang</h2>
    <p>Anda bersetuju untuk tidak menyalahgunakan perkhidmatan, termasuk: menyiarkan penyenaraian palsu atau mengelirukan; melanggar hak orang lain; cubaan mengakses sistem tanpa kebenaran; menghantar spam atau perisian hasad; atau menggunakan platform untuk aktiviti menyalahi undang-undang.</p>

    <h2><span class="lg-num">7.</span>Kandungan dan harta intelek</h2>
    <p>Anda mengekalkan pemilikan kandungan yang anda muat naik (foto, penerangan, logo). Anda memberi kami lesen tidak eksklusif untuk memaparkan kandungan tersebut bagi tujuan mengendalikan perkhidmatan, termasuk memaparkan penyenaraian di pasaran. Jenama, perisian dan reka bentuk Tempahlah kekal milik kami.</p>

    <h2><span class="lg-num">8.</span>Ketersediaan dan penafian</h2>
    <p>Kami berusaha memastikan perkhidmatan tersedia tetapi tidak menjaminnya bebas gangguan atau bebas ralat. Perkhidmatan disediakan "sebagaimana adanya" tanpa waranti dalam apa jua bentuk setakat yang dibenarkan oleh undang-undang.</p>

    <h2><span class="lg-num">9.</span>Had liabiliti</h2>
    <p>Setakat maksimum yang dibenarkan undang-undang, Tempahlah tidak bertanggungjawab atas sebarang kerugian tidak langsung, sampingan atau berbangkit. Jumlah liabiliti keseluruhan kami kepada anda tidak melebihi yuran yang anda bayar kepada kami dalam tempoh dua belas (12) bulan sebelumnya.</p>

    <h2><span class="lg-num">10.</span>Penggantungan dan penamatan</h2>
    <p>Anda boleh menamatkan akaun anda pada bila-bila masa. Kami boleh menggantung atau menamatkan akses jika anda melanggar terma ini atau atas sebab operasi yang munasabah. Selepas penamatan, anda boleh meminta eksport data anda dalam tempoh yang munasabah.</p>

    <h2><span class="lg-num">11.</span>Perubahan terma</h2>
    <p>Kami boleh mengemas kini terma ini dari semasa ke semasa. Jika perubahan ketara, kami akan memberi notis munasabah. Penggunaan berterusan selepas perubahan bermakna anda menerima terma yang dikemas kini.</p>

    <h2><span class="lg-num">12.</span>Undang-undang yang mentadbir</h2>
    <p>Terma ini ditadbir oleh undang-undang Malaysia, dan mahkamah Malaysia mempunyai bidang kuasa eksklusif ke atas sebarang pertikaian.</p>

    <h2><span class="lg-num">13.</span>Hubungi kami</h2>
    <p>Soalan tentang terma ini? Emel kami di <a href="mailto:hello@tempahlah.com">hello@tempahlah.com</a>.</p>
@else
    <p class="lg-eyebrow">Terms of Service</p>
    <h1>Terms of Service</h1>
    <p class="lg-meta">Effective {{ $updated }}</p>
    <p class="lg-intro">These terms govern your use of Tempahlah — the homestay management platform and marketplace at <a href="{{ url('/') }}">tempahlah.com</a>. By creating an account or using our services, you agree to these terms.</p>

    <h2><span class="lg-num">1.</span>About Tempahlah</h2>
    <p>Tempahlah provides software (SaaS) for Malaysian homestay operators to manage properties, bookings, payments, invoices and operations, plus a marketplace at tempahlah.com that connects guests with homestays. In these terms, "we" and "us" mean Tempahlah, "host" means a homestay operator who registers an account, and "guest" means a person who makes a booking.</p>

    <h2><span class="lg-num">2.</span>Eligibility and account</h2>
    <p>You must be at least 18 and able to form a binding contract. You are responsible for keeping your account password confidential and for all activity under your account. Tell us promptly if you suspect unauthorised use.</p>

    <h2><span class="lg-num">3.</span>Plans and fees</h2>
    <ul>
        <li><strong>Free plan (RM 0):</strong> 1 property, 3 rooms, and up to 20 bookings per month.</li>
        <li><strong>Pro plan (RM 49/month):</strong> unlimited properties and bookings, payment gateways, custom invoices, WhatsApp automation, calendar sync, marketplace listing and more. 7-day trial, no card required.</li>
        <li><strong>Marketplace commission:</strong> for bookings sourced through the tempahlah.com marketplace, we charge a <strong>3%</strong> commission. Direct bookings through your own page carry <strong>no commission</strong>.</li>
    </ul>
    <p>Subscription fees are billed monthly in advance and are non-refundable unless stated otherwise. We may change pricing with reasonable notice; changes will not affect a cycle you have already paid for.</p>

    <h2><span class="lg-num">4.</span>Guest payments and refunds</h2>
    <p>Hosts connect their own payment gateway account (such as Toyyibpay, Billplz or SecurePay) or accept manual payment (bank transfer / cash). We facilitate bookings but are <strong>not a party</strong> to the payment transaction between a guest and a host for direct bookings. Deposit, full-payment and refund policies are set by each host and shown to the guest before booking.</p>

    <h2><span class="lg-num">5.</span>Host responsibilities</h2>
    <ul>
        <li>Keep all property information, pricing and availability accurate and current.</li>
        <li>Comply with all applicable laws, including MOTAC licensing, taxes (SST and tourism tax where applicable), and local regulations.</li>
        <li>Treat guests fairly and honour the refund and cancellation policies you display.</li>
        <li>Handle guests' personal data in line with data-protection law (see our <a href="{{ url('/privacy') }}">PDPA Privacy Policy</a>).</li>
    </ul>
    <div class="lg-callout">Tempahlah does not provide legal, tax or licensing advice. You are solely responsible for obtaining the licences and approvals needed to operate your homestay.</div>

    <h2><span class="lg-num">6.</span>Prohibited use</h2>
    <p>You agree not to misuse the service, including: posting false or misleading listings; infringing others' rights; attempting to access systems without authorisation; sending spam or malware; or using the platform for unlawful activity.</p>

    <h2><span class="lg-num">7.</span>Content and intellectual property</h2>
    <p>You keep ownership of the content you upload (photos, descriptions, logos). You grant us a non-exclusive licence to display that content in order to operate the service, including showing your listing in the marketplace. Tempahlah's brand, software and design remain ours.</p>

    <h2><span class="lg-num">8.</span>Availability and disclaimer</h2>
    <p>We work to keep the service available but do not guarantee it will be uninterrupted or error-free. The service is provided "as is" without warranties of any kind to the fullest extent permitted by law.</p>

    <h2><span class="lg-num">9.</span>Limitation of liability</h2>
    <p>To the maximum extent permitted by law, Tempahlah is not liable for any indirect, incidental or consequential loss. Our total aggregate liability to you will not exceed the fees you paid us in the preceding twelve (12) months.</p>

    <h2><span class="lg-num">10.</span>Suspension and termination</h2>
    <p>You may close your account at any time. We may suspend or terminate access if you breach these terms or for reasonable operational reasons. After termination you may request an export of your data within a reasonable period.</p>

    <h2><span class="lg-num">11.</span>Changes to these terms</h2>
    <p>We may update these terms from time to time. For material changes we will give reasonable notice. Continued use after a change means you accept the updated terms.</p>

    <h2><span class="lg-num">12.</span>Governing law</h2>
    <p>These terms are governed by the laws of Malaysia, and the courts of Malaysia have exclusive jurisdiction over any dispute.</p>

    <h2><span class="lg-num">13.</span>Contact us</h2>
    <p>Questions about these terms? Email us at <a href="mailto:hello@tempahlah.com">hello@tempahlah.com</a>.</p>
@endif
@endsection
