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
- 🔄 **Update Otomatis** — Versi baru otomatis muncul di halaman **Plugins > Updates** dashboard WordPress, lengkap dengan tombol *Update now* dan changelog — tanpa perlu hapus & install ulang ZIP manual.

---

## 🛠️ Cara Instalasi (Dari GitHub)

Karena plugin ini bersifat Open Source dan berada di GitHub, Anda bisa menginstalnya dengan cara berikut:

> **⚠️ PENTING:** Jangan gunakan tombol hijau **"Code -> Download ZIP"** bawaan GitHub. File zip tersebut adalah *source code* mentah dan akan memunculkan error *"No valid plugins were found"* saat diunggah ke WordPress.

1. Kunjungi halaman repositori GitHub plugin ini.
2. Di sebelah kanan, cari bagian **Releases** dan klik versi rilis terbaru.
3. Di bagian paling bawah halaman rilis tersebut, terdapat bagian bernama **Assets**. Unduh file `wtb-table-builder-X.X.X.zip` dari sana. **Ini adalah file instalasi yang benar.**
4. Masuk ke Dashboard WordPress Anda, lalu navigasi ke **Plugins > Add New Plugin** (Tambah Baru).
5. Klik tombol **Upload Plugin** (Unggah Plugin) di bagian atas halaman.
6. Pilih file `.zip` yang baru saja Anda unduh dari *Releases*, lalu klik **Install Now** (Instal Sekarang).
7. Setelah instalasi selesai, klik **Activate Plugin** (Aktifkan Plugin).
8. Selesai! Menu **Table Builder** sekarang akan muncul di sidebar kiri admin WordPress Anda.

### 🔄 Cara Update Plugin (Otomatis)

Plugin ini dilengkapi **self-hosted updater** yang terhubung ke GitHub Releases. Artinya, untuk versi selanjutnya Anda **tidak perlu** lagi menghapus dan mengunggah ZIP secara manual:

1. Masuk ke Dashboard WordPress Anda.
2. Buka menu **Dashboard > Updates** atau halaman **Plugins**.
3. Jika ada versi baru di GitHub Releases, plugin akan menampilkan notifikasi *"New version available"* beserta tombol **Update now** (pemeriksaan berjalan otomatis setiap ±12 jam, atau klik tombol *Check for updates*).
4. Klik **Update now** — selesai. Data tabel Anda tidak akan hilang.

> ℹ️ Notifikasi update hanya muncul jika versi baru telah dirilis melalui halaman **Releases** GitHub dengan asset file ZIP (lihat panduan rilis di bawah).

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
4. Script akan membuat folder baru bernama `dist/`. Di dalamnya akan terdapat file instalasi `.zip` (contoh: `wtb-table-builder-1.1.0.zip`).
5. File ZIP dari folder `dist/` inilah yang valid dan siap diunggah ke Dashboard WordPress.
   > 💡 Versi pada nama file ZIP kini **otomatis** mengikuti header `Version:` di `wtb-table-builder.php` — cukup ubah versi di satu tempat saja.

### 🚀 Cara Rilis Versi Baru (Agar Update Muncul Otomatis di WP Admin)

Supaya pengguna menerima notifikasi update langsung dari halaman Plugins, ikuti alur rilis berikut setiap kali ada versi baru:

1. Naikkan versi pada header `Version:` di `wp-content/plugins/wtb-table-builder/wtb-table-builder.php` (dan konstanta `WTB_VERSION`).
2. Build ZIP: `./package.sh`.
3. Commit, lalu buat tag yang **harus sama persis dengan nomor versi** (boleh dengan awalan `v`, contoh: `v1.2.0`):
   ```bash
   git tag v1.2.0
   git push origin v1.2.0
   ```
4. Buka GitHub → tab **Releases** → **Draft a new release**, pilih tag tersebut.
5. Isi judul dan deskripsi changelog (teks ini akan tampil di popup *View details* WordPress).
6. Pada bagian **Assets**, unggah file ZIP hasil build (contoh: `dist/wtb-table-builder-1.2.0.zip`) — **file inilah yang akan diunduh oleh updater**.
7. Klik **Publish release**.

