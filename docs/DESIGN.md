# Design Document — WP Table Builder Plugin

## 1. Arsitektur Umum

```
┌─────────────────────────────┐
│   wp-admin (React builder)  │  ← bikin/edit tabel, cell (Rich Text), & styling
│   @wordpress/element         │
└──────────────┬───────────────┘
               │ REST API (fetch / batch save)
┌──────────────▼───────────────┐
│   PHP Backend (Plugin Core)   │
│   - REST controllers          │
│   - Batch Transaction Handler │
│   - Capability & Nonce check  │
│   - wp_kses_post / Sanitize   │
└──────────────┬───────────────┘
               │ $wpdb (No JOINs required)
┌──────────────▼───────────────┐
│   Database                    │
│   - wp_posts (CPT: wtb_table) │  ← metadata + styling (post meta JSON)
│   - wp_wtb_columns (custom)   │  ← definisi kolom & tipe data
│   - wp_wtb_rows (custom)      │  ← 1 baris = 1 record (cells_data disimpan sebagai JSON)
└───────────────────────────────┘
               │
 ┌─────────────┴───────────────┬─────────────────────────────┐
 │                             │                             │
┌▼────────────────────────────┐▼────────────────────────────┐▼────────────────────────────┐
│ Gutenberg Block (React)     │ Elementor Widget (PHP)      │ Shortcode [wtb_table id]    │
│ Dynamic block render via    │ \Elementor\Widget_Base      │ PHP handler (DRY)           │
│ PHP render_callback         │ Live preview di Editor      │                             │
└──────────────┬──────────────┴──────────────┬──────────────┴──────────────┬─────────────┘
               │                             │                             │
               └─────────────────────────────┼─────────────────────────────┘
                                             │
                              ┌──────────────▼───────────────┐
                              │  Frontend publik (browser)   │
                              │  HTML table + DataTables.js  │  ← Search/Sort/Filter/Pagination
                              │  (Client-side / Server-side) │     (Mobile Responsive)
                              └──────────────────────────────┘
```

## 2. Skema Database

### 2.1 CPT `wtb_table` (metadata & styling)
Disimpan sebagai post biasa dengan `post_type = 'wtb_table'`. Post meta menyimpan JSON styling & opsi render:

```json
{
  "header_bg": "#2271b1",
  "header_text": "#ffffff",
  "row_stripe": true,
  "row_stripe_color": "#f5f5f5",
  "border_color": "#dddddd",
  "border_width": 1,
  "cell_padding": 8,
  "enable_search": true,
  "enable_sort": true,
  "filter_columns": ["kategori"],
  "responsive_mode": "collapse",
  "server_side_threshold": 200
}
```
Meta key: `_wtb_settings` (single value, JSON string, di-`sanitize_text_field` sebelum simpan lalu `json_decode` saat baca).

### 2.2 Custom table: `{prefix}wtb_columns`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| table_id | BIGINT UNSIGNED, INDEX | FK ke post ID `wtb_table` |
| label | VARCHAR(255) | nama kolom ditampilkan |
| data_type | VARCHAR(20) | text / number / date / richtext / button / image / badge / rating |
| sort_order | INT | urutan tampil |

### 2.3 Custom table: `{prefix}wtb_rows` (JSON-per-Row Schema)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| table_id | BIGINT UNSIGNED, INDEX | FK ke post ID `wtb_table` |
| cells_data | LONGTEXT | JSON object format `{"col_1": "value", "col_2": "value"}` |
| sort_order | INT | urutan tampil baris |

> **Kenapa Skema JSON-per-Row (Menggantikan Pola EAV `wtb_cells`)**:
> 1. Mengurangi total record database secara dramatis. Untuk 1.000 baris $\times$ 10 kolom, pola EAV membutuhkan 10.000 record di tabel `wtb_cells`, sedangkan JSON-per-row hanya membutuhkan **1.000 record** di `wtb_rows`.
> 2. Menghilangkan kebutuhan SQL `JOIN` yang berat. Query data cukup: `SELECT * FROM wp_wtb_rows WHERE table_id = %d ORDER BY sort_order`. Super cepat dan ringan RAM!

