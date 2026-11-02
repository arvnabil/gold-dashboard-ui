# Product Requirements Document
## XAUUSD AI Hybrid Analyst

**Versi:** 1.0  
**Tanggal:** 2 November 2026  
**Owner produk:** Nabil  
**Target:** aplikasi analisis privat di Ubuntu home server  
**Baseline:** Laravel + React/Inertia + shadcn/ui + Python + MT5  
**Batas produk:** analisis, alert, replay, dan paper trading. Tidak mengirim order.

## 1. Ringkasan dan keputusan utama

Produk membantu pengguna membaca XAUUSD pada M5, M15, H1, H4, dan D1 melalui chart, zona SMC/SNR/SND yang dapat diaudit, skenario harian, serta alert perubahan setup. M1 dikoleksi sebagai data pendukung replay, bukan timeframe utama antarmuka.

**Hybrid** berarti kombinasi analisis terjadwal dan pemantauan berbasis event. Bukan memanggil LLM setiap tick. Algoritma menghitung level dan status. AI menjelaskan bukti yang diberikan. Pengguna tetap mengambil keputusan sendiri.

| Keputusan | Baseline implementasi |
| --- | --- |
| Aplikasi web | Modular monolith Laravel, bukan microservices penuh |
| Frontend | React + TypeScript + Inertia + Tailwind + shadcn/ui; seluruh halaman custom |
| Tidak digunakan | Filament, Next.js, TradingView iframe sebagai chart utama |
| Mesin teknikal | Paket Python deterministik, diekspos melalui FastAPI internal |
| Sumber harga | Satu feed broker MT5 yang dipilih pengguna |
| Chart | Lightweight Charts dengan primitive zona custom |
| Persistensi | PostgreSQL sebagai sumber kebenaran; Redis untuk queue, lock, cache |
| Realtime | Laravel Reverb + Echo; HTTP polling sebagai fallback |
| AI | Satu analyst berbasis API; tanpa akses broker, shell, atau SQL |
| Akses | Tailscale + HTTPS aplikasi + login; OpenSSH privat untuk development |
| Eksekusi | Dilarang: order_send, auto-entry, copy trading, martingale, grid |

Dokumentasi resmi mendukung baseline Laravel 13, starter kit React/Inertia/shadcn, queue, scheduler, dan Reverb. Versi patch harus dikunci setelah compatibility test, bukan memakai tag `latest` saat deployment. [S1][S2][S3][S4][S5]

## 2. Masalah, tujuan, dan ukuran keberhasilan

### 2.1 Masalah yang diselesaikan

Pengguna harus berpindah timeframe, menggambar zona berulang, dan menilai apakah sinyal masih berlaku. Analisis manual sulit direkonstruksi. Narasi AI tanpa data terstruktur dapat membuat level yang tidak berasal dari chart. Data terputus juga dapat terlihat seolah masih live.

### 2.2 Tujuan produk

1. Menampilkan satu pandangan konsisten atas harga, struktur, zona, dan status data dari broker yang sama.
2. Menghasilkan setup berbasis aturan eksplisit, dengan asal data, versi strategi, waktu tersedia, dan alasan invalidasi.
3. Menghasilkan brief AI yang berguna tanpa menjadikannya otoritas harga atau eksekusi.
4. Memungkinkan replay dan pengujian sebelum pengguna mengandalkan alert.
5. Menjaga biaya dan beban home server dengan satu aplikasi web, satu engine, dan worker terbatas.

### 2.3 Target penerimaan, bukan klaim performa

| Metrik | Target awal | Cara mengukur |
| --- | --- | --- |
| Duplikasi candle/logical event | 0 setelah retry/concurrent request | Unique constraints + integration tests |
| Event memakai future information | 0 dalam fixture dan prefix replay tests | Filter available_at + replay |
| Ingest closed candle sampai hasil teknikal | p95 <= 5 detik setelah batch diterima | Trace ID, tanpa menghitung delay broker |
| Quote diterima sampai tampil | p95 <= 2 detik pada jaringan sehat | Timestamp server + browser |
| Overview API | p95 <= 500 ms untuk dataset benchmark | Load test 5 pengguna, 10 req/detik |
| Chart pertama | <= 3 detik untuk 1.000 candle/100 zona | Browser desktop benchmark |
| Alert terbit sampai satu percobaan pengiriman | p95 <= 10 detik | Outbox + delivery log |
| Kegagalan AI | Tidak menghentikan chart/engine/alert teknikal | Fault injection |
| Restore | RPO <= 24 jam; RTO <= 2 jam | Restore drill pada host uji |

Angka di atas adalah target engineering. Ukur ulang pada hardware nyata sebelum menyebutnya tercapai. Profit, win rate, dan akurasi prediksi bukan acceptance criterion produk v1.

## 3. Pengguna, asumsi, dan ruang lingkup

### 3.1 Persona dan izin

MVP memiliki satu owner. Login publik/registrasi mandiri dimatikan. Owner melihat data dan mengubah konfigurasi. Role viewer disiapkan dalam policy, tetapi UI multi-user bukan prioritas. Collector memakai kredensial mesin dengan scope ingest saja. Engine memakai token internal yang berbeda.

### 3.2 Asumsi yang harus diverifikasi di Phase 0

| Asumsi | Default kerja | Verifikasi sebelum live |
| --- | --- | --- |
| Hardware | Ubuntu dengan perkiraan 4 core, RAM 8 GB, SSD; bukan minimum resmi | Catat resource, ruang disk, throughput aktual |
| Windows MT5 | Mesin Windows atau Windows VM yang terus aktif | Uji login, reboot, reconnect, akses history |
| Broker/simbol | Satu broker, satu akun data, simbol aktual dapat bersufiks | Pilih feed, tick size, digits, price basis, sesi |
| Zona waktu | Storage UTC; UI Asia/Jakarta | Cocokkan timestamp terhadap terminal broker |
| History | Ketersediaan mengikuti terminal/broker | Backfill dan laporkan coverage aktual |
| Penggunaan | Privat/personal, bukan redistribusi harga | Tinjau ketentuan feed sebelum publikasi |
| AI/news | API AI opsional; news feed belum tersedia | UI menampilkan `NEWS_NOT_CONNECTED` |

Windows laptop boleh menjadi collector saat development. Untuk monitoring terus-menerus, laptop tidur atau mati harus dianggap downtime, bukan kondisi yang dapat ditutupi AI. Windows VM di home server hanya dipilih setelah resource, lisensi, dan operasi terminal diuji.

### 3.3 Scope v1 dan penundaan

**Wajib v1:** autentikasi; ingest feed; chart multi-timeframe; SNR, SND, BOS, CHoCH, sweep, FVG, OB; lifecycle zona; deterministic setup; brief AI; alert Telegram/in-app; konfigurasi versi; audit; deterministic replay; paper simulation dasar; health dan restore.

**Setelah v1:** annotation manual terpisah dari zona mesin; economic calendar berlisensi; multi-pair; multi-broker; aplikasi mobile; optimasi strategi; worker terdistribusi.

**Tidak termasuk:** rekomendasi ukuran posisi personal, auto-order, klaim profit, deteksi aktivitas institusi yang terverifikasi, scraping harga TradingView, training model prediksi, dan dashboard admin Filament.

## 4. User journey dan halaman

### 4.1 Alur utama

