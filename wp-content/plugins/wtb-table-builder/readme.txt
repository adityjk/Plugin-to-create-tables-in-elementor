=== WP Table Builder ===
Contributors: AdityJK
Tags: table, tables, elementor, data tables, csv, gutenberg
Requires at least: 5.6
Tested up to: 6.4
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress & Elementor Widget visual untuk membuat, mengelola, dan mendesain tabel data yang interaktif, responsif, dan kaya fitur tanpa perlu coding.

== Description ==

WP Table Builder adalah plugin WordPress canggih yang dirancang untuk memudahkan Anda membuat tabel data interaktif dengan antarmuka Visual Builder yang intuitif. Plugin ini terintegrasi penuh dengan ekosistem Elementor dan Gutenberg.

Tabel yang dihasilkan sepenuhnya responsif, modern, dan didukung oleh fungsionalitas DataTables.js (fitur pencarian, pengurutan kolom, dan pagination halaman).

= Fitur Utama =
* **Visual Builder**: Tambah, edit, hapus kolom dan baris data dengan cepat melalui dashboard admin WordPress.
* **Integrasi Elementor**: Widget khusus Elementor dengan *Live Preview* real-time dan kontrol styling lengkap (Warna, Tipografi, Margin, Pagination Controls).
* **Advanced Column Filters**: Filter spesifik per-kolom (Dropdown kategori atau Pencarian teks).
* **Integrasi WP Posts**: Narik data otomatis dari WordPress Posts/Custom Post Types secara dinamis.
* **Export & Import CSV**: Manipulasi dan backup data secara masal dengan format CSV UTF-8.
* **Server-Side AJAX Processing**: Otomatis menangani tabel besar (ribuan baris) tanpa membuat web melambat/crash.
* **9 Tipe Data Sel**: Teks Biasa, Angka, Tanggal, Rich Text / HTML, Link, Tombol, Gambar, Badge, dan Rating Bintang.
* **Anti-Spam Form (Honeypot)**: Melindungi formulir frontend dari serangan spam tanpa membebani pengguna dengan Captcha.

== Installation ==

1. Unggah folder `wp-table-builder` ke direktori `/wp-content/plugins/` melalui FTP, atau unggah file `.zip` via halaman **Plugins > Add New** di dashboard WordPress.
2. Aktifkan plugin melalui menu **Plugins** di WordPress.
3. Buka menu **Table Builder** yang baru muncul di sidebar untuk membuat tabel perdana Anda.
4. Gunakan Elementor Widget `WP Table Builder` atau shortcode `[wtb_table id="X"]` untuk menampilkan tabel di halaman web.

== Frequently Asked Questions ==

= Apakah tabel ini mobile-responsive? =
Ya. Di pengaturan tabel, Anda dapat memilih mode reponsif: *Horizontal Scroll* atau *Collapsible Child Rows* (menyembunyikan kolom di dalam tombol expand/+).

= Apakah saya bisa memasukkan form ke dalam halaman untuk diisi pengunjung? =
Ya! Jika fitur Formulir diaktifkan, pengunjung bisa mengirimkan entri langsung dari halaman depan, dan data bisa diatur untuk membutuhkan *Approval* (Moderasi) sebelum tayang di tabel.

= Kenapa CSV hasil export saya saat dibuka di Excel berantakan (garbled)? =
Tidak perlu khawatir, fitur Export kami menggunakan penanda *UTF-8 BOM* (Byte Order Mark), sehingga file CSV Anda akan terbaca rapi oleh Microsoft Excel secara otomatis.

== Changelog ==

= 1.1.0 =
* Fitur Baru: Dukungan penuh Server-Side Processing (AJAX) untuk manual tabel skala besar.
* Fitur Baru: Integrasi Data Source langsung dari WordPress Posts.
* Fitur Baru: Export & Import data via file CSV.
* Fitur Baru: Advanced Column Filters (Dropdown Select & Text Search).
* Fitur Baru: Keamanan form submission dilengkapi validasi Anti-Spam Honeypot.

= 1.0.0 =
* Rilis Awal. Visual Builder dengan 9 opsi tipe data kolom.
