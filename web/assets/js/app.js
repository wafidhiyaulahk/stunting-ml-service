/* =====================================================================
   Skrip umum: pop-up (modal), dropdown wilayah bertingkat, penghitung
   skor keragaman makanan.
   ===================================================================== */

/* ----------------------------- Pop-up ----------------------------- */
function bukaModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('tampil'); document.body.style.overflow = 'hidden'; }
}
function tutupModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('tampil'); document.body.style.overflow = ''; }
}

/** Isi pop-up dari data JSON yang menempel pada tombol (atribut data-detail). */
function bukaDetail(idModal, tombol, judul) {
    const data = JSON.parse(tombol.getAttribute('data-detail'));
    const modal = document.getElementById(idModal);
    modal.querySelector('.modal-kepala h3').textContent = judul;
    const dl = modal.querySelector('.modal-isi dl');
    dl.innerHTML = '';
    Object.entries(data).forEach(([k, v]) => {
        const dt = document.createElement('dt');
        dt.textContent = k;
        const dd = document.createElement('dd');
        dd.innerHTML = v;                 // nilai sudah di-escape di sisi PHP
        dl.appendChild(dt); dl.appendChild(dd);
    });
    bukaModal(idModal);
}

document.addEventListener('click', (ev) => {
    if (ev.target.classList.contains('modal-latar')) {
        ev.target.classList.remove('tampil');
        document.body.style.overflow = '';
    }
});
document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') {
        document.querySelectorAll('.modal-latar.tampil')
            .forEach(m => m.classList.remove('tampil'));
        document.body.style.overflow = '';
    }
});

/* -------------------- Dropdown wilayah bertingkat -------------------- */
/**
 * Mengisi pilihan RW lalu RT berdasarkan kelurahan yang dipilih.
 * Data diambil dari endpoint api_wilayah.php agar tidak perlu memuat
 * seluruh kombinasi wilayah ke dalam halaman.
 */
function siapkanWilayah(prefix, baseUrl, terpilih) {
    const selKel = document.getElementById(prefix + '_kelurahan');
    const selRw  = document.getElementById(prefix + '_rw');
    const selRt  = document.getElementById(prefix + '_rt');
    if (!selKel || !selRw || !selRt) return;
    terpilih = terpilih || {};

    function isi(select, daftar, placeholder, nilaiTerpilih) {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        daftar.forEach(v => {
            const o = document.createElement('option');
            o.value = v; o.textContent = v;
            if (String(v) === String(nilaiTerpilih)) o.selected = true;
            select.appendChild(o);
        });
        select.disabled = daftar.length === 0;
    }

    async function muatRw(nilaiRw, nilaiRt) {
        if (!selKel.value) { isi(selRw, [], 'Pilih kelurahan dahulu'); isi(selRt, [], 'Pilih RW dahulu'); return; }
        const r = await fetch(baseUrl + '/api_wilayah.php?kelurahan=' + encodeURIComponent(selKel.value));
        isi(selRw, await r.json(), 'Pilih RW', nilaiRw);
        if (nilaiRw) muatRt(nilaiRt); else isi(selRt, [], 'Pilih RW dahulu');
    }
    async function muatRt(nilaiRt) {
        if (!selRw.value) { isi(selRt, [], 'Pilih RW dahulu'); return; }
        const r = await fetch(baseUrl + '/api_wilayah.php?kelurahan='
            + encodeURIComponent(selKel.value) + '&rw=' + encodeURIComponent(selRw.value));
        isi(selRt, await r.json(), 'Pilih RT', nilaiRt);
    }

    // Penanda agar listener tidak terpasang dua kali ketika fungsi ini
    // dipanggil ulang, misalnya setiap kali pop-up edit dibuka.
    if (!selKel.dataset.siap) {
        selKel.addEventListener('change', () => muatRw());
        selRw.addEventListener('change', () => muatRt());
        selKel.dataset.siap = '1';
    }

    if (terpilih.kelurahan) {
        selKel.value = terpilih.kelurahan;
        muatRw(terpilih.rw, terpilih.rt);
    } else {
        isi(selRw, [], 'Pilih kelurahan dahulu');
        isi(selRt, [], 'Pilih RW dahulu');
    }
}