1. Owner login melalui alamat privat. Sistem menunjukkan feed dan status koneksi sebelum chart.
2. Owner memilih timeframe dan layer. Zona H1/H4 boleh ditampilkan di chart M5, tetapi label timeframe asal tidak berubah.
3. Owner membuka zona untuk melihat batas, waktu asal, waktu konfirmasi, lifecycle, bukti, dan strategi.
4. Owner membaca setup: `WATCHING`, `ARMED`, `CONFIRMED`, atau `READY_FOR_REVIEW`. Tidak ada tombol Buy/Sell broker.
5. Brief AI menjelaskan skenario menggunakan referensi setup/zone yang sama. Level ditampilkan dari backend, bukan dari teks AI.
6. Owner menerima alert satu kali untuk satu transisi logis, lalu dapat membuka snapshot saat alert dibuat.
7. Owner menjalankan replay/paper simulation dan melihat hasil serta keterbatasan data.

### 4.2 Struktur antarmuka custom

| Halaman | Komponen wajib | Empty/error state |
| --- | --- | --- |
| Overview | Feed badge, bid/ask, freshness, bias tiap TF, setup aktif, brief terakhir | Belum ada feed, warming up, stale |
| Trading chart | Candle, selector TF, filter layer, legenda, daftar zona, panel detail | No history, feed mismatch, realtime reconnecting |
| Analysis | Brief pagi/siang/malam, evidence links, konflik, missing context | AI disabled, budget exhausted, failed validation |
| Setups & alerts | State timeline, reason codes, dedupe status, delivery status | Tidak ada setup bukan error |
| Replay & paper | Rentang waktu, versi strategi, quality report, hasil simulasi | History kurang, ambiguous outcome |
| Settings | Feed, strategi, jadwal, Telegram, AI budget, safety switches | Validasi field, perubahan belum aktif |
| System health | Collector, lag, gap, queue, disk, backup, error audit | Dependency degraded/failed |

Gunakan komponen shadcn/ui yang dimiliki aplikasi. Inertia menangani navigasi dan form standar; React local state/context cukup untuk awal. Jangan menambah Zustand, React Hook Form, dan library form lain tanpa kebutuhan teruji. Laravel tetap menjadi otoritas validasi server. [S2][S18]

Dark mode menjadi default; light mode tetap tersedia. Status wajib memakai label/ikon selain warna. Pada mobile, panel detail menjadi sheet dan chart memakai satu timeframe aktif. Detail zona tetap tersedia sebagai daftar teks agar informasi tidak hanya berada di canvas. Lightweight Charts memungkinkan primitive custom; patuhi attribution/NOTICE library. [S10][S11]

## 5. Kebutuhan fungsional dan acceptance criteria

Semua requirement berikut adalah MUST untuk rilis v1 kecuali dinyatakan lain. ID menjadi penghubung ke TEST_PLAN dan issue implementasi.

| ID | Requirement | Acceptance criterion |
| --- | --- | --- |
| FR-01 | Autentikasi dan policy | User anonim ditolak; viewer tidak dapat mengubah settings; registrasi publik off |
| FR-02 | Feed profile | Broker/server/simbol/tick size/digits/calendar/price basis tersimpan; pergantian feed tidak mencampur data |
| FR-03 | Ingest candle dan quote | Batch tervalidasi, idempotent, punya receipt ID; quote tidak membuat candle closed palsu |
| FR-04 | Data integrity | Duplikat, gap, revisi, out-of-order, OHLC invalid terdeteksi; audit tidak ditimpa |
| FR-05 | Data health | UI dan engine membedakan LIVE, WARMING_UP, STALE, DISCONNECTED, MARKET_CLOSED, DEGRADED |
| FR-06 | Chart | Lima TF utama; historis paginated; provisional bar jelas; timestamp konsisten |
| FR-07 | Zona overlay | Koordinat harga/waktu stabil saat zoom; TF asal, type, status, evidence dapat dibuka |
| FR-08 | Struktur | Pivot/BOS/CHoCH/sweep mengikuti rule v0.1 dan waktu ketersediaan |
| FR-09 | SNR | Cluster level dibentuk dari pivot terkonfirmasi; perubahan cluster membuat revisi/supersession |
| FR-10 | SND | Base/displacement memenuhi parameter; batas dan alasan zona dapat direproduksi |
| FR-11 | FVG dan OB | FVG tiga closed bars; OB menunggu konfirmasi break; tidak aktif pada waktu asal semata |
| FR-12 | Lifecycle zona | Touch/mitigation/invalid/expiry tercatat; zona invalid tidak kembali aktif |
| FR-13 | Setup multi-timeframe | Urutan evidence valid, hard gates lulus, entry/SL/target dari engine, bukan AI |
| FR-14 | AI brief | Output lolos schema dan semantic guard; fallback tersedia; semua evidence ID valid |
| FR-15 | Hybrid orchestration | Brief terjadwal + teknikal per event; tidak memanggil AI tiap tick |
| FR-16 | Alert | In-app durable; Telegram dapat retry; logical dedupe; stale setup tidak dikirim sebagai fresh |
| FR-17 | Audit snapshot | Setiap report/alert dapat direkonstruksi dengan candle revision, strategy, prompt, model |
| FR-18 | Settings/versioning | Konfigurasi immutable setelah aktif; perubahan membuat version baru dan audit |
| FR-19 | Replay | Tidak mengakses data di masa depan; mode knowledge/reconstruction dibedakan |
| FR-20 | Paper simulation | No broker orders; next executable price; spread/slippage; ambiguous fill dilaporkan |
| FR-21 | Operasional | Health, queue, retention, backup/restore dan recovery diuji |
| FR-22 | Safety boundary | No execution endpoint; secrets tidak terkirim ke AI/frontend; kill switches bekerja |

## 6. Arsitektur teknis dan kepemilikan

### 6.1 Topologi deployment

```text
Windows laptop: VS Code + OpenSSH + browser
                | Tailscale
                v
Ubuntu home server
  Reverse proxy HTTPS -> Laravel/Inertia -> React/shadcn UI
                         | PostgreSQL
                         | Redis + Horizon workers
                         | Reverb -> browser
                         | internal HTTP -> Python FastAPI engine
                         | outbound HTTPS -> AI API / Telegram
                         | dedicated worker -> paper/replay chunks

Always-on Windows host/VM
  MT5 terminal -> Python collector -> local durable spool
                        | Tailscale + authenticated HTTPS
                        +----> Laravel ingest endpoints
```

### 6.2 Tanggung jawab layanan

| Komponen | Bertanggung jawab | Tidak boleh melakukan |
| --- | --- | --- |
| Collector | Membaca MT5, normalize, spool, retry, health | Menghitung keputusan trading atau mengirim order |
| Laravel | Auth, ingest, DB, snapshot, queue, versioning, guard, AI orchestration | Menjalankan strategi kedua yang berbeda dari engine |
| Python engine | Fungsi strategi deterministik dan replay per chunk | Mengakses broker, LLM, atau menulis langsung ke DB utama |
| AI analyst | Menjelaskan evidence terstruktur | Membuat harga/trigger baru, memilih lot, override hard gate |
| React | Visualisasi dan interaksi | Menjadi sumber kebenaran status setup |
| Alert worker | Pengiriman dan delivery audit | Menandai successful tanpa bukti provider |

Horizon mengelola queue Laravel/Redis. Python tidak membaca format job Laravel secara langsung. Laravel worker memanggil FastAPI dengan snapshot lengkap dan timeout. Untuk replay panjang, pecah menjadi chunk idempotent dengan checkpoint; jangan memakai FastAPI `BackgroundTasks` sebagai durable job queue. [S3][S6][S14]

### 6.3 Alur konsistensi

Ingest valid -> transaksi candle revision + outbox -> worker menyusun snapshot immutable -> engine mengevaluasi -> Laravel memvalidasi dan menyimpan hasil + outbox -> Reverb/in-app/Telegram.

Delivery bersifat at-least-once. Efek logis dibuat sekali melalui unique keys dan transaksi. Jangan mengklaim jaringan memberikan exactly-once delivery. Outbox yang belum terkirim disapu ulang. Redis bukan satu-satunya catatan pekerjaan yang belum selesai.

