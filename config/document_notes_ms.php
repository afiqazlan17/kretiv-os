<?php

// Bahasa Melayu version of config/document_notes.php, used when a document is
// set to BM. Keep the two files in step: same keys, same order.

$quotationValid = 'Sebut harga ini sah selama 14 hari dari tarikh dikeluarkan.';

return [
    'quotation' => [
        'print' => [
            'Harga adalah berdasarkan spesifikasi di atas. Sebarang perubahan reka bentuk, saiz, bahan atau kuantiti selepas pengesahan boleh mengubah harga, dan sebut harga baharu akan dihantar. Kerja dimulakan selepas pesanan disahkan secara bertulis.',
            'Bagi pesanan bawah RM2,000, bayaran penuh diperlukan sebelum kerja dimulakan. Bagi pesanan RM2,000 dan ke atas, deposit 80% diperlukan sebelum draf pertama.',
            'Pengeluaran disiapkan dalam tempoh 14 hari bekerja selepas draf akhir diluluskan.',
            'Artwork yang dibekalkan oleh pelanggan mestilah sedia untuk dicetak. Warna mungkin berbeza sedikit antara skrin dan cetakan. Kelulusan draf akhir adalah tanggungjawab pelanggan.',
            'Deposit tidak akan dikembalikan setelah tempahan disahkan dan draf pertama telah disediakan.',
            'Caj penghantaran dikenakan seperti yang dinyatakan. Kawasan di luar Lembah Klang mungkin dikenakan caj berasingan.',
            $quotationValid,
        ],
        'tech' => [
            'Sebut harga ini meliputi skop kerja yang disenaraikan di atas sahaja. Ciri tambahan atau perubahan akan disebut harga secara berasingan.',
            'Jadual bayaran: deposit 50% sebelum kerja dimulakan, 30% semasa demo sistem/UAT, dan 20% semasa sistem mula digunakan (go-live).',
            'Tempoh kerja bermula selepas deposit dan semua kandungan/bahan yang diperlukan diterima. Kelewatan maklum balas pelanggan boleh melanjutkan tarikh siap.',
            'Sehingga 2 pusingan pindaan disediakan bagi setiap peringkat.',
            'Kos pihak ketiga (domain, hosting, WhatsApp API/yuran Meta, SMS, gerbang pembayaran) tidak termasuk kecuali dinyatakan.',
            'Penyelenggaraan dan sokongan disediakan di bawah perjanjian berasingan. Waranti pembaikan pepijat percuma: 30 hari selepas go-live.',
            'Pemilikan sistem akhir diserahkan kepada pelanggan selepas bayaran penuh.',
            'Deposit tidak akan dikembalikan setelah kerja dimulakan.',
            $quotationValid,
        ],
        'brand' => [
            'Sebut harga ini meliputi hasil kerja yang disenaraikan di atas. Item tambahan atau perubahan skop akan disebut harga secara berasingan.',
            'Bagi pesanan bawah RM2,000, bayaran penuh diperlukan sebelum kerja dimulakan. Bagi pesanan RM2,000 dan ke atas, deposit 50% diperlukan sebelum kerja dimulakan dan baki sebelum fail akhir diserahkan.',
            'Sehingga 2 pusingan pindaan reka bentuk disediakan. Pindaan tambahan dicaj secara berasingan.',
            'Pelanggan membekalkan semua kandungan, teks, gambar dan logo. Kelewatan kandungan atau maklum balas boleh melanjutkan tempoh kerja.',
            'Fail artwork akhir dan hak penggunaan diserahkan kepada pelanggan selepas bayaran penuh.',
            'Imej stok, fon dan muzik dilesenkan mengikut keperluan. Yuran lesen, jika ada, tidak termasuk kecuali dinyatakan.',
            'Kos iklan dan boosting platform tidak termasuk.',
            'Kretivco berhak memaparkan hasil kerja yang telah siap dalam portfolio kecuali diminta sebaliknya secara bertulis.',
            'Deposit tidak akan dikembalikan setelah kerja dimulakan.',
            $quotationValid,
        ],
        'event' => [
            'Sebut harga ini berdasarkan tarikh, tempat, tempoh dan skop acara yang dinyatakan di atas. Sebarang perubahan boleh menjejaskan harga dan akan disebut harga secara bertulis.',
            'Tarikh acara hanya disahkan selepas deposit diterima. Deposit 50% diperlukan untuk mengesahkan tempahan, dan baki perlu dibayar 7 hari sebelum acara.',
            'Perubahan skop atau kuantiti mesti disahkan sekurang-kurangnya 7 hari sebelum acara.',
            'Sewa tempat, permit, lesen, keselamatan dan utiliti tidak termasuk kecuali dinyatakan.',
            'Pemasangan dan pembukaan termasuk dalam masa yang dipersetujui. Kerja lebih masa atau sekatan tempat boleh dikenakan caj tambahan.',
            'Bagi acara luar, perubahan disebabkan cuaca bukan tanggungjawab Kretivco. Penjadualan semula tertakluk kepada kekosongan pasukan dan peralatan.',
            'Pembatalan: lebih 30 hari sebelum acara, 50% daripada deposit akan dikembalikan. 30 hari atau kurang, deposit tidak akan dikembalikan.',
            'Pelanggan bertanggungjawab atas kerosakan peralatan sewaan yang disebabkan oleh tetamu atau kakitangan tempat acara.',
            $quotationValid,
        ],
    ],

    'proforma' => [
        'default' => [
            'Ini ialah invois proforma untuk memohon bayaran sebelum kerja dimulakan. Ia bukan invois cukai.',
            'Kerja hanya dimulakan selepas bayaran diterima dan disahkan.',
            'Bayaran perlu dibuat dalam tempoh 7 hari dari tarikh dikeluarkan. Sebut harga boleh luput jika bayaran tidak diterima.',
            'Sila buat bayaran ke akaun bank yang dinyatakan di atas dan hantar bukti bayaran ke :contact, dengan menyatakan nombor proforma sebagai rujukan.',
            'Invois cukai akan dikeluarkan untuk baki (jika ada) atau selepas kerja siap.',
            'Deposit tidak akan dikembalikan seperti terma dalam sebut harga.',
        ],
        'event' => [
            'Ini ialah invois proforma untuk memohon bayaran sebelum kerja dimulakan. Ia bukan invois cukai.',
            'Kerja hanya dimulakan selepas bayaran diterima dan disahkan.',
            'Deposit perlu dibayar dalam tempoh 7 hari dari tarikh dikeluarkan untuk mengesahkan tarikh acara.',
            'Sila buat bayaran ke akaun bank yang dinyatakan di atas dan hantar bukti bayaran ke :contact, dengan menyatakan nombor proforma sebagai rujukan.',
            'Invois cukai akan dikeluarkan untuk baki (jika ada) atau selepas kerja siap.',
            'Deposit tidak akan dikembalikan seperti terma dalam sebut harga.',
        ],
    ],

    'invoice' => [
        'default' => [
            'Bayaran perlu dibuat dalam tempoh 14 hari dari tarikh invois kecuali dipersetujui sebaliknya.',
            'Sila buat bayaran ke akaun bank yang dinyatakan di atas dan hantar bukti bayaran ke :contact.',
            'Sila nyatakan nombor invois sebagai rujukan bayaran.',
            'Sebarang pertanyaan mengenai invois ini mesti dibuat dalam tempoh 7 hari dari tarikh invois.',
            'Kelewatan bayaran boleh melambatkan pengeluaran, penghantaran atau penyerahan fail akhir.',
        ],
        'tech' => [
            'Bayaran perlu dibuat dalam tempoh 14 hari dari tarikh invois kecuali dipersetujui sebaliknya.',
            'Sila buat bayaran ke akaun bank yang dinyatakan di atas dan hantar bukti bayaran ke :contact.',
            'Sila nyatakan nombor invois sebagai rujukan bayaran.',
            'Sebarang pertanyaan mengenai invois ini mesti dibuat dalam tempoh 7 hari dari tarikh invois.',
            'Kelewatan bayaran boleh melambatkan pengeluaran, penghantaran atau penyerahan fail akhir.',
            'Akses sistem akan diberikan selepas bayaran penuh.',
        ],
        'brand' => [
            'Bayaran perlu dibuat dalam tempoh 14 hari dari tarikh invois kecuali dipersetujui sebaliknya.',
            'Sila buat bayaran ke akaun bank yang dinyatakan di atas dan hantar bukti bayaran ke :contact.',
            'Sila nyatakan nombor invois sebagai rujukan bayaran.',
            'Sebarang pertanyaan mengenai invois ini mesti dibuat dalam tempoh 7 hari dari tarikh invois.',
            'Kelewatan bayaran boleh melambatkan pengeluaran, penghantaran atau penyerahan fail akhir.',
            'Fail akhir akan diserahkan selepas bayaran penuh.',
        ],
        'event' => [
            'Baki bayaran perlu dibuat 7 hari sebelum tarikh acara.',
            'Sila buat bayaran ke akaun bank yang dinyatakan di atas dan hantar bukti bayaran ke :contact.',
            'Sila nyatakan nombor invois sebagai rujukan bayaran.',
            'Sebarang pertanyaan mengenai invois ini mesti dibuat dalam tempoh 7 hari dari tarikh invois.',
            'Kelewatan bayaran boleh melambatkan pengeluaran, penghantaran atau penyerahan fail akhir.',
        ],
    ],

    'receipt_deposit' => [
        'default' => [
            'Resit ini mengesahkan deposit telah diterima bagi sebut harga/proforma yang dinyatakan di atas.',
            'Bayaran cek hanya sah selepas cek dijelaskan.',
            'Deposit tidak akan dikembalikan seperti terma dalam sebut harga.',
            'Jadual kerja disahkan dari tarikh deposit ini diterima.',
            'Sila simpan resit ini untuk rekod anda.',
        ],
    ],
    'receipt' => [
        'default' => [
            'Resit ini mengesahkan bayaran penuh telah diterima bagi invois yang dinyatakan di atas.',
            'Bayaran cek hanya sah selepas cek dijelaskan.',
            'Sila simpan resit ini untuk rekod anda.',
        ],
    ],

    'delivery' => [
        'print' => [
            'Barang dihantar dalam keadaan baik kecuali dinyatakan sebaliknya.',
            'Sila semak kuantiti dan keadaan barang semasa penerimaan.',
            'Sebarang kekurangan atau kerosakan mesti dilaporkan dalam tempoh 3 hari dari tarikh penghantaran. Tuntutan selepas tempoh itu tidak akan diterima.',
            'Sila tandatangan dan cop nota penghantaran ini sebagai bukti penerimaan.',
        ],
        'default' => [
            'Pelanggan mengesahkan penerimaan hasil kerja yang disenaraikan di atas dan ia telah diserahkan seperti yang dipersetujui.',
            'Sebarang kecacatan mesti dilaporkan dalam tempoh 7 hari dari tarikh serahan.',
            'Sila tandatangan dan cop sebagai bukti penerimaan.',
        ],
    ],

    'credit_note' => [
        'default' => [
            'Nota kredit ini dikeluarkan terhadap nombor invois yang dinyatakan di atas.',
            'Jumlah yang dikreditkan akan ditolak daripada baki tertunggak atau dikembalikan, seperti yang dipersetujui secara bertulis.',
            'Sebab kredit: :reason.',
            'Nota kredit ini tidak mengubah terma bayaran bagi invois lain.',
        ],
    ],
];