> ⚠️ Catatan penting:
> - Jangan gunakan asset otomatis *"Source code (zip)"* milik GitHub — struktur foldernya tidak sesuai sehingga update akan gagal.
> - Nomor versi pada tag harus lebih tinggi dari versi yang terpasang agar WordPress mendeteksinya sebagai update.

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

## 🎨 Panduan Custom CSS & Class Names

Plugin **WP Table Builder** dirancang menggunakan class-class CSS terstruktur (berawalan `wtb-`) yang bersih, sehingga sangat mudah disesuaikan (*customized*) menggunakan CSS tambahan.

### 📍 Tempat Menambahkan Custom CSS
1. **Elementor**: Klik Widget WP Table Builder > Buka tab **Advanced** > Ketik CSS pada panel **Custom CSS**.
2. **WordPress Customizer**: Di Admin WordPress, buka **Tampilan (Appearance) > Sesuaikan (Customize) > CSS Tambahan (Additional CSS)**.
3. **Child Theme**: Tambahkan kode CSS pada file `style.css` milik tema/child theme Anda.

---

### 📋 Daftar Class Name & Selector CSS

#### 1. Container & Struktur Tabel
| Selector / Class Name | Deskripsi Elemen |
|---|---|
| `.wtb-table-wrap` | Container pembungkus utama seluruh elemen tabel & kontrol navigasi |
| `#wtb-wrap-X` / `.wtb-wrap-X` | Selector khusus per tabel berdasarkan ID (ganti `X` dengan ID tabel, misal: `#wtb-wrap-12`) |
| `.wtb-table-scroll` | Pembungkus scrollbar responsif |
| `.wtb-table` | Elemen utama `<table>` |
| `#wtb-table-X` | Selector elemen `<table>` spesifik berdasarkan ID tabel (misal: `#wtb-table-12`) |
| `.wtb-table thead tr th` | Sel Header tabel (`<th>`) |
| `.wtb-table tbody tr td` | Sel Isi tabel (`<td>`) |
| `.wtb-table tbody tr:hover td` | Efek hover pada baris tabel saat kursor diarahkan |
| `.wtb-table tbody tr:nth-child(even)` | Baris tabel selang-seling (stripe background) |
| `.wtb-th-inner` | Wrapper konten judul & icon filter di dalam header |
| `.wtb-th-label-text` | Teks judul header kolom |

#### 2. Tipe Data Sel (Cell Types)
| Selector / Class Name | Deskripsi Elemen |
|---|---|
| `.wtb-cell-img` | Elemen thumbnail gambar (`<img>`) |
| `.wtb-cell-btn` | Elemen tombol Call-to-Action (`<span>` / `<a>`) |
| `.wtb-cell-badge` | Elemen badge / label status (`<span>`) |
| `.wtb-cell-rating` | Penampung teks bintang rating (`<span>`) |
| `.wtb-cell-file-wrap` | Wrapper tombol unduh & preview file |
| `.wtb-cell-file` | Tombol/link unduh file utama |
| `.wtb-file-icon` | Icon SVG file |
| `.wtb-btn-file-preview` | Tombol trigger modal preview file |

#### 3. Search Box, Dropdown & Pagination (DataTables)
| Selector / Class Name | Deskripsi Elemen |
|---|---|
| `.wtb-table-wrap .dataTables_wrapper` | Wrapper utama seluruh kontrol navigasi & pencarian |
| `.wtb-table-wrap .dataTables_filter` | Wrapper area pencarian (*Search Box*) |
| `.wtb-table-wrap .dataTables_filter input[type="search"]` | Field input teks pencarian |
| `.wtb-table-wrap .dataTables_length` | Wrapper penentu jumlah baris per halaman |
| `.wtb-table-wrap .dataTables_length select` | Dropdown jumlah baris |
| `.wtb-table-wrap .dataTables_info` | Teks status data (*Showing 1 to 10 of N entries*) |
| `.wtb-table-wrap .dataTables_paginate` | Container tombol navigasi pagination |
| `.wtb-table-wrap .dataTables_paginate .paginate_button` | Tombol angka halaman / Prev / Next |
| `.wtb-table-wrap .dataTables_paginate .paginate_button.current` | Tombol halaman yang sedang aktif |
| `.wtb-table-wrap.wtb-dots-mode .paginate_button` | Indicator titik-titik (*Dots Mode Pagination*) |

