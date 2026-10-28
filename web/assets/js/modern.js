/* =====================================================================
   SiDeteksi Stunting — gerak halus tampilan modern
   ---------------------------------------------------------------------
   Dua pekerjaan saja:
   1. Menandai kepala halaman ketika halaman mulai digulir, supaya
      bayangannya muncul dan latarnya menebal.
   2. Memunculkan bagian isi secara berurutan ketika masuk ke layar.

   Berkas ini bersifat pelengkap. Bila JavaScript dimatikan peramban,
   seluruh isi halaman tetap tampil normal karena kelas penyembunyi baru
   dipasang dari sini.
   ===================================================================== */
(function () {
    'use strict';

    var kurangiGerak = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 1. Kepala halaman menebal saat digulir ---------- */
    var kepala = document.querySelector('.nav-depan, .kepala-pengguna');
    if (kepala) {
        var perbaruiKepala = function () {
            kepala.classList.toggle('tergulir', window.scrollY > 8);
        };
        perbaruiKepala();
        window.addEventListener('scroll', perbaruiKepala, { passive: true });
    }

    /* ---------- 2. Isi halaman muncul perlahan ---------- */
    // Tanpa IntersectionObserver (peramban lama), bagian ini dilewati dan
    // halaman tampil apa adanya.
    if (kurangiGerak || !('IntersectionObserver' in window)) { return; }

    var sasaran = document.querySelectorAll(
        '.bagian, .kartu, .panel, .kartu-p, .fitur, .penyebab, .langkah-kartu, ' +
        '.langkah-p, .tips, .faq, .fakta, .kartu-anak, .posyandu-k, .riwayat-item, .saran-kartu'
    );
    if (!sasaran.length) { return; }

    document.documentElement.classList.add('js-muncul');

    var pengamat = new IntersectionObserver(function (baris) {
        baris.forEach(function (satu) {
            if (!satu.isIntersecting) { return; }
            satu.target.classList.add('tampil');
            pengamat.unobserve(satu.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: .08 });

    Array.prototype.forEach.call(sasaran, function (el, i) {
        el.classList.add('muncul');
        // Jeda bertingkat hanya untuk beberapa elemen pertama pada tiap
        // kelompok, supaya terasa berirama tanpa membuat pengguna menunggu.
        el.style.transitionDelay = (Math.min(i % 4, 3) * 70) + 'ms';
        pengamat.observe(el);
    });

    // Elemen yang sudah terlihat sejak awal dimunculkan segera agar bagian
    // atas halaman tidak berkedip saat dibuka.
    window.requestAnimationFrame(function () {
        Array.prototype.forEach.call(sasaran, function (el) {
            if (el.getBoundingClientRect().top < window.innerHeight * 0.92) {
                el.classList.add('tampil');
            }
        });
    });
}());