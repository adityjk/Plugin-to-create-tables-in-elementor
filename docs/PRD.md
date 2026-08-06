# PRD — WP Table Builder Plugin

## 1. Ringkasan
Plugin WordPress untuk membuat dan mengelola tabel kustom secara visual (mirip Advanced Custom Fields, tapi untuk tabel data), dengan builder berbasis Gutenberg block, **Elementor Widget**, dan tampilan frontend yang interaktif (search/sort/filter via DataTables.js).

## 2. Latar Belakang & Masalah
Ekosistem plugin WordPress belum punya solusi table-builder yang:
- Visual (drag/drop, live preview) selevel ACF untuk field.
- Terintegrasi secara fleksibel baik dengan **Gutenberg Block** maupun **Elementor Widget** secara native.
- Bisa dikustomisasi tampilannya (warna, padding, border) tanpa coding CSS manual.
- Ringan, berkinerja tinggi, dan tidak membebani database atau DOM browser saat data tabel berukuran besar (1.000+ baris).

## 3. Tujuan (Goals)
- User (admin situs) bisa membuat tabel baru, menambah/menghapus baris & kolom secara visual di dalam WordPress admin.
- User bisa mengatur styling dasar tabel (warna header, warna baris selang-seling, padding cell, border) tanpa custom CSS.
- Tabel bisa ditampilkan di halaman/post via **Gutenberg Block**, **Elementor Widget**, atau **Shortcode**.
- Pengunjung situs (end user) bisa search, sort, dan filter isi tabel di frontend.
- Memfasilitasi berbagai tipe konten cell (Text, Number, Date, Link, Button, Image, Badge, Rating).
- Plugin aman (bebas celah XSS, CSRF, SQL Injection) dan performan meski jumlah baris data banyak (menggunakan Server-Side processing bila diperlukan).

## 4. Non-Goals (di luar scope v1)
- Import/export dari Excel/CSV (bisa jadi fitur v2).
- Kalkulasi/formula antar cell (seperti spreadsheet).
- Multi-user real-time collaborative editing.
- Relasi antar tabel (seperti database relasional penuh).

## 5. Target Pengguna
- **Admin/Editor situs** — yang membuat & mengatur tabel lewat wp-admin serta menyusun layout via Gutenberg / Elementor.
- **Pengunjung situs** — yang melihat & berinteraksi (search/sort/filter) dengan tabel di frontend.

## 6. User Stories

### Sebagai Admin
- Sebagai admin, saya bisa membuat tabel baru dari menu khusus di wp-admin.
- Sebagai admin, saya bisa menambah/menghapus/mengurutkan ulang kolom.
- Sebagai admin, saya bisa menambah/menghapus/mengurutkan ulang baris.
- Sebagai admin, saya bisa mengedit isi tiap cell langsung di builder (inline editing), baik teks biasa maupun elemen Rich Text/HTML (gambar, tombol, link).
- Sebagai admin, saya bisa mengatur styling tabel: warna header, warna baris ganjil/genap, warna border, ketebalan border, padding cell.
- Sebagai admin, saya bisa insert tabel ke dalam post/page lewat **Gutenberg block**, dengan live preview.
- Sebagai admin, saya bisa insert tabel ke dalam post/page lewat **Elementor Widget** (`\Elementor\Widget_Base`), dengan dropdown pemilih tabel dan live preview di Elementor editor.
- Sebagai admin, saya (opsional) bisa embed tabel yang sama lewat shortcode `[wtb_table id="X"]` di tempat yang tidak mendukung block/elementor editor.
- Sebagai admin, saya bisa duplikat tabel yang sudah ada sebagai starting point tabel baru.

### Sebagai Pengunjung Situs
- Sebagai pengunjung, saya bisa mengetik kata kunci untuk mencari baris tertentu di tabel.
- Sebagai pengunjung, saya bisa klik header kolom untuk sort ascending/descending.
- Sebagai pengunjung, saya bisa filter data berdasarkan kolom tertentu (jika diaktifkan admin).
- Sebagai pengunjung, tabel tetap enak dilihat di perangkat mobile (responsive via horizontal scroll atau collapsible child rows).

## 7. Requirement Fungsional

