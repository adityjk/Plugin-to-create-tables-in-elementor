# Review Lengkap Fungsi & Fitur — WP Table Builder Plugin

Dokumen ini berisi dokumentasi dan review komprehensif atas seluruh fungsi, arsitektur, API, modul render, integrasi Elementor & Gutenberg, serta fitur-fitur yang ada di plugin **WP Table Builder**.

---

## 📋 Ringkasan Umum Plugin

| Parameter | Detail |
|---|---|
| **Nama Plugin** | WP Table Builder |
| **Versi** | 1.0.7 |
| **Teknologi Backend** | PHP 7.4+ / WordPress REST API / Custom Database Tables |
| **Integrasi Page Builder** | Elementor Widget (`\Elementor\Widget_Base`), Gutenberg Block (`wtb/table`), Shortcode |
| **Frontend Renderer** | DataTables.js (Client-side & Server-side Processing) + Vanilla CSS/JS |
| **Skema Database** | JSON-per-Row (`{prefix}wtb_rows`) untuk performa tinggi tanpa SQL JOIN berat |

---

## 🏗️ 1. Arsitektur Database & Activation

### 1.1 Custom Database Tables (class-activator.php)
Plugin menggunakan 2 tabel kustom yang dibuat via `dbDelta()` saat aktivasi:
1. **`{prefix}wtb_columns`**: Menyimpan definisi kolom tabel (ID, `table_id`, `label`, `data_type`, `sort_order`).
2. **`{prefix}wtb_rows`**: Menyimpan data baris dalam format JSON-per-Row (`cells_data`), `sort_order`, dan `status` (`published` / `pending`).

### 1.2 Custom Post Type (class-post-type.php)
- **`wtb_table`**: Custom Post Type internal (non-public) untuk menyimpan judul tabel dan metadata pengaturan/styling dalam post meta `_wtb_settings`.

---

## 🛡️ 2. Sanitasi & Keamanan (class-sanitizer.php)

- **`WTB_Sanitizer::plain_text()`**: Sanitasi teks standar menggunakan `sanitize_text_field()`.
- **`WTB_Sanitizer::data_type()`**: Validasi tipe data kolom yang diperbolehkan (`text`, `number`, `date`, `richtext`, `link`, `button`, `image`, `badge`, `rating`, `file`).
- **`WTB_Sanitizer::cell_value()`**: Sanitasi kontekstual per sel data:
  - `richtext`: Menggunakan **`wp_kses_post()`** (memperbolehkan tag HTML aman seperti `<a>`, `<img>`, `<b>`, menghapus `<script>` & XSS payload).
  - `link` / `image` / `file`: Menggunakan `esc_url_raw()`.
  - `number` / `rating`: Validasi numerik `strval()`.
- **`WTB_Sanitizer::table_settings()`**: Sanitasi hex warna (`sanitize_hex_color()`), integer (`absint()`), dan flag opsi tabel (`enable_search`, `enable_sort`, `enable_taxonomy_filter`, `enable_form_submission`, `form_require_approval`).

---

## ⚡ 3. REST API Controller (class-rest-controller.php)

Namespace: `wtb/v1`

| Method | Endpoint | Hak Akses | Fungsi & Deskripsi |
|---|---|---|---|
| `GET` | `/tables` | Admin (`edit_posts`) | Mengambil daftar semua tabel untuk pilihan dropdown admin. |
| `GET` | `/tables/{id}` | Admin (`edit_posts`) | Mengambil detail 1 tabel (metadata, skema kolom, dan baris data). |
| `POST` | `/tables` | Admin (`manage_options`) | Membuat tabel baru. |
| `DELETE` | `/tables/{id}` | Admin (`manage_options`) | Menghapus tabel beserta seluruh kolom & baris di DB. |
| `POST` | `/tables/{id}/save` | Admin (`manage_options`) | **Batch Save Transaction**: Menyimpan perubahan kolom, baris, dan settings dalam 1 transaksi DB. |
| `POST` | `/tables/{id}/duplicate` | Admin (`manage_options`) | Menduplikat tabel beserta skema kolom & baris data. |
| `GET` | `/tables/{id}/data` | Publik (`__return_true`) | **DataTables Server-side AJAX Endpoint**: Digunakan untuk tabel > 200 baris (pagination, search, sorting server-side, memfilter status `published`). |
| `POST` | `/tables/{id}/submit` | Publik (`__return_true`) | **Form Submission & Webhook Endpoint**: Menerima input dari pengunjung situs atau Webhook Elementor Form, melakukan auto-mapping ke kolom tabel, dan menyimpan baris baru (`published` atau `pending`). |

---

## 🎨 4. Render Layer & Frontend Output (class-render.php)