Struktur tabel dibuat via `dbDelta()` saat plugin activation hook (`register_activation_hook`).

## 3. REST API Endpoints

Namespace: `wtb/v1`

| Method | Endpoint | Fungsi | permission_callback |
|---|---|---|---|
| GET | `/tables` | list semua tabel (untuk admin dropdown) | `edit_posts` |
| GET | `/tables/{id}` | detail 1 tabel + kolom + baris data | `edit_posts` (admin) / public |
| POST | `/tables` | buat tabel baru | `manage_options` |
| PUT | `/tables/{id}` | update metadata/styling | `manage_options` |
| DELETE | `/tables/{id}` | hapus tabel | `manage_options` |
| POST | `/tables/{id}/save` | **Batch save** (simpan kolom + baris + settings dalam 1 DB transaction) | `manage_options` |
| GET | `/tables/{id}/data` | **DataTables Server-side AJAX** endpoint (pagination, search, sorting) | `public` |

### 3.1 Batch Save Payload Contoh (`POST /wtb/v1/tables/{id}/save`)
```json
{
  "settings": { "header_bg": "#2271b1", "enable_search": true },
  "columns": [
    { "id": 1, "label": "Nama Produk", "data_type": "richtext", "sort_order": 1 },
    { "id": 2, "label": "Harga", "data_type": "number", "sort_order": 2 }
  ],
  "rows": [
    { "id": 101, "sort_order": 1, "cells_data": { "col_1": "<a href='#'>Sepatu A</a>", "col_2": "250000" } },
    { "id": 102, "sort_order": 2, "cells_data": { "col_1": "<a href='#'>Sepatu B</a>", "col_2": "300000" } }
  ]
}
```
Setiap request `POST /save` dieksekusi di dalam transaksi database (`$wpdb->query('START TRANSACTION')`), sehingga data kolom dan baris di-sync secara konsisten.

## 4. Alur Kerja Admin (Builder)

1. Admin buka menu **Table Builder** di wp-admin (render React root `<div id="wtb-builder-root">`).
2. React app fetch daftar tabel via `GET /wtb/v1/tables`.
3. Admin klik "Tambah Tabel Baru" → modal input nama → `POST /wtb/v1/tables`.
4. Masuk ke editor tabel: tampil grid kolom x baris dengan dukungan **Rich Text inline editor** (tombol, link, gambar).
5. Panel samping: styling (color picker header/border/stripe, slider padding, toggle search/sort/filter).
6. **Autosave / Manual Save**: Menggunakan debounced HTTP request ke `POST /wtb/v1/tables/{id}/save` (mengirimkan seluruh state yang diperbarui sekaligus).

## 5. Gutenberg Block

- Nama block: `wtb/table`.
- Attribute utama: `tableId` (integer).
- Live preview di editor menggunakan Dynamic Block (`render_callback` di PHP).
- Saat disimpan/publish: hanya attribute `tableId` yang disimpan di post content (mencegah data stale).

## 6. Elementor Widget Integration

- File: `includes/class-elementor-widget.php` (`class WTB_Elementor_Table_Widget extends \Elementor\Widget_Base`).
- **Penting**: Seluruh tab **Advanced** (Margin, Padding, Width, Z-Index, Position, CSS ID, CSS Classes) disediakan **100% otomatis oleh Elementor** tanpa penulisan kode manual.
- Kontrol yang didaftarkan di PHP:
  - **Tab Content**: Dropdown `SELECT` memilih ID Tabel dari CPT `wtb_table`, Toggle Search/Sort bar.
  - **Tab Style**: Color picker untuk Override Header Background & Border.
- Method `render()` memanggil fungsi terpusat `wtb_render_table_html($table_id)` untuk konsistensi dengan Gutenberg & Shortcode.
- Di dalam Elementor Editor, script DataTables di-reinisialisasi via JS hook Elementor (`elementor/frontend/init`).

## 7. Shortcode Fallback