Worker live dan backtest dipisah. State per feed/TF diproses serial melalui lock dan `expected_state_version`. Hasil lama tidak boleh menimpa state baru; hasil tersebut disimpan sebagai obsolete untuk audit atau dievaluasi ulang.

## 7. Kontrak market data

### 7.1 Feed dan timeframe

Koleksi OHLC native MT5 untuk M1, M5, M15, H1, H4, D1. **Jangan membangun H4/D1 dari UTC midnight sendiri pada v1.** Batas candle mengikuti feed broker. Aggregation M1 hanya boleh ditambahkan setelah lolos parity test terhadap native feed.

MT5 menyediakan field OHLC, tick_volume, spread, real_volume dan menjelaskan bahwa index bar 0 adalah candle saat ini. Gunakan bar terkonfirmasi, umumnya mulai posisi 1, untuk deteksi. History tersedia dibatasi terminal dan pengaturan chart; coverage harus dilaporkan, bukan diasumsikan penuh. [S7][S8]

Collector berjalan di Windows untuk baseline ini. Distribusi paket MetaTrader5 yang diverifikasi menyediakan wheel Windows x86-64; engine analisis tetap di Ubuntu. [S9]

### 7.2 Polling dan freshness

| Stream | Default | Persistensi |
| --- | --- | --- |
| Quote terakhir bid/ask | Poll tiap 1 detik saat market open | Recent samples 7 hari; quote evidence disimpan dalam snapshot |
| Closed candles | Cek tiap 2 detik, fetch hanya TF yang berubah | Candle + revisi immutable |
| Heartbeat collector | Tiap 10 detik | Status terakhir + health events |
| Backfill | Startup dan setelah gap | Chunk <= 1.000 candle; retry idempotent |
| History reconciliation | Terjadwal, recent window | Revisi dengan alasan dan known_at baru |

Ini adalah sampled quote feed, bukan rekaman seluruh tick. Pergerakan di antara polling dapat terlewat. Touch yang baru diketahui dari candle closed dicatat `detected_retrospectively=true`; jangan mengirimnya sebagai sinyal realtime yang baru terjadi.

Default health: quote age > 10 detik saat sesi open menjadi STALE; heartbeat age > 30 detik menjadi DISCONNECTED; history yang belum cukup menjadi WARMING_UP. Sesi terjadwal tutup menjadi MARKET_CLOSED, bukan otomatis gangguan. Status kalender yang tidak diketahui menjadi DEGRADED dan memblokir setup baru.

Candle freshness dihitung dari expected broker close/session calendar dan observed close, bukan aturan age generik yang membuat D1 selalu stale. Recovery memerlukan dua heartbeat sehat, quote fresh, dan tidak ada gap unresolved pada window yang dibutuhkan strategi.

### 7.3 Timestamp dan revisi

| Field | Arti |
| --- | --- |
| `opened_at` | Awal interval candle dari feed |
| `closed_at` | Akhir interval yang ditetapkan kalender feed, bukan waktu diterima |
| `source_time` | Waktu quote/event dari provider |
| `received_at` / `known_at` | Waktu backend pertama memiliki record/revisi |
| `formed_at` | Waktu asal pola, bisa lebih awal dari konfirmasi |
| `confirmed_at` | Waktu closed candle yang menyelesaikan syarat pola |
| `available_at` | Maksimum waktu konfirmasi dan known_at seluruh evidence |
| `as_of` | Batas informasi snapshot/replay |

Simpan timezone-aware UTC dan raw provider timestamp untuk audit. Dokumentasi MT5 meminta datetime UTC; cocokkan hasil dengan broker dalam onboarding dan jangan menebak offset dari timezone OS. [S8]

Candle open tidak dipromosikan menjadi closed hanya karena jam lokal melewati interval. Verifikasi rollover/closed-history dari terminal. Pada jeda sesi, bar terakhir yang belum terkonfirmasi tetap provisional. Jangan mengisi weekend/gap dengan candle buatan.

Unique logical candle: `(feed_id, timeframe, opened_at)`. Payload sama menjadi no-op. Payload berbeda dari bar closed membuat `candle_revision` baru; revisi sebelumnya tetap ada. Snapshot menunjuk revision ID yang spesifik. Revisi historis tidak mengubah report lama dan tidak memicu alert lama seolah baru. Dampaknya memicu reanalysis, flag `DATA_REVISED`, dan audit.

### 7.4 Validasi minimum

OHLC finite dan positif; low <= min(open, close) <= max(open, close) <= high; bid <= ask; volume tidak negatif; digits dan tick size konsisten; timestamp UTC valid; timeframe allowed; simbol sesuai token/feed; candle tidak berada di masa depan melebihi toleransi clock 5 detik. Uji clock skew sebelum mempercayai freshness.

Price disimpan sebagai `NUMERIC(20,8)` dan dikirim sebagai decimal string. Engine memakai Decimal atau integer tick untuk perbandingan batas. Frontend boleh mengubah ke number untuk rendering, tetapi tidak untuk keputusan. `tick_volume` tidak disebut sebagai volume institusi/global. Price basis `bid`, `last`, atau `unknown` harus eksplisit; `unknown` memblokir paper fills.

## 8. Spesifikasi algoritma v0.1

Seluruh aturan bagian ini adalah **konvensi produk dan hipotesis eksperimen**, bukan definisi universal SMC atau strategi yang sudah terbukti profit. Parameter berada dalam `strategy_versions`. Tidak boleh diubah diam-diam untuk memperbaiki hasil backtest.

### 8.1 Konfigurasi dan numerik

| Parameter | Default eksperimen |
| --- | --- |
| Pivot kiri/kanan | 2 / 2 closed bars |
| ATR | Wilder ATR(14); seed rata-rata 14 TR awal |
| TR awal | high - low jika previous close belum ada |
| Buffer break | max(2 x tick_size, 0,05 x ATR sebelumnya) |
| FVG minimum | 2 x tick_size |
| Base SND | 1-4 candle sebelum displacement |
| Displacement | body >= 1,5 x ATR sebelumnya; body/range >= 0,60 |
| Base candle | body <= 0,50 ATR; range <= 1,00 ATR sebelumnya |
| SNR merge tolerance | 0,15 x ATR saat keputusan cluster |
| Lookback kandidat SNR | 200 closed bars |
| Expiry zona | 100 candle pada TF asal, dihitung sejak aktivasi |
| RR minimum | 2,00 setelah allowance biaya yang didefinisikan |
| Setup score minimum | 70/100; bukan probabilitas menang |

Setiap threshold ATR memakai nilai yang sudah tersedia sebelum candle pemicu. Seluruh boundary price dibulatkan konservatif ke tick: low/SL buy ke bawah, high/SL sell ke atas. RR tidak dibulatkan sebelum dibandingkan dengan minimum.

### 8.2 Pivot, struktur, BOS, CHoCH, sweep

Pivot high pada indeks i harus strict lebih tinggi dari semua high dalam L bar kiri dan R bar kanan. Pivot low menggunakan strict lebih rendah. Equal highs/lows tidak menghasilkan pivot strict; dapat menjadi liquidity cluster terpisah. Pivot baru tersedia setelah bar i+R closed dan seluruh evidence diterima.

Reducer struktur memakai pivot terkonfirmasi secara berurutan. Dua pivot bertipe sama berturut-turut: reference aktif diganti hanya jika lebih ekstrem; event sejarah tidak dihapus. Jika satu candle menjadi high dan low pivot sekaligus, simpan kedua bukti tetapi tandai ambiguous; jangan mengarang urutan intrabar.