- **`WTB_Render::render_table( $table_id, $override_settings )`**:
  - Merender markup semantik `<table><thead>...</thead><tbody>...</tbody></table>`.
  - Injecting CSS dinamis per-tabel (width, padding, header bg, row stripe, border radius, shadow).
  - Menyematkan **Inline Header Taxonomy Dropdown Filter** (`<select class="wtb-header-tax-select">`) di dalam cell `<th>` di sebelah judul kolom.
  - Mendukung dual render mode (Client-side vs Server-side AJAX threshold > 200 baris).
- **`WTB_Render::render_cell_value()`**: Output HTML per tipe data (Rich Text, Link button, Badge status, Star rating, Image lightbox, File preview download button).
- **`WTB_Render::render_form( $table_id )`**: Merender UI form pengisian data publik berbasis grid input.

---

## 🧩 5. Integrasi Page Builder & Shortcode

### 5.1 Elementor Widget (class-elementor-widget.php)
- **`WTB_Elementor_Widget`**: Meng-extend `\Elementor\Widget_Base`.
- **Tab Content**:
  - Pilihan Dropdown Tabel CPT `wtb_table`.
  - Switcher Search Box, Length Page Size, Info Status Data, Pagination.
  - Pilihan Tipe Pagination (Numbers / Indicator Dots).
  - Custom Teks & Icon Tombol Prev/Next (`\Elementor\Icons_Manager`).
  - Switcher File Preview Modal.
  - Switcher **Aktifkan Filter Taxonomy / Kategori** & **Form Input Pengunjung / Moderasi**.
- **Tab Style**: Controls lengkap untuk Width, Max Width, Height, Alignment, Border Radius, Box Shadow, Header BG/Text Color, Stripe Row, Hover Color, Tipografi Tabel & Header, serta Styling Tombol Pagination & Dots.
- **Tab Advanced**: 100% mendukung kontrol standar Elementor (Margin, Padding, Motion Effects, Responsive Visibility, CSS Custom).

### 5.2 Gutenberg Block (class-block.php)
- Block name: `wtb/table`.
- Dynamic rendering via `render_callback` di PHP.

### 5.3 Shortcode Handlers
- **`[wtb_table id="X"]`** (class-shortcode.php): Menampilkan tabel di post/page.
- **`[wtb_table_form id="X"]`** (class-form-shortcode.php): Menampilkan form pengisian data publik di post/page.

---

## 🔗 6. Integrasi Elementor Form (Elementor Form Linking) (class-elementor-form-integration.php)

1. **Elementor Pro Form Hook**: Memantau event `elementor_pro/forms/new_record` atau `elementor/forms/new_record`.
2. **Auto Field Mapping**: Mengambil data input form Elementor dan secara otomatis memetakan nama/ID field Elementor ke nama kolom tabel WP Table Builder.
3. **Webhook Support**: Endpoint `POST /wp-json/wtb/v1/tables/{ID}/submit` dapat dimasukkan ke setting Webhook Action di Elementor Form untuk pengiriman data lintas situs / form.

---

## 💻 7. Assets Frontend (JS & CSS)

### 7.1 JavaScript (assets/js/frontend.js)
- **DataTables Init & Re-init**: Inisialisasi DataTables client-side / server-side mode. Kompatibel dengan live preview Elementor editor iframe (`elementor/frontend/init`).
- **Inline Header Taxonomy Filter**: Event handler `.wtb-header-tax-select` yang terhubung ke DataTables `dt.column().search()` dengan `event.stopPropagation()` agar tidak memicu sorting header.
- **Interactive File Preview Modal**: Modal preview instan di browser untuk file PDF (iframe), Gambar (lightbox), Audio (player HTML5), dan Video (player HTML5).
- **Form Submission Handler**: Event listener `.wtb-user-submit-form` yang mengirimkan data via AJAX POST, menampilkan pesan notifikasi sukses/gagal, dan meng-auto reload DataTables.

### 7.2 Styling (assets/css/frontend.css)
- Visual DataTables modern dengan border-collapse clean.
- Inline Header Dropdown Styling dengan panah SVG mini yang menyatu dengan warna header.
- Modal backdrop blur overlay untuk preview file.
- Form submission grid dan alert notification styling.

---

## ✅ Kesimpulan & Rekomendasi

Plugin **WP Table Builder** memiliki arsitektur yang solid, aman, dan kaya fitur:
1. **Performa Terjamin**: Skema JSON-per-row + Server-side AJAX threshold menjaga performa situs tetap cepat meski tabel berisi ribuan data.
2. **Fleksibilitas Integrasi**: Mendukung Gutenberg, Elementor Widget, Shortcode, serta Webhook Elementor Form.
3. **Desain Modern**: Header taxonomy filter dan file preview modal memberikan UX sekelas aplikasi modern.