| ID | Requirement | Prioritas |
|----|-------------|-----------|
| F1 | CRUD tabel (create, read, update, delete) dari admin | Must |
| F2 | CRUD kolom (nama, tipe data, urutan) | Must |
| F3 | CRUD baris & cell | Must |
| F4 | Styling: warna header, warna baris selang-seling, warna & ketebalan border, padding cell | Must |
| F5 | Gutenberg block dengan live preview, pilih tabel by ID/nama | Must |
| F6 | Elementor Widget (`\Elementor\Widget_Base`) dengan live preview & dropdown pilih tabel | Must |
| F7 | Shortcode fallback `[wtb_table id]` | Should |
| F8 | Tipe data cell Rich Text/HTML (Link, Button, Image, Badge, Rating) | Must |
| F9 | Frontend search box | Must |
| F10 | Frontend sort per kolom | Must |
| F11 | Frontend filter per kolom (dropdown) | Should |
| F12 | Responsive frontend (mobile-friendly: pilihan Collapsible Child Rows atau Horizontal Scroll) | Must |
| F13 | Batch Save / Autosave API (simpan perubahan kolom & baris dalam 1 transaksi) | Must |
| F14 | Server-side DataTables AJAX processing (otomatis untuk tabel > 200 baris) | Must |
| F15 | Duplikat tabel | Could |
| F16 | Import/export CSV | Won't (v1) |

## 8. Requirement Non-Fungsional
- **Keamanan**: semua input disanitasi (`sanitize_text_field()` untuk teks biasa, `wp_kses_post()` untuk Rich Text/HTML cell), semua output di-escape, semua request pengubah data diverifikasi nonce & capability (`manage_options`), query database pakai prepared statement.
- **Performa**:
  - Skema database JSON-per-row untuk menghindari overhead `JOIN` ribuan baris.
  - Server-side DataTables Ajax pagination untuk dataset > 200 baris agar DOM browser tidak meledak.
- **Kompatibilitas**: WordPress versi terbaru & minimal 2 versi mayor ke belakang, PHP 7.4+, Elementor v3.0+.
- **Aksesibilitas**: markup tabel semantik (`<table>`, `<th scope>`, dst), bisa dinavigasi keyboard.
- **Isolasi**: styling & script plugin tidak bentrok dengan tema/plugin lain (prefix CSS/JS unik `wtb-`).

## 9. Batasan Teknis
- Backend: PHP (standar WordPress plugin), custom database table untuk data baris (`wp_wtb_rows` dengan JSON `cells_data`), CPT `wtb_table` + post meta untuk metadata & styling tabel.
- Integration Layer: Gutenberg Block (React API) + Elementor Widget (`\Elementor\Widget_Base`).
- Frontend builder: React via `@wordpress/scripts`.
- Frontend publik: DataTables.js (Client-side & Server-Side AJAX mode).
- Tidak boleh menambah dependency berat yang tidak perlu (jaga plugin tetap ringan).

## 10. Metrik Keberhasilan (v1)
- Admin bisa membuat tabel baru & publish ke halaman (via Gutenberg / Elementor) dalam < 5 menit tanpa dokumentasi tambahan.
- Tidak ada celah keamanan dasar (lolos self-check XSS/CSRF/SQLi checklist).
- Tabel dengan 1.000+ baris tetap render frontend < 1 detik (berkat Server-Side DataTables processing).

## 11. Milestone / Fase
1. **Fase 1 — Fondasi & Database**: struktur plugin, custom DB tables (`wp_wtb_columns`, `wp_wtb_rows`), CPT metadata.
2. **Fase 2 — Builder Backend & REST API**: REST API batch save `POST /tables/{id}/save` & data fetch endpoints.
3. **Fase 3 — Builder Frontend (React)**: UI builder di admin (grid editor, rich text cell support, styling panel).
4. **Fase 4 — Gutenberg & Elementor Integration**: Gutenberg block (`wtb/table`) & Elementor Widget (`WTB_Elementor_Table_Widget`).
5. **Fase 5 — Frontend Render & DataTables (Client & Server-Side)**: render publik + DataTables.js integration (search/sort/filter/responsive).
6. **Fase 6 — Hardening**: security review (`wp_kses_post`), unit testing, shortcode fallback, edge case testing.