Bias bullish memerlukan dua high dan dua low berurutan yang naik; bearish memerlukan keduanya turun; selain itu RANGE/UNKNOWN. Untuk bullish, protected low adalah low terkonfirmasi yang mendahului reference high saat ini. BOS adalah close > unbroken high + buffer. CHoCH adalah close < protected low - buffer. Aturan bearish dicerminkan. Equality tidak dianggap break.

CHoCh memindahkan state ke TRANSITION, bukan langsung menyatakan trend lawan pasti. Bias lawan baru terkonfirmasi setelah pasangan struktur yang sesuai tersedia. Satu swing break hanya menghasilkan satu event per direction/strategy version. Wick saja bukan BOS/CHoCH.

Sweep bawah: low < liquidity low - buffer dan close > liquidity low. Sweep atas: high > liquidity high + buffer dan close < liquidity high. Gunakan level yang sudah tersedia sebelum bar sweep. Close sama dengan level bukan sweep terkonfirmasi. Label sweep menjelaskan pola harga, bukan bukti niat pelaku pasar.

### 8.3 SNR

Gunakan pivot terkonfirmasi sejenis dalam 200 bar terakhir. Proses berdasarkan available_at. Gabungkan ke cluster dengan jarak pivot ke median cluster <= merge tolerance. Jika beberapa cluster cocok, pilih jarak terdekat lalu ID tertua. Dua pivot distinct yang berjarak minimal tiga bar diperlukan untuk membuat zona.

Batas zona adalah minimum harga anggota - 1 tick sampai maksimum + 1 tick, dengan lebar maksimum 0,50 ATR. Kandidat yang terlalu lebar tidak digabung. High cluster menjadi resistance, low cluster menjadi support. Penambahan anggota mengubah geometri melalui zona penerus `supersedes_zone_id`; jangan mengubah batas historis. Touch setelah aktivasi dihitung terpisah dari pivot pembentuk.

### 8.4 Supply dan demand

Temukan rangkaian base kontigu terpanjang, maksimum empat candle, tepat sebelum displacement. Semua candle base memenuhi batas body/range dan range beririsan dengan candle tetangganya. Jika tidak ada base valid, tidak ada zona.

Demand: displacement bullish dan close melewati high base + buffer. Low zona = minimum low base; high zona = maksimum max(open, close) base. Supply: displacement bearish dan close di bawah low base - buffer. Low zona = minimum min(open, close) base; high zona = maksimum high base.

Lebar zona minimal dua tick dan maksimal satu ATR sebelumnya. Zona tersedia setelah displacement closed. Candle pembentuk bukan retest. Retest hanya dinilai setelah waktu aktivasi.

### 8.5 FVG dan order block

FVG menggunakan tiga candle closed berurutan A, B, C pada sesi kontigu tanpa unresolved gap. Bullish jika low(C) - high(A) >= dua tick; zona `[high(A), low(C)]`. Bearish jika low(A) - high(C) >= dua tick; zona `[high(C), low(A)]`. Equality tanpa gap tidak valid. FVG tersedia pada close C, bukan saat A/B.

OB adalah candle berlawanan terakhir dalam maksimal lima candle sebelum displacement yang memenuhi rule dan kemudian menghasilkan BOS atau CHoCH dalam maksimal tiga candle. Batas OB memakai seluruh high/low candle kandidat. Arah mengikuti displacement. `formed_at` berada pada candle kandidat, tetapi `available_at` menunggu break terkonfirmasi. Jika area telah invalid sebelum konfirmasi, catat kandidat rejected dan jangan menerbitkan fresh OB.

OB, SND, dan FVG dari impulse yang sama diberi `origin_group_id`. Ketiganya tidak boleh dihitung sebagai tiga bukti independen dalam scoring.

### 8.6 Lifecycle dan mitigation

Pisahkan `lifecycle` (`active`, `invalidated`, `expired`, `superseded`) dari `mitigation` (`none`, `partial`, `full`) dan `touch_count`. Label fresh diturunkan dari active + zero touch + mitigation none, bukan status tambahan yang tumpang tindih.

Touch candle terjadi jika range beririsan dengan zona setelah aktivasi. Touch live memakai quote basis yang sesuai dan disimpan sebagai episode. Episode baru baru diizinkan setelah dua quote fresh di luar zona yang diperluas buffer dan cooldown minimal 10 detik. Satu batch retry tidak menambah touch.

Demand/bullish zone invalid jika close TF asal < low - buffer. Supply/bearish invalid jika close > high + buffer. Close tepat pada boundary bukan invalid. Invalidation menang atas touch/mitigation ketika semuanya baru diketahui pada candle closed yang sama; jangan mengirim alert touch baru yang langsung invalid.

Mitigation partial berarti ada overlap tetapi distal edge belum tercapai. Full berarti harga mencapai/menembus distal edge dari arah pendekatan yang sesuai setelah aktivasi, walaupun close kemudian pulih. Full-mitigated tidak memenuhi setup baru pada v0.1. Zona expired setelah 100 candle TF asal tanpa reactivation. Zona terminal tidak kembali aktif; versi/revision baru membuat ID baru.

### 8.7 Setup multi-timeframe

D1 adalah konteks. H4 menentukan arah, H1 memilih area, M15 menyediakan sweep, M5 memberi CHoCH dan retest. Baseline hanya menerima H4/H1 bullish untuk buy, bearish untuk sell; konflik menjadi WAIT.

Urutan buy, dicerminkan untuk sell:

1. Ada demand/OB H1 aktif yang belum full-mitigated, touch_count <= 1, dan bias H4/H1 bullish. Buat WATCHING.
2. Quote fresh atau candle closed masuk zona H1. Simpan evidence dan waktu kontak; state ARMED.
3. M15 menunjukkan sweep bawah yang confirmed_at sesudah kontak dan terjadi dalam tiga closed M15 bars sejak kontak.
4. M5 CHoCH bullish harus confirmed_at sesudah konfirmasi sweep, maksimum 12 closed M5 bars kemudian. State CONFIRMED.
5. Ambil FVG bullish M5 pertama yang tersedia pada/setelah CHoCH, maksimal enam M5 bars kemudian. Tunggu candle M5 berikutnya beririsan FVG dan close di atas high FVG. Tidak boleh memakai candle pembentuk FVG sebagai retest.
6. Hitung candidate entry referensi pada close trigger, SL di bawah minimum low zona H1/sweep dikurangi buffer, dan target di near edge opposing H1 zone terdekat di atas entry. Tanpa target yang valid: WAIT, bukan target hasil karangan.
7. Terapkan hard gates, score, dan RR. Jika lulus, READY_FOR_REVIEW. Ini bukan order. Paper fill hanya pada quote/next bar yang tersedia setelah sinyal.

READY kadaluarsa setelah tiga M5 bars, berubah bias, invalidasi zona, atau hard gate gagal. ARMED/CONFIRMED mengikuti timeout evidence di atas. Simpan `WAIT_REASON` yang spesifik, bukan teks generik.

### 8.8 Hard gates dan scoring

Hard gates: feed LIVE; semua TF yang diperlukan cukup history dan fresh; price basis diketahui; no unresolved gap/revision pending; kalender valid; spread <= batas yang ditetapkan owner setelah observasi; target/SL valid; RR >= 2; tidak ada kill switch. Spread threshold wajib dikonfigurasi dalam price units/ticks dari feed, bukan angka universal gold.

Score: alignment H4/H1 30; kualitas area H1 maksimum 25; sweep M15 15; CHoCH M5 15; FVG retest 10; SNR confluence independen 5. Area hanya mendapat satu kontribusi walaupun OB dan SND overlap. Area memberi 25 untuk zona fresh, 20 untuk first-touch yang masih eligible, dan 0 bila tidak eligible. Alignment memberi 30 hanya bila H4/H1 searah; sweep, CHoCH, dan FVG memberi bobot penuh hanya setelah tahap masing-masing valid. SNR memberi 5 bila origin_group berbeda dan overlap minimal 50% dari lebar zona yang lebih sempit. Selain itu kontribusi komponen adalah 0. Semua komponen disimpan. Score tidak mengubah hard gate dan bukan persentase kemenangan.

