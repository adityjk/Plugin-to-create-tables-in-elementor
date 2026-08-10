# 📊 WP Table Builder

> Plugin WordPress & Elementor Widget visual untuk membuat, mengelola, dan mendesain tabel data yang interaktif, responsif, dan kaya fitur tanpa perlu coding.

---

## 🌟 Fitur Utama

- 🎨 **Visual Builder** — Tambah, edit, hapus kolom dan baris data dengan cepat melalui dashboard admin WordPress.
- 🧩 **Integrasi Elementor Native** — Widget khusus Elementor dengan *Live Preview* real-time dan kontrol styling lengkap (Warna Header, Stripe, Tipografi, Margin, Alignment, dan Pagination Controls).
- 🏷️ **9 Tipe Data Cell** — Teks Biasa, Angka, Tanggal (Native Date Picker), Rich Text / HTML, Link / URL, Tombol, Gambar, Badge / Label, dan Rating Bintang.
- 🔍 **Pencarian & Pengurutan Interaktif** — Pengunjung dapat melakukan pencarian live (*search box*) dan mengurutkan kolom (*sorting*) secara langsung di frontend via DataTables.js.
- 📑 **Pagination Fleksibel** — Mendukung mode Nomor Halaman (*Numbers*) dan *Indicator Dots*, dilengkapi kustomisasi teks dan icon untuk tombol Sebelum (*Prev*) / Sesudah (*Next*).
- 📱 **Desain Responsif Mobile** — Pilihan mode tampilan mobile: *Horizontal Scroll* atau *Collapsible Child Rows*.
- ⚡ **Performa Tinggi (Server-Side AJAX)** — Otomatis beralih ke mode Server-Side pagination via REST API untuk tabel besar yang memiliki lebih dari 200 baris data atau untuk sumber data WP Posts.
- 🧱 **Gutenberg Block & Shortcode** — Tampilkan tabel di mana saja menggunakan Block Gutenberg native atau Shortcode `[wtb_table id="X"]`.
- 📋 **Fitur Duplikat Tabel** — Salin tabel beserta struktur kolom dan seluruh baris datanya dalam 1-klik.
- 📥 **Export & Import CSV** — Ekspor tabel ke file CSV berformat UTF-8 BOM, dan impor file CSV untuk memasukkan data secara instan.
- 🔗 **Integrasi WP Posts** — Mengambil data otomatis dari WordPress Posts/Custom Post Types dan memetakannya ke kolom secara dinamis (Thumbnail, Judul, Kategori, Tag).
- 🕵️ **Advanced Column Filters** — Sediakan filter Dropdown Select (kategori) atau pencarian teks (Text Input) untuk masing-masing kolom dengan integrasi AJAX Server-Side.
- 🤖 **Anti-Spam Form (Honeypot)** — Validasi bot dan spam via Honeypot tersembunyi pada form pengisian frontend pengunjung.

---

## 🛠️ Cara Instalasi (Dari GitHub)

Karena plugin ini bersifat Open Source dan berada di GitHub, Anda bisa menginstalnya dengan cara berikut:

> **⚠️ PENTING:** Jangan gunakan tombol hijau **"Code -> Download ZIP"** bawaan GitHub. File zip tersebut adalah *source code* mentah dan akan memunculkan error *"No valid plugins were found"* saat diunggah ke WordPress.

1. Kunjungi halaman repositori GitHub plugin ini.
2. Di sebelah kanan, cari bagian **Releases** dan klik versi rilis terbaru.
3. Di bagian paling bawah halaman rilis tersebut, terdapat bagian bernama **Assets**. Unduh file `wp-table-builder-X.X.X.zip` dari sana. **Ini adalah file instalasi yang benar.**
4. Masuk ke Dashboard WordPress Anda, lalu navigasi ke **Plugins > Add New Plugin** (Tambah Baru).
5. Klik tombol **Upload Plugin** (Unggah Plugin) di bagian atas halaman.
6. Pilih file `.zip` yang baru saja Anda unduh dari *Releases*, lalu klik **Install Now** (Instal Sekarang).
7. Setelah instalasi selesai, klik **Activate Plugin** (Aktifkan Plugin).
8. Selesai! Menu **Table Builder** sekarang akan muncul di sidebar kiri admin WordPress Anda.

### 🛠️ Cara Build File ZIP Secara Manual (Khusus Developer)

Jika Anda melakukan *clone* repositori ini (men-download source code) untuk memodifikasi kode secara mandiri, Anda tidak bisa langsung mengunggah foldernya ke WordPress. Anda harus mem-*build* file ZIP yang bersih (tanpa file *development* seperti Docker, Git, dll) menggunakan script bawaan.

**Prasyarat**: Komputer Anda harus memiliki **Python 3**.

1. Clone repositori ini ke komputer lokal Anda:
   ```bash
   git clone https://github.com/USERNAME/table-plugin.git
   cd table-plugin
   ```
2. Modifikasi kode sesuai kebutuhan Anda.
3. Jika sudah selesai, jalankan script *packager* di terminal:
   ```bash
   ./package.sh
   # atau bisa juga menjalankan: python3 package.py
   ```
4. Script akan membuat folder baru bernama `dist/`. Di dalamnya akan terdapat file instalasi `.zip` (contoh: `wp-table-builder-1.1.0.zip`).
5. File ZIP dari folder `dist/` inilah yang valid dan siap diunggah ke Dashboard WordPress.

---

## 📖 Panduan Cara Penggunaan

### 1. Membuat & Mengisi Data Tabel (Admin Dashboard)