#### 4. Filter Kolom Header
| Selector / Class Name | Deskripsi Elemen |
|---|---|
| `.wtb-header-tax-wrap` | Wrapper icon filter di header kolom |
| `.wtb-header-tax-select` | Dropdown `<select>` filter kategori di header |
| `.wtb-header-text-filter-wrap` | Wrapper field pencarian teks per kolom |
| `.wtb-filter-text` | Field input teks pencarian per kolom |

#### 5. Form Pengunjung Frontend (User Form Submission)
| Selector / Class Name | Deskripsi Elemen |
|---|---|
| `.wtb-form-container` / `#wtb-form-container-X` | Container utama formulir penambahan data |
| `.wtb-form-title` & `.wtb-form-subtitle` | Judul dan petunjuk pengisian form |
| `.wtb-form-grid` & `.wtb-form-field-group` | Grid layout dan grup input field |
| `.wtb-form-label` | Teks label input |
| `.wtb-form-input` & `.wtb-form-textarea` | Field input teks / angka / tanggal / textarea |
| `.wtb-form-btn-submit` | Tombol submit kirim data |
| `.wtb-form-response-msg` | Alert box respon setelah form dikirim |
| `.wtb-msg-success` & `.wtb-msg-error` | Alert sukses (hijau) & alert error (merah) |

---

### 💡 Contoh Kode CSS Praktis

#### A. Mengubah Warna Header & Hover Baris
```css
/* Mengubah background header tabel */
.wtb-table thead tr th {
    background-color: #0f172a !important;
    color: #ffffff !important;
    font-weight: 700;
}

/* Mengubah warna hover baris */
.wtb-table tbody tr:hover td {
    background-color: #e2e8f0 !important;
}
```

#### B. Kustomisasi Tombol CTA (`.wtb-cell-btn`) & Badge (`.wtb-cell-badge`)
```css
/* Custom Tombol Call to Action */
.wtb-cell-btn {
    background-color: #10b981 !important; /* Hijau Emerald */
    color: #ffffff !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3) !important;
}

.wtb-cell-btn:hover {
    background-color: #059669 !important;
    transform: translateY(-2px);
}

/* Custom Badge Status */
.wtb-cell-badge {
    background-color: #fef3c7 !important;
    color: #92400e !important;
    border: 1px solid #fde68a !important;
}
```

#### C. Kustomisasi Field Search & Tombol Pagination
```css
/* Mengubah bentuk input search menjadi rounded pill */
.wtb-table-wrap .dataTables_filter input[type="search"] {
    border-radius: 20px !important;
    padding: 8px 16px !important;
    border: 2px solid #cbd5e1 !important;
}

/* Mengubah warna tombol pagination yang aktif */
.wtb-table-wrap .dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #06b6d4 !important; /* Cyan */
    border-color: #06b6d4 !important;
    color: #ffffff !important;
}
```

#### D. Mentarget Tabel Spesifik Saja (Berdasarkan ID Tabel)
Jika Anda hanya ingin mengubah **Tabel ID 12** tanpa mempengaruhi tabel lain:
```css
/* Hanya berlaku untuk tabel ID 12 */
#wtb-wrap-12 .wtb-table thead tr th {
    background-color: #8b5cf6 !important; /* Purple */
}

#wtb-wrap-12 .wtb-cell-btn {
    background-color: #ec4899 !important; /* Pink */
}
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