RR simulasi buy = `(target - entry - profit_cost) / (entry - stop + loss_cost)`. Sell dicerminkan. `profit_cost` dan `loss_cost` adalah allowance adverse price movement tambahan yang didefinisikan skenario; spread tidak boleh dihitung dua kali jika entry/exit sudah memakai bid/ask executable. Nilai negatif/nonfinite atau risk <= 0 ditolak.

## 9. Agent dan AI contract

### 9.1 Modul bukan banyak LLM

| Modul | Jenis | Output |
| --- | --- | --- |
| Data quality guard | Deterministik | Health, gap, completeness, eligible TF |
| Structure analyzer | Deterministik | Pivot, bias, BOS, CHoCH, sweep |
| Zone analyzer | Deterministik | SNR/SND/FVG/OB, evidence dan lifecycle |
| Setup evaluator | Deterministik | State, score, level, reason codes |
| Narrative analyst | Satu LLM | Penjelasan berbasis evidence dan skenario yang disediakan |
| Publication guard | Deterministik | Publish/reject/fallback, audit |

### 9.2 Input dan output

Input AI berisi snapshot ID/hash, as_of, feed health, bias, evidence IDs, selected zones/setups, conflict codes, missing context, dan daftar action yang diizinkan. Tidak menyertakan password, account number, saldo, posisi personal, atau raw tick dump.

Model mengembalikan schema dalam `contracts/ai-report.schema.json`: `schema_version`, `snapshot_id`, `stance`, `summary`, `scenario_ids`, `highlights` dengan evidence IDs, `conflict_codes`, dan `missing_context`. Tidak ada field entry/SL/TP atau lot size. Harga yang tampil di report di-hydrate dari snapshot deterministic.

OpenAI Structured Outputs dipakai untuk bentuk output. Schema tidak menjamin kebenaran, dan refusal/incomplete response harus ditangani. Validasi semantik tetap wajib. [S12]

Publication guard memeriksa snapshot ID, subset evidence/scenario ID, conflict/missing-context codes yang sesuai snapshot, stance yang diizinkan, data freshness pada waktu publish, dan price-like numerals dalam free text. Teks AI tidak boleh memperkenalkan level numerik; token timeframe seperti M5/H1 boleh. Pelanggaran menghasilkan deterministic fallback. Pemeriksaan teks tidak membuktikan seluruh klaim naratif benar; tetap sediakan label AI-generated dan evidence review.

### 9.3 Jadwal, retry, dan budget

Report berlaku untuk snapshot-nya, bukan janji bahwa kondisi tetap sama. `valid_until` default 15 menit setelah generated_at atau expiry setup terkait, mana yang lebih awal. Setelah itu tetap tersedia sebagai laporan historis dengan label waktu, bukan live stance.

Default brief: 07:00, 13:00, 19:00 Asia/Jakarta pada hari/sesi yang relevan. Ini jadwal tetap WIB, bukan klaim jam buka London/New York. Dukungan session-relative memakai IANA timezone dan calendar adapter pada fase berikutnya.

Engine teknikal berjalan pada candle close; quote memantau kontak zona. AI event briefing hanya saat state berubah material, dengan cooldown 15 menit per setup. Manual refresh maksimum tiga kali per jam. Default global cap 20 panggilan AI per hari, output 1.200 token per call, satu call concurrent. Nominal budget harian ditetapkan owner sebelum AI diaktifkan.

Dedupe AI: snapshot hash + prompt version + model ID + report type. Retry maksimal dua percobaan total untuk gangguan sementara, menghormati Retry-After dan deadline 60 detik. Error schema/refusal tidak dianggap sukses. AI disabled/budget exhausted menghasilkan brief template; engine, chart, dan alert teknikal terus berfungsi.

## 10. Model database dan invariants

Gunakan UUID untuk object publik; bigint boleh untuk candle/revision internal. Semua foreign key lintas feed diverifikasi. Timestamps TIMESTAMPTZ; harga NUMERIC(20,8); JSONB hanya untuk parameter/evidence tambahan, bukan menggantikan kolom query utama.

| Entitas | Kolom inti dan relasi | Constraint/index penting |
| --- | --- | --- |
| users | id, role, timezone, 2FA metadata | email unique; policies |
| instruments | id, canonical_symbol, quote_currency | canonical_symbol unique |
| market_feeds | instrument_id, broker, server, broker_symbol, digits, tick_size, price_basis, calendar_version | unique broker/server/symbol; tick_size > 0 |
| collectors | feed_id, token_hash, last_heartbeat, version | token scoped; revoke timestamp |
| ingest_batches | collector_id, idempotency_key, payload_hash, receipt, status | unique collector/key; immutable response |
| candles | feed_id, timeframe, opened_at, current_revision_id | unique feed/TF/opened_at |
| candle_revisions | candle_id, revision, OHLC, closed_at, known_at, payload_hash, reason | unique candle/revision; OHLC checks |
| quote_samples | feed_id, source_time, received_at, bid, ask, sequence | bid <= ask; feed/time index; retention |
| data_gaps | feed_id, TF, start/end, reason, resolved_at | feed/TF/status index |
| strategy_versions | name, semver, config_json, code_commit, config_hash, activated_at | config_hash unique; immutable once active |
| analysis_snapshots | feed_id, as_of, strategy_version_id, data_hash, health, state_version | exact candle revision/evidence references |
| snapshot_candles | snapshot_id, candle_revision_id | composite unique; no future known_at |
| structure_events | snapshot_id, type, direction, level, formed/confirmed/available_at, evidence | event_key unique |
| zones | feed_id, TF, type, direction, low/high, strategy, origin_group, lifecycle, mitigation, times, supersedes_zone_id | low < high; geometry immutable; feed/TF/lifecycle index |
| zone_events | zone_id, type, event_time, available_at, quote/candle evidence | event_key unique; append only |
| trade_setups | snapshot_id, direction, state, score, entry/stop/target, expires_at, reason_codes | constraints on level ordering |
| setup_events | setup_id, previous/new state, reason, evidence | append only; expected state version |
| analysis_runs | snapshot_id, prompt_version, provider/model, request_hash, status, usage, error | dedupe unique; no secrets |
| analysis_reports | analysis_run_id, validated_body, origin, generated_at, valid_until | origin ai/template; immutable published body |
| alerts | event_id, type, recipient, payload, status, dedupe_key | dedupe_key unique |
| alert_deliveries | alert_id, attempt, provider_id, outcome, retry_at | unique alert/attempt; unknown delivery supported |
| outbox_events | aggregate_id, event_type, payload, event_seq, published_at | event_key unique; unpublished index |
| backtest_runs | feed_id, dataset_hash, strategy, config, period, mode, metrics, status | immutable run inputs |
| paper_trades | backtest_run_id, setup_id, entry/exit evidence, cost, R, ambiguity | no link to broker order execution |
| audit_logs | actor, action, entity, before/after hash, request_id | append only; sensitive fields redacted |
| system_settings | validated values, revision, updated_by | separate secrets from public settings |

Relasi utama: instrument -> feeds -> candles -> revisions -> snapshots -> structure/zones/setups -> reports/alerts. Event terkait state lama tetap menunjuk snapshot lamanya, walaupun current view sudah berubah.