1. Buka menu **Table Builder** di sidebar Admin WordPress.
2. Klik tombol **`+ Buat Tabel Baru`** dan masukkan nama tabel Anda.
3. Di halaman **Editor Kolom & Baris**:
   - **Tambah Kolom**: Klik tombol `+ Kolom` di ujung kanan header tabel. Berikan nama kolom dan pilih **Tipe Data** (misal: *Rating Bintang*, *Gambar*, *Tombol*, dll).
   - **Tambah Baris Data**: Klik `+ Tambah Baris Baru` dan isi nilai pada setiap sel sesuai tipe datanya.
   - **Pengaturan Behavior**: Di panel sebelah kanan (*Pengaturan Tabel*), Anda bisa mengaktifkan *Search Box*, *Sort Kolom*, *Mode Responsif*, atau mengatur threshold *Server-Side*.
4. Klik **`Simpan Tabel`**.

---

### 2. Menampilkan & Styling Tabel di Elementor

1. Buka halaman/post dengan **Edit with Elementor**.
2. Cari widget **WP Table Builder** di panel Elementor lalu drag & drop ke halaman.
3. Di tab **Content**:
   - **Pilih Tabel**: Pilih tabel yang sudah Anda buat dari dropdown.
   - **Element & Kontrol Tabel**: Atur opsi untuk menampilkan/menyembunyikan *Search Box*, *Jumlah Baris*, *Info Status Data*, *Pagination*, *Tombol Prev/Next*, *Nomor Halaman*, serta pilih tipe pagination (*Nomor Halaman* / *Indicator Dots*).
4. Di tab **Style**:
   - **Dimensi & Layout**: Atur *Width*, *Max Width*, *Height*, *Max Height*, dan *Alignment* (Left / Center / Right).
   - **Border & Shadow**: Gunakan kontrol standar Elementor untuk memberikan Border, Border Radius, dan Box Shadow pada tabel.
   - **Warna Header & Baris**: Sesuaikan warna background header, warna teks header, warna baris selang-seling (*stripe*), serta warna hover baris.
   - **Tipografi**: Atur font, ukuran teks, dan ketebalan font untuk header maupun sel tabel.
   - **Navigasi & Pagination Style**: Ubah teks tombol *Prev/Next*, tambahkan Icon (FontAwesome / SVG custom), atur ukuran icon, spasi dots, warna state *Normal*, *Hover*, dan *Active* untuk tombol & nomor halaman.

---

### 3. Menampilkan via Gutenberg Block atau Shortcode

#### A. Gutenberg Block
1. Buka Editor Halaman / Post biasa WordPress.
2. Cari dan tambahkan block **WP Table Builder**.
3. Pilih tabel dari dropdown inspector block.

#### B. Shortcode
Salin shortcode yang tersedia di halaman admin dan tempelkan di mana saja:
```html
[wtb_table id="12"]
```

---

## 🏷️ Tipe Data Cell yang Didukung

| Tipe Data | Deskripsi | Tampilan di Editor / Frontend |
|---|---|---|
| **Teks Biasa** | Teks standar tanpa format | Textarea 1 baris / Teks biasa |
| **Angka** | Nilai numerik | Input type `number` |
| **Tanggal** | Format tanggal | Date Picker native browser (`YYYY-MM-DD`) |
| **Rich Text / HTML** | Teks kaya dengan tag HTML yang aman | Textarea / HTML terformat (`wp_kses_post`) |
| **Link / URL** | Tautan hyperlink | Input URL → Meng-generate tag `<a>` otomatis |
| **Tombol** | Tombol aksi (Call to Action) | Input label → Elemen tombol kustom |
| **Gambar** | URL gambar yang ditampilkan sebagai Thumbnail | Input URL → Image thumbnail responsif |
| **Badge / Label** | Badge status atau kategori | Pill badge berwarna lembut |
| **Rating Bintang** | Nilai rating 1 sampai 5 | Input 1–5 → Tampilan bintang ★★★☆☆ |

---

## 💻 Pengembangan & Instalasi Lokal (Docker)

Repositori ini sudah dilengkapi dengan konfigurasi **Docker Compose** untuk kemudahan pengembangan lokal.

### Prasyarat
- [Docker](https://www.docker.com/) & Docker Compose terinstal di komputer Anda.

### Langkah Menjalankan Environment Lokal

1. Clone repositori ini:
   ```bash
   git clone https://github.com/USERNAME/table-plugin.git
   cd table-plugin
   ```

2. Jalankan container Docker:
   ```bash
   docker compose up -d
   ```

3. Akses layanan lokal melalui browser:
   - **WordPress**: `http://localhost:8080`
   - **phpMyAdmin**: `http://localhost:8081`

4. Login awal WordPress:
   - **Username**: `wordpress`
   - **Password**: `wordpress`
   - *(Atau lakukan setup wizard WordPress jika baru pertama kali dipasang)*

---

## 🔒 Keamanan & Performa

- **Sanitasi Data Ketat**: Menggunakan `wp_kses_post()` untuk Rich Text/HTML, `sanitize_text_field()` untuk teks biasa, `esc_url_raw()` untuk URL, dan `sanitize_hex_color()` untuk kode warna.
- **Prepared Statements**: Semua query database MySQL berjalan dengan `$wpdb->prepare()` untuk mencegah SQL Injection.
- **Nonce & Capability Check**: Semua REST API endpoint dan aksi admin dilindungi autentikasi role `manage_options` dan token Nonce.
- **DataTables AJAX Pagination**: Pengolahan data tabel besar secara server-side melalui REST API untuk menjaga kecepatan loading browser pengunjung, juga teroptimasi untuk query `WP_Posts` dalam skala besar.
- **Anti-Spam Validasi**: Melindungi pengisian formulir dengan Honeypot Field (`wtb_website_url`) tersembunyi yang divalidasi langsung pada layer API backend.

---

## 📄 Lisensi

Plugin ini dirilis di bawah lisensi **[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)**.