/* ---------------- Penghitung skor keragaman makanan ---------------- */
function siapkanPenghitungMakanan() {
    const kotak = document.querySelectorAll('input[name="makanan[]"]');
    const tampil = document.getElementById('skor_makanan');
    if (!kotak.length || !tampil) return;
    const perbarui = () => {
        const n = document.querySelectorAll('input[name="makanan[]"]:checked').length;
        tampil.textContent = n + ' dari ' + kotak.length + ' kelompok makanan tercentang';
    };
    kotak.forEach(k => k.addEventListener('change', perbarui));
    perbarui();
}

/* ------------------------- Usia otomatis ------------------------- */
function siapkanUsiaOtomatis() {
    const tgl = document.getElementById('tanggal_lahir');
    const tampil = document.getElementById('info_usia');
    if (!tgl || !tampil) return;
    const hitung = () => {
        if (!tgl.value) { tampil.textContent = ''; return; }
        const lahir = new Date(tgl.value), kini = new Date();
        if (lahir > kini) { tampil.textContent = 'Tanggal lahir tidak boleh melebihi hari ini.'; return; }
        let bulan = (kini.getFullYear() - lahir.getFullYear()) * 12 + (kini.getMonth() - lahir.getMonth());
        if (kini.getDate() < lahir.getDate()) bulan--;
        tampil.textContent = bulan > 59
            ? 'Usia ' + bulan + ' bulan. Aplikasi ini ditujukan untuk balita usia 0-59 bulan.'
            : 'Usia anak saat ini ' + bulan + ' bulan.';
    };
    tgl.addEventListener('change', hitung);
    hitung();
}

document.addEventListener('DOMContentLoaded', () => {
    siapkanPenghitungMakanan();
    siapkanUsiaOtomatis();
});

/* --------------------- Sidebar admin (laci di HP) --------------------- */
function bukaSidebar()  { document.body.classList.add('sidebar-terbuka'); }
function tutupSidebar() { document.body.classList.remove('sidebar-terbuka'); }
document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') tutupSidebar(); });
window.addEventListener('resize', () => { if (window.innerWidth > 1024) tutupSidebar(); });

/* ----------------------- Tooltip grafik dashboard ----------------------- */
/**
 * Setiap elemen grafik yang memiliki atribut data-tip (berisi JSON) akan
 * menampilkan tooltip saat disentuh atau disorot kursor.
 */
function siapkanTooltipGrafik() {
    const target = document.querySelectorAll('[data-tip]');
    if (!target.length) return;
    const tip = document.createElement('div');
    tip.className = 'tooltip-grafik';
    tip.setAttribute('role', 'tooltip');
    document.body.appendChild(tip);

    const tampil = (el, x, y) => {
        const d = JSON.parse(el.getAttribute('data-tip'));
        tip.innerHTML = '<b>' + d.judul + '</b>' + d.baris.map(b =>
            '<div><i style="background:' + b.warna + '"></i>' + b.teks + '</div>').join('');
        tip.style.left = x + 'px';
        tip.style.top = y + 'px';
        tip.classList.add('tampil');
    };
    const sembunyi = () => tip.classList.remove('tampil');

    target.forEach(el => {
        el.addEventListener('mousemove', ev => tampil(el, ev.clientX, ev.clientY));
        el.addEventListener('mouseleave', sembunyi);
        el.addEventListener('touchstart', ev => {
            const t = ev.touches[0];
            tampil(el, t.clientX, t.clientY);
        }, { passive: true });
    });
    document.addEventListener('touchstart', ev => {
        if (!ev.target.closest('[data-tip]')) sembunyi();
    }, { passive: true });
    window.addEventListener('scroll', sembunyi, { passive: true });
}
document.addEventListener('DOMContentLoaded', siapkanTooltipGrafik);

/* -------------------- Penyaring tabel tanpa muat ulang -------------------- */
document.addEventListener('DOMContentLoaded', () => {
    const kotak = document.getElementById('saring-tabel');
    const tabel = document.getElementById('tabel-data');
    if (!kotak || !tabel) return;
    kotak.addEventListener('input', () => {
        const q = kotak.value.trim().toLowerCase();
        tabel.querySelectorAll('tbody tr').forEach(tr => {
            if (tr.querySelector('.kosong')) return;
            tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});