`[wtb_table id="5"]` — parameter `id` wajib. Handler PHP shortcode memanggil fungsi render terpusat yang sama (`wtb_render_table_html`).

## 8. Render Frontend & DataTables.js (Client-Side & Server-Side)

### 8.1 Dual Render Mode (Berdasarkan Jumlah Baris)
1. **Client-Side Mode ($\le 200$ baris)**:
   - PHP langsung merender seluruh HTML `<table><thead>...</thead><tbody>...</tbody></table>`.
   - Script DataTables diinisialisasi secara lokal di browser.
2. **Server-Side AJAX Mode ($> 200$ baris)**:
   - PHP hanya merender tag `<table><thead>...</thead></table>` (tanpa `<tbody>` masif).
   - DataTables meminta data baris via AJAX ke `GET /wtb/v1/tables/{id}/data?start=0&length=10&search[value]=...`.
   - Menghemat payload HTML halaman hingga 90%+ dan mempercepat load waktu halaman.

### 8.2 Responsive Mode (Mobile)
Tersedia 2 mode yang dapat dipilih admin di setting tabel:
- **Collapsible Child Rows**: Menggunakan DataTables Responsive extension (kolom yang meluap disembunyikan dan dapat dibuka via tombol `+`).
- **Horizontal Scroll**: Wrapper `<div class="wtb-table-responsive">` dengan CSS `overflow-x: auto`.

## 9. Keamanan (Security Checklist)

| Area | Implementasi |
|---|---|
| CSRF | REST nonce (`X-WP-Nonce`) di semua request pengubah data |
| Authorization | `permission_callback` cek `current_user_can('manage_options')` untuk semua endpoint tulis |
| XSS (Plain Text) | `sanitize_text_field()` untuk label kolom dan cell plain text |
| XSS (Rich Text/HTML) | **`wp_kses_post()`** untuk isi cell Rich Text (memperbolehkan `<a>`, `<img>`, `<span>`, `<button>`, stripping `<script>` & `onload`) |
| XSS (output) | `esc_html()`, `esc_url()`, `esc_attr()` saat merender elemen di frontend |
| SQL Injection | Semua query custom menggunakan `$wpdb->prepare()` |
| Styling injection | Validate hex (`sanitize_hex_color()`) & angka (`absint()`) |
| Direct file access | Setiap file PHP diawali `if (!defined('ABSPATH')) exit;` |

## 10. Struktur Folder Plugin

```
wp-table-builder/
├── wp-table-builder.php        ← file utama + header plugin + activation hook
├── includes/
│   ├── class-activator.php     ← dbDelta() setup tabel custom (wtb_columns, wtb_rows)
│   ├── class-post-type.php     ← register CPT wtb_table
│   ├── class-rest-controller.php ← REST controller (Batch save & Server-side AJAX)
│   ├── class-render.php        ← fungsi wtb_render_table_html (dipakai Block, Elementor, Shortcode)
│   ├── class-shortcode.php     ← handler shortcode [wtb_table]
│   ├── class-block.php         ← register_block_type (Gutenberg)
│   └── class-elementor-widget.php ← WTB_Elementor_Table_Widget (Elementor integration)
├── src/                         ← source React (di-build wp-scripts)
│   ├── builder/                 ← admin builder app
│   │   ├── index.js
│   │   └── components/
│   └── block/                   ← Gutenberg block
│       ├── index.js
│       ├── edit.js
│       └── block.json
├── build/                       ← hasil compile JS/CSS
├── assets/
│   └── css/
│       └── frontend.css
└── readme.txt
```

## 11. Rencana Testing
- Unit test PHP untuk fungsi sanitasi (`wp_kses_post`), REST batch controller, dan query server-side processing (`WP_UnitTestCase`).
- Integration test Elementor Widget: Memastikan widget muncul di panel Elementor dan live preview merender tabel dengan benar.
- Manual test skenario: Tabel 1.000+ baris (Server-side AJAX pagination test), input cell mengandung tag HTML aman vs tag `<script>` berbahaya (pastikan ter-strip).
- Cross-device test untuk DataTables responsive di mobile viewport.