DB invariants: low < high untuk zona; BUY stop < entry < target dan SELL target < entry < stop; RR/score finite; score 0..100; event available_at >= confirmed_at; snapshot tidak boleh menunjuk evidence yang available_at > as_of. Validasi lintas tabel dilakukan domain service dalam transaksi dan diuji, tidak hanya mengandalkan CHECK satu baris.

Retensi awal: quote samples 7 hari; logs non-audit 30 hari; candles dan strategy versions minimal 24 bulan jika disk memungkinkan; snapshots/reports/audit minimal 12 bulan. Data yang direferensikan run yang dipertahankan tidak boleh dihapus. Tampilkan estimasi disk sebelum purge. Tidak perlu TimescaleDB atau partitioning pada hari pertama; tambahkan setelah pengukuran.

## 11. Kontrak API, event, dan queue

### 11.1 Prinsip API

Prefix `/api/v1`. Web dashboard memakai same-origin session + CSRF. Machine ingest memakai bearer token scoped, tanpa cookie. Engine token berbeda, network internal only. UI memakai feed_id, bukan simbol XAUUSD saja, agar broker tidak tercampur.

Respons error: `error.code`, `error.message`, `error.fields`, `request_id`. JSON harga berupa string. Pagination candle: `before` eksklusif, limit 1..2.000, hasil ascending; timezone UTC. Semua schema memiliki `schema_version`.

| Method/path | Otoritas | Perilaku |
| --- | --- | --- |
| POST /collector/batches | Collector | <= 1.000 closed candles; atomik; Idempotency-Key wajib |
| POST /collector/quotes | Collector | Quote sequence; out-of-order tidak mengganti latest |
| POST /collector/heartbeat | Collector | Health dan clock telemetry |
| GET /feeds/{id}/overview | Session | Health, latest quote, TF bias, snapshot ID |
| GET /feeds/{id}/candles | Session | TF, before, limit; closed history; provisional dipisah |
| GET /feeds/{id}/zones | Session | TF/status/type/as_of; cursor pagination |
| GET /feeds/{id}/structures | Session | Event terkonfirmasi, bounded time range |
| GET /feeds/{id}/setups | Session | Setup aktif/history dan evidence |
| GET /feeds/{id}/reports/latest | Session | Latest validated report + stale metadata |
| POST /feeds/{id}/analysis-runs | Owner + CSRF | Return 202/run ID; rate limit; idempotent |
| GET /analysis-runs/{id} | Session | Pending/running/succeeded/fallback/failed |
| GET /alerts | Session | Riwayat dan status delivery |
| POST /backtests | Owner + CSRF | 202; run immutable; feature flag P5 |
| GET /backtests/{id} | Session | Progress, metrics, limitations |
| PATCH /settings/{group} | Owner + CSRF | Validasi; config version; audit |
| GET /system/health | Owner | Redacted dependency details |

Endpoint engine internal: `POST /internal/v1/evaluate` menerima snapshot dengan closed candles dan previous state; mengembalikan deterministic events/new state. `POST /internal/v1/replay-chunk` menerima dataset slice/checkpoint; mengembalikan checkpoint baru. Timeout compute 30 detik live, 60 detik chunk. HTTP retry harus memakai request hash yang sama.

### 11.2 Idempotency dan conflict

Idempotency-Key sama + hash sama -> respons receipt yang sama, tanpa effect baru. Key sama + hash berbeda -> 409 `IDEMPOTENCY_CONFLICT`. Bad payload -> 422 tanpa sebagian bar masuk. Token salah/revoked -> 401; feed tidak berhak -> 403; oversize -> 413; throttle -> 429. Receipt hanya dikembalikan setelah commit DB.

Request pertama dan retry memakai timestamp request yang baru jika kredensial memiliki freshness checks; payload identity tidak berubah. Hindari nonce yang membuat retry sah selalu ditolak. Collector menghapus spool hanya setelah menerima committed receipt.

### 11.3 Realtime dan queues

Private channels: `private-feed.{feed_id}` dan `private-user.{user_id}`. Event envelope: event_id, event_type, feed_id, event_seq, schema_version, occurred_at, published_at, payload. Jenis: QuoteUpdated, CandleClosed, ZoneChanged, SetupChanged, ReportPublished, FeedHealthChanged.

Browser mengabaikan event_seq lama; jika sequence gap/reconnect, fetch snapshot terbaru sebelum melanjutkan. Poll fallback 5 detik, dengan badge DEGRADED. Jangan broadcast full candle history pada setiap tick. Reverb origin allowlist hanya hostname aplikasi. [S5]

Queue: `market-critical`, `technical`, `notifications`, `ai`, `backtests`. Live workers mempunyai kapasitas terpisah. Worker timeout selalu lebih pendek dari queue retry_after; request timeout lebih pendek dari worker timeout. Job uniqueness dibantu DB constraint, bukan lock Redis saja. Jadwal memakai overlap lock dan satu scheduler aktif. [S3][S4]

## 12. Alert dan reporting

Event alert: zone first touch, setup confirmed, ready for review, invalidated, expired, feed degraded, feed recovered, brief ready. Default Telegram hanya READY, INVALIDATED untuk setup yang pernah dikirim, gangguan > 60 detik, dan brief terjadwal. Touch alert dapat diaktifkan; jangan memenuhi chat dengan setiap tick.

Deduplication key = event ID + channel + recipient. Cooldown menahan event yang repetitif, tetapi tidak boleh menahan invalidation penting. Sebelum kirim, refresh validity dan as_of; setup expired tidak dikirim sebagai setup aktif. Replay/backfill tidak mengirim trading alerts.

Telegram delivery dapat ambigu jika timeout terjadi setelah provider menerima pesan. Simpan `delivery_unknown`, jangan menjanjikan exactly-once. Pesan memuat short event ID agar duplikat yang jarang terjadi dapat dikenali. Retry bounded dan log tiap attempt; aplikasi in-app menjadi histori otoritatif.

Template mencantumkan feed, timeframe, event time WIB, data age, zona dari engine, alasan, invalidation, dan tautan privat. Tidak menulis instruksi eksekusi otomatis. News coverage selalu terlihat; `NEWS_NOT_CONNECTED` bukan berarti tidak ada news penting.

## 13. Replay, paper simulation, dan evaluasi

### 13.1 Dua mode waktu

**Knowledge replay:** data/revisi hanya tersedia saat known_at aslinya. Cocok untuk periode yang dikoleksi sistem secara live. As_of membatasi candle, pivot, zona, dan konfigurasi.

**Historical reconstruction:** history diunduh belakangan, sehingga receipt time asli tidak diketahui. Simulasikan availability berdasarkan candle close + confirmation delay dan nyatakan asumsi. Jangan menyebutnya rekonstruksi sempurna kondisi live; revisi history provider mungkin sudah tercakup.

Dataset hash, strategy hash, session calendar version, cost assumptions, dan code commit wajib disimpan. Replay chunked dan full-run menghasilkan event hash identik untuk input yang sama. Data yang diperoleh sesudah periode uji tidak boleh digunakan untuk memilih parameter holdout.

### 13.2 Paper fills dan biaya

Entry tidak menggunakan harga close pemicu sebagai fill otomatis. Gunakan quote executable berikutnya setelah READY: buy masuk ask/keluar bid; sell masuk bid/keluar ask. Jika bid/ask historis tidak tersedia, gunakan candle berikutnya dan asumsi spread yang dinyatakan secara eksplisit; label hasil sebagai bar-based simulation.

Harga entry aktual harus memicu perhitungan ulang RR. Gap melewati entry/SL atau RR di bawah minimum menghasilkan skipped fill atau adverse fill sesuai aturan run, tidak diisi pada harga yang tidak pernah tersedia. Stop gap menggunakan executable next price yang tersedia.

Jika SL dan TP tersentuh pada candle yang sama tanpa tick/lower-TF yang menjelaskan urutannya: tandai ambiguous. Laporkan rentang hasil, dan untuk ringkasan konservatif gunakan stop-first. Jangan diam-diam memilih TP-first. SND/FVG touch intrabar juga diberi label keterbatasan yang sama.

Default evaluasi dalam unit R tanpa sizing modal. Komisi/slippage/spread menggunakan input run yang terversi; spread tidak dihitung dua kali. Cash P&L hanya boleh ditampilkan setelah contract size, lot, currency conversion, dan biaya tervalidasi.

### 13.3 Metrik dan validasi strategi

Laporkan jumlah setup, jumlah trade simulasi, rejected/skipped/ambiguous count, expectancy R, win rate, profit factor (N/A bila pembagi nol), maximum drawdown R, MAE/MFE jika data mendukung, time in trade, dan breakdown per sesi/TF.

Split kronologis: 60% development, 20% validation, 20% untouched test, jika coverage memadai. Gunakan warmup dari masa sebelum masing-masing segmen tanpa memasukkan trade warmup ke metrik. Jangan random-shuffle time series. Walk-forward dan perbandingan baseline SNR-only dilakukan sebelum menyimpulkan manfaat rule gabungan. Minimal 20 sesi forward observation adalah gate operasional awal, bukan bukti profitabilitas.

## 14. Keamanan dan operasional home server

### 14.1 Network dan credentials

Akses development menggunakan OpenSSH key lewat Tailscale. HTTPS dashboard hanya pada interface/akses tailnet yang diizinkan. Jangan membuka port database, Redis, Reverb mentah, atau engine ke internet. Tailscale tidak menggantikan autentikasi aplikasi; default policy tailnet dapat mengizinkan komunikasi luas, jadi buat grants/ACL least-privilege secara eksplisit. [S13]

Jalur yang diizinkan: laptop developer -> server SSH/HTTPS; collector -> ingest HTTPS; Laravel -> engine internal; worker -> AI/Telegram HTTPS. Container database/Redis/engine tidak memiliki published host ports pada production profile. Uji juga akses LAN, bukan hanya internet, karena aturan tailnet tidak otomatis mengontrol seluruh lalu lintas LAN.

Simpan secret di environment/secret files dengan izin ketat. Jangan commit `.env`, MT5 credential, token Telegram, prompt berisi akun, atau DB dump ke Git. Token collector scope per feed dan dapat dirotasi. Gunakan kredensial read-only/investor bila broker mendukung; walaupun tidak tersedia, collector code tidak boleh mempunyai order path.

### 14.2 Isolasi development

Satu home server boleh menampung dev dan personal deployment dengan compose project, port, DB, volume, Redis prefix, dan secret berbeda. Coding agent hanya bekerja di workspace dev sebagai non-root. Akses Docker socket setara akses tinggi; jangan mount ke agent container tanpa kontrol. Semua operasi destructive memerlukan persetujuan, backup, dan target environment eksplisit.

### 14.3 Startup, recovery, dan backup

Collector menjalankan reconnect dengan exponential backoff + jitter, local durable spool, dan batas disk. Spool penuh menghasilkan error visible, bukan silent drop. Setelah reboot, verify terminal session, heartbeat, recent overlap backfill, lalu resume saat window lengkap. Jangan menganggap terminal MT5 selalu dapat berjalan tanpa sesi desktop yang terkonfigurasi.

Gunakan durable DB volume, Redis persistence sesuai kebutuhan, restart policies dan health checks. Scheduler/worker/reverb adalah process terpisah dari web. Saat deploy, lakukan migration kompatibel, restart worker gracefully, dan verifikasi readiness. Rollback aplikasi harus cocok dengan schema; migration destructive ditunda sampai backup dan plan disetujui.

Backup terenkripsi setiap hari ke media/host lain, bukan disk yang sama saja. Simpan 7 harian dan 4 mingguan sebagai baseline. Uji restore bulanan, termasuk kunci enkripsi aplikasi yang diperlukan. Jika backup gagal > 24 jam atau disk free < 15%, tampilkan warning; < 5% hentikan backfill/backtest dan prioritas menjaga audit/data integrity.

### 14.4 Observability dan kill switches

Metric wajib: last quote/heartbeat, gap count, rejected/revised candles, queue age, engine latency, failed jobs, AI calls/tokens/budget, alert failures, disk, backup age. Log terstruktur dengan request_id, feed_id, snapshot_id, strategy_version dan event_id; tanpa secret.

Switch terpisah: `INGEST_ENABLED`, `ANALYSIS_ENABLED`, `AI_ENABLED`, `NOTIFICATIONS_ENABLED`, `PAPER_ENABLED`. `EXECUTION_ENABLED` tidak disediakan. Pause harus terlihat di UI dan audit. Tidak ada jalur yang berubah menjadi live trading hanya karena flag salah.

## 15. Rencana implementasi dan release gates

Durasi belum ditetapkan karena hardware, broker, dan waktu development belum diketahui. Urutan ditentukan dependency, bukan janji kalender.

| Fase | Output | Gate untuk lanjut |
| --- | --- | --- |
| P0 - Bootstrap | Repo, docs, lockfiles, Laravel starter kit, custom shadcn shell, CI, Docker dev, auth | Build/test baseline; no Filament; isolated secrets; fixtures bisa dimuat |
| P1 - Data foundation | Feed config, Windows collector, spool, ingest, revisions, health, simple chart | Idempotency, disconnect/reconnect, UTC, native TF parity, gaps lulus |
| P2 - Technical engine | ATR, pivots, structure, SNR/SND/FVG/OB, lifecycle, evidence | Golden fixtures, no-lookahead, deterministic chunk replay lulus |
| P3 - Dashboard & setup | Chart primitives, MTF, state machine, hard gates, Reverb, UI history | Visual/behavioral tests, authorization, stale banner, event ordering lulus |
| P4 - AI & alerts | Structured brief, budget, fallback, schedules, Telegram outbox | Schema + semantic guards + dedupe + failure injection lulus |
| P5 - Replay & paper | Replay UI, costs, ambiguity, dataset manifests, metrics | No same-close fills, costs, holdout, reproducible results lulus |
| P6 - Personal release | Restore drill, hardening, runbook, forward observation | Semua MUST tests pass; zero critical issues; owner UAT approval |

### 15.1 Backlog per vertical slice

Setiap issue memuat FR ID, input/output, test ID, database change, migration/rollback impact, dan demo evidence. Satu PR menyelesaikan satu slice; jangan meminta coding agent membuat seluruh produk dalam satu langkah.

Prioritas pertama: owner login -> feed terkonfigurasi -> satu batch fixture -> candle tersimpan -> chart H1 tampil -> health terlihat. Setelah itu baru live collector. Jangan mulai dari LLM atau animasi dashboard.

### 15.2 Definition of Done per PR

Implementasi sesuai contract; unit/integration tests baru; full relevant suite pass; static checks; tidak ada secret; migration aman; empty/error/loading states; API schema/types sesuai; audit dan metrics relevan; dokumentasi berubah jika perilaku berubah. Pernyataan test harus menyertakan command dan hasil nyata, bukan hanya 'sudah dites'.

## 16. Strategi pengujian

`docs/TEST_PLAN.md` adalah test plan rinci dan traceability. `tests/engine/test_strategy_v01.py` adalah acceptance tests untuk interface Python yang wajib diimplementasikan. `tests/contracts/` memeriksa schema, fixture, dan invariants paket spesifikasi. `tests/acceptance/hybrid_analyst.feature` berisi skenario end-to-end untuk diikat ke aplikasi.

| Layer | Tool baseline | Sasaran |
| --- | --- | --- |
| PHP unit/feature | Pest/PHPUnit + PostgreSQL test DB | Ingest, auth, outbox, policy, settings |
| Python strategy | pytest + Decimal; property tests pada iterasi berikut | Pivot, zones, state machine, replay |
| React component | Vitest + Testing Library | Health states, chart adapter, filters, evidence panel |
| Browser | Playwright | Login sampai chart, setup, report, settings, reconnect |
| Contract | JSON Schema + API contract assertions | Cross-language payload compatibility |
| Operasional | Failure scripts + isolated restore environment | Restart, gap, disk, restore, secret exposure |

Pytest fixtures/parametrization, Vitest coverage, dan Playwright web-first assertions menjadi rujukan implementasi test. Hindari sleep arbitrer dan assertion berbasis output yang dihitung ulang oleh fungsi yang sedang diuji. [S15][S16][S17]

Target coverage: strategy package >= 90% branch; Laravel domain services >= 80% line; custom React logic >= 80% line. Exclude generated shadcn/vendor code secara transparan, tetapi component interaction penting tetap diuji. Coverage bukan bukti strategi benar; semua critical golden cases tetap mandatory.

CI memblokir merge jika test kritis gagal, lint/typecheck gagal, contract drift, future-data test gagal, atau secret terdeteksi. Unit/CI memakai fixture dan mock AI/Telegram/MT5, bukan market live. Broker parity tests terpisah, read-only, opt-in pada Windows runner.

## 17. Risiko dan keputusan yang tersisa

| Risiko | Respons desain | Keputusan owner sebelum aktivasi |
| --- | --- | --- |
| Feed berbeda dengan chart luar | Satu feed canonical, label broker selalu terlihat | Broker, server, symbol |
| Collector laptop sering tidur | Health gate dan always-on host | Windows host/VM yang dipakai |
| Aturan terlalu ketat / tidak ada setup | WAIT valid; evaluasi per rule, bukan longgarkan diam-diam | Setujui versi eksperimen |
| Look-ahead/repainting | available_at, immutable revisions, prefix replay | Tidak boleh dinonaktifkan |
| LLM mengarang klaim | Evidence whitelist, no price fields, fallback, review | Provider/model dan budget |
| News belum terintegrasi | Warning eksplisit, technical-only brief | Pilihan data provider tahap berikut |
| Backtest terlalu optimistis | Next executable fill, cost, ambiguity, holdout | Spread/slippage assumptions |
| Home server mati | Spool, recovery, backup ke media lain | Storage/backup destination |

P0 tidak menunggu semua keputusan ini untuk membuat fixture-driven foundation. Tetapi AI live, setup READY, dan paper fill harus tetap disabled sampai konfigurasi yang relevan selesai diverifikasi.

## 18. Interface Python untuk acceptance tests

Paket import: `xau_engine.contracts_v1`. Semua fungsi pure; tidak membaca clock nyata, DB, jaringan, atau environment. Decimal strings in/out. Unit fungsi di bawah adalah adapter stabil bagi test acceptance, bukan seluruh engine.

| Fungsi | Kontrak |
| --- | --- |
| confirmed_pivots(bars, left=2, right=2) | List dict: kind, index, price, confirmed_index; hanya bars closed |
| detect_fvgs(bars, tick_size) | List dict: direction, low, high, formed_index, confirmed_index; direction buy/sell |
| classify_break(direction, close, reference, protected, buffer) | BOS / CHOCH / NONE; equality bukan break |
| zone_invalidated(direction, close, low, high, buffer) | Bool berdasarkan closed price dan strict threshold |
| net_rr(direction, entry, stop, target, profit_cost, loss_cost) | Decimal; invalid geometry/cost menghasilkan ValueError |

`classify_break` menerima direction `bullish` atau `bearish`; fungsi zona/FVG/RR memakai `buy` atau `sell`. Invalid direction, nonfinite price, biaya negatif, dan geometri tidak sah menghasilkan ValueError.

Bars adalah list ordered dict `{open, high, low, close, is_closed}`; harga berupa decimal string. Fungsi closed-bar detectors mengabaikan bar terakhir yang provisional, dan menolak provisional bar di tengah history. `formed_index` FVG menunjuk A, `confirmed_index` menunjuk C. Pivot output diurutkan index lalu kind. Adapter harus memakai fungsi produksi, bukan implementasi test-only.

## 19. Dokumen dan prioritas kontrak

Jika ada pertentangan, stop pada issue terkait dan buat decision record. Urutan otoritas: PRD untuk perilaku domain; JSON Schema untuk bentuk wire payload; golden fixture untuk contoh numerik; TEST_PLAN untuk verifikasi; AGENTS.md untuk workflow. Jangan mengganti tes agar mengikuti bug implementasi.

Schema di paket ini meliputi candle batch, quote, dan output AI. `ai-report.api-schema.json` adalah bentuk struktural untuk request model; schema lokal yang lebih ketat tetap wajib diterapkan setelah respons, karena subset JSON Schema provider perlu diuji saat integrasi. Endpoint lain dijelaskan sebagai kontrak semantik dan harus diwujudkan dalam OpenAPI oleh P0/P1; jangan mengklaim seluruh API sudah diimplementasikan. Fixture sintetis memakai feed `fixture-gold` dan bukan harga/sinyal XAUUSD aktual.

## 20. Referensi primer

Referensi diverifikasi 2 November 2026. Sumber mendukung kemampuan platform; aturan strategi dan target SLA merupakan keputusan desain dokumen ini.

[S1] [Laravel 13 release notes](https://laravel.com/framework/docs/releases) - baseline runtime dan support policy.  
[S2] [Laravel React starter kit](https://laravel.com/framework/docs/13.x/starter-kits) - React, Inertia, Tailwind, shadcn/ui.  
[S3] [Laravel queues](https://laravel.com/framework/docs/13.x/queues) - queue processing, timeout, uniqueness, transaksi.  
[S4] [Laravel scheduling](https://laravel.com/framework/docs/13.x/scheduling) - scheduler dan overlap control.  
[S5] [Laravel Reverb](https://laravel.com/framework/docs/13.x/reverb) - realtime server dan allowed origins.  
[S6] [Laravel Horizon](https://laravel.com/framework/docs/13.x/horizon) - Redis queues dan worker monitoring.  
[S7] [MT5 copy_rates_from_pos](https://www.mql5.com/en/docs/python_metatrader5/mt5copyratesfrompos_py) - index bar dan OHLC fields.  
[S8] [MT5 copy_rates_from](https://www.mql5.com/en/docs/python_metatrader5/mt5copyratesfrom_py) - UTC, timeframe, history limits.  
[S9] [MetaTrader5 package](https://pypi.org/project/metatrader5/) - distribution dan Windows wheels.  
[S10] [Lightweight Charts series primitives](https://tradingview.github.io/lightweight-charts/docs/plugins/series-primitives) - custom chart overlay.  
[S11] [Lightweight Charts repository](https://github.com/tradingview/lightweight-charts) - license dan attribution notice.  
[S12] [OpenAI Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs) - schema, refusal, incomplete output, limitations.  
[S13] [Tailscale access controls](https://tailscale.com/docs/features/access-control/acls) - policy dan default permissions.  
[S14] [FastAPI background tasks](https://fastapi.tiangolo.com/tutorial/background-tasks/) - caveat heavy processing.  
[S15] [pytest parametrization](https://docs.pytest.org/en/stable/how-to/parametrize.html) - reusable automated scenarios.  
[S16] [Vitest coverage](https://vitest.dev/guide/coverage) - frontend coverage.  
[S17] [Playwright assertions](https://playwright.dev/docs/test-assertions) - web-first assertions.  
[S18] [shadcn/ui Laravel installation](https://ui.shadcn.com/docs/installation/laravel) - official integration.
