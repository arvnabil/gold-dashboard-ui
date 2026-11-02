# Test Plan & Acceptance Matrix

**Produk:** XAUUSD AI Hybrid Analyst v1.0  
**Tanggal:** 2 November 2026  
**Jumlah kasus:** 84  
**Status produk:** spesifikasi test; belum dijalankan terhadap aplikasi yang belum dibangun.

## 1. Scope dan test environment

Test mencakup data integrity, strategi, AI guardrails, UI, security, resilience, replay dan paper simulation. Gunakan fixture sintetis tanpa live broker order. Fixture tidak mewakili harga pasar aktual.

Environment wajib: PostgreSQL test database terpisah, Redis namespace terpisah, aplikasi testing dengan no external notification default, mock MT5/AI/Telegram, frozen clock UTC, seed deterministik. Jangan memakai SQLite sebagai satu-satunya integration test database karena kontrak production memakai PostgreSQL.

Unit tests harus bebas network. Broker parity test adalah suite opt-in, read-only, dijalankan di Windows dengan izin pengguna. Browser tests memakai app staging privat dan synthetic feed. Load/restore tests tidak dijalankan pada volume production.

## 2. Prioritas dan release gate

Critical: semua test wajib pass sebelum fitur terkait diaktifkan. High: wajib diperiksa sebelum rilis v1; penyimpangan target performa harus terukur dan disetujui, bukan disembunyikan. Tidak ada waiver untuk future data, secret exposure, execution boundary, atau data corruption.

Gate merge: lint, typecheck, unit, integration, contract dan security checks. Gate release: browser/UAT, replay, failure recovery, restore drill, serta observasi forward. Coverage minimum mengikuti PRD; coverage tinggi tidak menggantikan expected outputs yang benar.

## 3. Acceptance matrix

### Market data & collector

| ID / layer | Requirement | Skenario | Expected result |
| --- | --- | --- | --- |
| DATA-01 / integration | FR-02, FR-03 | Kirim valid candle-batch fixture ke feed yang diizinkan. | 201 atau committed receipt sesuai kontrak; satu logical candle dan satu revision; OHLC sama persis; satu outbox event. |
| DATA-02 / integration | FR-03, FR-04 | Kirim payload dan Idempotency-Key sama dua kali, lalu 10 request paralel. | Receipt sama; satu batch effect, satu candle, satu logical outbox event. Tidak ada double touch/analysis. |
| DATA-03 / integration | FR-03 | Ulangi Idempotency-Key tetapi ubah close dari 102.00 menjadi 102.01. | 409 IDEMPOTENCY_CONFLICT; data committed pertama tidak berubah. |
| DATA-04 / integration | FR-03, FR-04 | Batch dua candle; candle kedua high < close atau low > open. | 422 dan rollback seluruh batch; candle pertama juga tidak tersimpan. |
| DATA-05 / unit/integration | FR-03, FR-08 | Kirim is_closed=false ke closed-candle endpoint; tampilkan provisional bar melalui jalur UI. | Ingest closed menolak; provisional boleh tampil, tetapi tidak memicu pivot/BOS/zona confirmed. |
| DATA-06 / integration | FR-04 | Bar closed dikirim ulang dengan nilai berbeda dan key baru. | Revision baru; current pointer berganti; snapshot/report lama tetap menunjuk revision lama; DATA_REVISED tercatat. |
| DATA-07 / integration | FR-04, FR-17 | Revision baru diterima setelah as_of snapshot lama. | Knowledge replay lama tidak dapat membaca revision baru; original result hash tetap sama. |
| DATA-08 / integration | FR-04, FR-05 | Hapus satu M5 bar pada sesi open dari window analisis. | Unresolved gap; health DEGRADED; READY baru diblokir; tidak ada candle sintetis. |
| DATA-09 / integration | FR-02, FR-04 | Lewati interval akhir pekan/session break yang ada pada calendar version. | Tidak dihitung sebagai data gap; label MARKET_CLOSED sesuai kalender. |
| DATA-10 / integration | FR-03, FR-05 | Heartbeat diterima tetapi quote age melewati 10 detik saat sesi open. | STALE, bukan LIVE; chart menampilkan last update; setup baru diblokir. |
| DATA-11 / integration | FR-05, FR-21 | Matikan collector lebih dari 30 detik, lalu hidupkan dan lakukan overlap backfill. | DISCONNECTED lalu WARMING_UP; LIVE hanya setelah dua heartbeat sehat, quote fresh dan gap window selesai. |
| DATA-12 / integration | FR-03, FR-04 | Quote sequence baru masuk, lalu quote lama; ulangi setelah collector restart dengan session_id baru. | Latest quote tidak mundur; ordering mempertimbangkan session/source time; history lama tidak memundurkan state. |
| DATA-13 / broker parity | FR-02, FR-06 | Bandingkan 50 closed candles per TF dengan terminal; sertakan rentang perubahan session/DST bila tersedia. | OHLC, open time, price basis dan native H4/D1 cocok; mismatch memblokir feed activation. |
| DATA-14 / unit | FR-03, FR-04 | Uji NaN, Infinity, harga negatif/nol, volume negatif, precision berlebih, timeframe invalid, future timestamp. | Tolak dengan 422/validation code; tidak ada crash atau parsing float yang mengubah nilai diam-diam. |
| DATA-15 / resilience | FR-03, FR-21 | Putus jaringan setelah DB commit tetapi sebelum collector menerima receipt. | Collector retry dari spool; server mengembalikan receipt lama; spool dihapus hanya setelah receipt sah. |
| DATA-16 / integration | FR-02, FR-05 | Price basis/calendar belum verified atau provider history lebih pendek dari permintaan. | Coverage aktual terlihat; status unknown/degraded; paper fill dan READY diblokir tanpa data palsu. |

### Deterministic strategy engine

| ID / layer | Requirement | Skenario | Expected result |
| --- | --- | --- | --- |
| ENG-01 / pytest | FR-08 | High [101,102,105,102,101], L=R=2. Evaluasi prefix 3,4,5 bars. | Pivot index 2 tidak ada pada prefix 3/4; muncul pada prefix 5 dengan confirmed_index=4. |
| ENG-02 / pytest | FR-08 | Ubah high tetangga menjadi 105; uji low sama besar secara simetris. | Strict pivot tidak terbentuk; equal level tidak dipaksa menjadi pivot. |
| ENG-03 / property/replay | FR-08, FR-19 | Tambahkan 50 future bars setelah cutoff; filter hasil berdasarkan available_at <= cutoff. | Event dan geometri yang sudah diketahui identik dengan hasil prefix saja; no look-ahead. |
| ENG-04 / pytest | FR-08 | 14 TR bernilai 2, kemudian TR=4. | Seed ATR=2; berikutnya ATR=(2*13+4)/14=15/7. Threshold trigger memakai ATR sebelumnya. |
| ENG-05 / pytest | FR-08 | Bullish reference 105, protected 99, buffer .02; close 105.02 lalu 105.03. | Equality menghasilkan NONE; 105.03 menghasilkan BOS; hanya satu BOS untuk swing yang sama. |
| ENG-06 / integration | FR-08 | Wick menembus reference tetapi close kembali di dalam. | Bukan BOS/CHoCH. Sweep hanya jika syarat direction dan close-back terpenuhi. |
| ENG-07 / pytest/integration | FR-08 | Bullish close 98.97 terhadap protected 99 dan buffer .02. | CHOCH; state TRANSITION; tidak langsung memaksakan bearish bias tanpa struktur lawan. |
| ENG-08 / pytest/integration | FR-08 | Liquidity low=100, buffer=.02; low=99.97, close=100.01; ulang close=100.00. | Kasus pertama sweep bullish; equality close bukan sweep confirmed; reference harus sudah available. |
| ENG-09 / pytest | FR-11 | FVG bullish A.high=101, C.low=103; bearish A.low=103, C.high=101. | Zona [101,103], arah sesuai, confirmed_index=2; belum ada saat hanya A/B. |
| ENG-10 / pytest | FR-11 | Tick .01; gap 0, .01, .02. | Gap 0/.01 ditolak; .02 diterima; provisional C tidak membentuk FVG. |
| ENG-11 / integration | FR-11 | OB kandidat tersedia, impulse terjadi, structural break baru pada candle berikutnya. | OB hanya available pada break confirmation; chart origin boleh lebih awal tetapi replay tidak menampilkan OB sebelum tersedia. |
| ENG-12 / integration | FR-11, FR-12 | OB candidate sudah invalid sebelum break confirmation. | Kandidat rejected; tidak terbit fresh OB atau fresh-zone alert. |
| ENG-13 / pytest | FR-10 | Base memenuhi body/range/overlap; displacement tepat 1.5 ATR dan body/range .60. | Boundary yang memakai >= diterima; zona demand/supply mengikuti rumus base, bukan wick impulse. |
| ENG-14 / pytest | FR-10 | Base terputus, lima candle diperlukan, width > ATR, atau displacement body kurang threshold. | Tidak ada zona valid dari kandidat tersebut; reason code menjelaskan syarat yang gagal. |
| ENG-15 / pytest | FR-09 | Dua pivot sejenis terpisah >=3 bars; harga berjarak <=.15 ATR; beberapa cluster kandidat. | Cluster terdekat dipilih, tie memakai ID tertua; wide cluster >.50 ATR tidak digabung. |
| ENG-16 / integration | FR-09, FR-17 | Pivot baru mengubah bounds cluster SNR yang sudah diterbitkan. | Zona penerus/supersession; historical geometry dan snapshot lama tidak berubah. |
| ENG-17 / pytest | FR-12 | Buy zone [100,102], buffer=.02; close 99.98 lalu 99.97. Sell simetris. | 99.98 tidak invalid, 99.97 invalid. Invalidated tidak dapat kembali active dengan quote berikutnya. |
| ENG-18 / integration | FR-12, FR-16 | Satu closed candle sekaligus menembus zona dan memenuhi invalidation. | Prioritas invalidation; tidak menerbitkan fresh touch alert yang langsung tidak berlaku. |
| ENG-19 / integration | FR-12 | Quote oscillation di batas, retry event, lalu dua quote di luar expanded zone dan cooldown 10 detik. | Touch count satu per episode; hanya entry setelah rearm membuat episode baru. |
| ENG-20 / replay | FR-12 | Jalankan 100 closed bars TF asal sejak activation tanpa invalidation; uji full mitigation sebelum itu. | Expiry mengikuti TF asal, bukan chart TF; full mitigation memblokir setup baru; event origin tetap tersimpan. |

### Multi-timeframe setup & risk gates

| ID / layer | Requirement | Skenario | Expected result |
| --- | --- | --- | --- |
| SET-01 / integration | FR-13 | Replay seluruh urutan H1 contact -> M15 sweep -> M5 CHoCH -> FVG -> later retest. | WATCHING, ARMED, CONFIRMED, READY_FOR_REVIEW berurutan; evidence timestamps dan IDs lengkap. |
| SET-02 / integration | FR-13 | CHOCH terjadi sebelum sweep confirmation atau retest memakai candle pembentuk FVG. | Tidak READY; reason LTF_CONFIRMATION_MISSING/SEQUENCE_INVALID; tidak reorder evidence agar cocok. |
| SET-03 / integration | FR-13 | H4 bullish tetapi H1 bearish, atau H4 RANGE. | WAIT dengan HTF_CONFLICT; AI tidak dapat override. |
| SET-04 / pytest/integration | FR-13 | SND, OB, FVG berasal dari origin_group yang sama. | Kontribusi area maksimum 25; tidak mengklaim tiga bukti independen; total score <=100. |
| SET-05 / pytest | FR-13, FR-20 | Buy entry100, stop98, target106; cost0 menghasilkan RR3. Cost profit .2/loss .1 menghasilkan 5.8/2.1. | Hasil Decimal sesuai oracle; cost negatif/geometry terbalik/risk nol ditolak. |
| SET-06 / integration | FR-13 | Semua pattern valid tetapi spread melampaui configured limit atau RR=1.9999. | Tidak READY walaupun score 100; RR tidak dibulatkan menjadi 2 untuk meluluskan gate. |
| SET-07 / integration | FR-13, FR-16 | READY menunggu lebih dari tiga M5 bars atau area H1 invalid. | EXPIRED/INVALIDATED; delayed notification dibatalkan atau dikirim sebagai invalidation, bukan setup aktif. |
| SET-08 / integration | FR-13, FR-18 | Ganti active strategy version saat setup versi lama masih ada. | Versi lama tetap dapat diaudit; setup baru memakai versi baru; tidak mencampur evidence tanpa re-evaluation. |

### Narrative AI & publication guard

| ID / layer | Requirement | Skenario | Expected result |
| --- | --- | --- | --- |
| AI-01 / contract/integration | FR-14 | Provider mengembalikan valid report fixture. | Local JSON Schema pass; snapshot/evidence/scenario subset pass; harga tampil melalui hydration backend. |
| AI-02 / integration | FR-14, FR-22 | Model menambah entry_price atau evidence ID yang tidak ada. | Reject output; template fallback; audit validation reason; tidak ditampilkan sebagai valid AI report. |
| AI-03 / integration | FR-14 | Summary menyisipkan level numerik baru atau instruksi override WAIT. | Price-like text/stance guard menolak; teks AI tidak mengubah setup deterministic. |
| AI-04 / integration | FR-14, FR-05 | Input awal LIVE, tetapi feed stale sebelum respons AI selesai. | Publication revalidates freshness; report tidak dipublikasikan sebagai live actionable analysis. |
| AI-05 / resilience | FR-14, FR-21 | Provider refusal, schema invalid, response truncated, 429 atau timeout. | No successful run palsu; bounded retry hanya gangguan sementara; fallback; chart/engine tetap jalan. |
| AI-06 / integration | FR-15 | Dua worker meminta brief untuk snapshot/prompt/model/type yang sama. | Satu logical run; response reuse; usage/budget tidak didobel oleh duplicate scheduling. |
| AI-07 / integration | FR-15, FR-18 | Daily call cap, configured budget atau manual refresh limit tercapai. | Call baru ditahan; budget reason tampil; technical alerts tidak dinonaktifkan. |
| AI-08 / security/evaluation | FR-14, FR-22 | Snapshot tanpa news; masukkan label bebas berisi instruksi mengambil secret atau membuat order. | NEWS_NOT_CONNECTED tetap ada; data tidak dianggap system instruction; tool order/shell memang tidak tersedia. |

### Notifications & scheduling

| ID / layer | Requirement | Skenario | Expected result |
| --- | --- | --- | --- |
| ALERT-01 / integration | FR-16 | Event yang sama dikonsumsi tiga kali oleh workers. | Satu logical alert per channel/recipient; unique dedupe key menahan duplikasi. |
| ALERT-02 / resilience | FR-16 | Telegram timeout setelah request terkirim. | Delivery status unknown, attempt terekam; tidak mengklaim exactly-once; retry policy bounded dengan short event ID. |
| ALERT-03 / integration | FR-16 | Invalidation terjadi ketika cooldown touch/READY aktif. | Invalidation penting tidak ditahan cooldown generik. |
| ALERT-04 / integration | FR-16, FR-19 | Backfill/replay menemukan setup lama. | Historical events disimpan, tetapi tidak ada fresh Telegram trading alert. |
| ALERT-05 / scheduler integration | FR-15 | Jalankan scheduler dua instance untuk 07:00 WIB; uji worker restart. | Satu report per scheduled slot; overlap lock dan idempotency menangani retry; UTC conversion benar. |
| ALERT-06 / integration | FR-16, FR-21 | Redis unavailable saat DB transaction menyimpan event. | DB outbox tetap ada; sweep setelah recovery mengirim satu logical event; tidak kehilangan alert permanen. |

### Dashboard & realtime

| ID / layer | Requirement | Skenario | Expected result |
| --- | --- | --- | --- |
| UI-01 / playwright | FR-01, FR-06 | Owner login, buka Overview dan chart, pindah M5/M15/H1/H4/D1. | Feed konsisten; candle/timestamp benar; tidak ada refresh yang menghapus session; loading state jelas. |
| UI-02 / component/playwright | FR-07 | Tampilkan zona H1 pada M5, zoom/pan/resize, klik zona. | Bounds harga/waktu stabil, label H1 tetap; detail menampilkan origin dan available time/evidence. |
| UI-03 / playwright | FR-05, FR-06 | Ubah feed LIVE -> STALE -> DISCONNECTED -> recovered. | Badge dan timestamp berubah; last price tidak ditampilkan seolah masih live; setup baru terblokir. |
| UI-04 / integration/playwright | FR-06, FR-17 | Websocket terputus; kirim event_seq gap lalu event out-of-order. | Refetch snapshot pada reconnect/gap; event lama diabaikan; polling fallback diberi label. |
| UI-05 / playwright | FR-14, FR-17 | Buka AI report lalu detail evidence dari report historis setelah zona current berubah. | Evidence mengacu snapshot saat report, bukan diganti current zone; AI-generated/template dibedakan. |
| UI-06 / playwright | FR-18 | Owner menyimpan invalid settings, lalu nilai valid; viewer mencoba endpoint sama. | Invalid fields terlihat; valid save membuat version/audit; viewer mendapat 403. |
| UI-07 / accessibility/playwright | FR-06, FR-07 | Viewport 390x844 dan 1440x900; navigasi keyboard, dark/light, focus dialog. | Tidak ada kontrol kritis terpotong; status tidak bergantung warna; detail zona dapat dibaca tanpa canvas. |
| UI-08 / performance | FR-06, FR-21 | Muat 1.000 candles/100 zones dan 5 user pada hardware benchmark yang dicatat. | Target PRD diukur, bukan diasumsikan; no unbounded subscriptions/memory setelah 20 TF switches. |

### Authentication & security

| ID / layer | Requirement | Skenario | Expected result |
| --- | --- | --- | --- |
| SEC-01 / feature | FR-01 | Anonymous membuka overview, reports, raw candles dan websocket private channel. | 401/redirect login sesuai jalur; tidak ada data privat bocor. |
| SEC-02 / feature | FR-01, FR-18 | Mutasi via browser tanpa/invalid CSRF; viewer mencoba settings dan backtest. | 419/CSRF rejection; viewer 403; data tidak berubah. |
| SEC-03 / feature | FR-02, FR-03, FR-22 | Collector token feed A mengirim feed B; token revoked; oversize batch. | 403,401,413 sesuai kasus; token tidak memberi akses settings/AI/broker. |
| SEC-04 / static/integration | FR-22 | Cari order_send, order endpoints, execution flag dan broker write imports pada production graph. | Tidak ada jalur eksekusi; exception hanya test strings/documentation; dependency allowlist diperiksa. |
| SEC-05 / security | FR-14, FR-22 | Periksa rendered HTML, JS bundle, network AI payload, logs, error traces, repository. | Tidak ada broker secret, API key, account number, balance atau .env; error production redacted. |
| SEC-06 / network | FR-21, FR-22 | Dari internet/LAN/unauthorized tailnet device, coba DB, Redis, engine dan raw Reverb ports. | Tidak dapat diakses sesuai policy; authorized app path tetap bekerja; app login tetap wajib. |
| SEC-07 / feature/security | FR-01, FR-22 | User memilih feed/entity ID bukan miliknya; chat webhook URL arbitrary; unauthorized origin. | Policy dan allowlist menolak; tidak ada IDOR/SSRF; Reverb origin tidak wildcard. |
| SEC-08 / ops/security | FR-21, FR-22 | Jalankan destructive dev command dengan target production config; rotate collector token. | Environment guard menolak target salah; approval/backup diperlukan; token lama tidak dapat ingest. |

### Replay, paper simulation & operations

| ID / layer | Requirement | Skenario | Expected result |
| --- | --- | --- | --- |
| OPS-01 / replay | FR-19, FR-17 | Dataset dan config identik dijalankan full dan chunked dengan restart di tengah. | Event hash dan keputusan identik; no duplicate outcome; checkpoint idempotent. |
| OPS-02 / replay | FR-19 | Replay prefix sebelum right pivot bars, lalu tambahkan data/revisi yang datang sesudah cutoff. | Future pivot/revision tidak terlihat; mode reconstruction diberi label asumsi receipt time. |
| OPS-03 / simulation | FR-20 | READY pada candle close; next quote lebih buruk dan RR turun di bawah 2. | Tidak fill pada signal close; ulang RR pada executable quote; skip/reject sesuai rule, bukan isi harga lama. |
| OPS-04 / simulation | FR-20 | SL dan TP tersentuh dalam satu candle; tidak ada data intrabar. | Outcome ambiguous; conservative summary stop-first; best/worst outcome dan count ditampilkan. |
| OPS-05 / simulation | FR-20 | Buy/sell bid-ask, spread, slippage, commission, gap stop; uji no-loss/no-trade dataset. | Biaya tidak double counted; gap memakai executable adverse price; undefined metrics N/A, bukan infinity/fake zero. |
| OPS-06 / evaluation | FR-20 | Fit parameter pada development/validation lalu evaluasi untouched chronological holdout. | Tidak ada test-period tuning; run menyimpan dataset/strategy/code hashes dan warmup policy. |
| OPS-07 / resilience | FR-21 | Restart Postgres/Redis/engine/web/collector satu per satu di staging. | Tidak ada corrupt state; health terlihat; outbox/spool recovery; live queue tidak tertahan backtest. |
| OPS-08 / restore drill | FR-21 | Restore backup terenkripsi di isolated host, tanpa akses production credentials. | Data/hash/snapshot/audit dapat dibaca; RPO/RTO dicatat; tidak mengirim ulang alert historis. |
| OPS-09 / ops | FR-21 | Disk melewati warning/critical threshold; spool penuh; backup age >24 jam. | Warning terlihat; backfill/backtest dihentikan prioritas rendah; no silent discard; failed backup terdeteksi. |
| OPS-10 / UAT | FR-21, FR-22 | Aktifkan pause AI/notifikasi/analysis masing-masing, lalu observasi minimal 20 sesi dengan konfigurasi frozen. | Switch terisolasi; chart/health benar; evidence quality dan error rate dicatat; tidak menyatakan profitabilitas dari durasi saja. |

## 4. Traceability requirement ke test

| Requirement | Test IDs |
| --- | --- |
| FR-01 | UI-01, SEC-01, SEC-02, SEC-07 |
| FR-02 | DATA-01, DATA-09, DATA-13, DATA-16, SEC-03 |
| FR-03 | DATA-01, DATA-02, DATA-03, DATA-04, DATA-05, DATA-10, DATA-12, DATA-14, DATA-15, SEC-03 |
| FR-04 | DATA-02, DATA-04, DATA-06, DATA-07, DATA-08, DATA-09, DATA-12, DATA-14 |
| FR-05 | DATA-08, DATA-10, DATA-11, DATA-16, AI-04, UI-03 |
| FR-06 | DATA-13, UI-01, UI-03, UI-04, UI-07, UI-08 |
| FR-07 | UI-02, UI-07 |
| FR-08 | DATA-05, ENG-01, ENG-02, ENG-03, ENG-04, ENG-05, ENG-06, ENG-07, ENG-08 |
| FR-09 | ENG-15, ENG-16 |
| FR-10 | ENG-13, ENG-14 |
| FR-11 | ENG-09, ENG-10, ENG-11, ENG-12 |
| FR-12 | ENG-12, ENG-17, ENG-18, ENG-19, ENG-20 |
| FR-13 | SET-01, SET-02, SET-03, SET-04, SET-05, SET-06, SET-07, SET-08 |
| FR-14 | AI-01, AI-02, AI-03, AI-04, AI-05, AI-08, UI-05, SEC-05 |
| FR-15 | AI-06, AI-07, ALERT-05 |
| FR-16 | ENG-18, SET-07, ALERT-01, ALERT-02, ALERT-03, ALERT-04, ALERT-06 |
| FR-17 | DATA-07, ENG-16, UI-04, UI-05, OPS-01 |
| FR-18 | SET-08, AI-07, UI-06, SEC-02 |
| FR-19 | ENG-03, ALERT-04, OPS-01, OPS-02 |
| FR-20 | SET-05, OPS-03, OPS-04, OPS-05, OPS-06 |
| FR-21 | DATA-11, DATA-15, AI-05, ALERT-06, UI-08, SEC-06, SEC-08, OPS-07, OPS-08, OPS-09, OPS-10 |
| FR-22 | AI-02, AI-08, SEC-03, SEC-04, SEC-05, SEC-06, SEC-07, SEC-08, OPS-10 |

## 5. Fixture dan oracle numerik

`fixtures/strategy-golden.json` menyimpan expected output tetap, bukan dihitung oleh fungsi produksi. Nilai 99-105 adalah unit sintetis untuk memudahkan pemeriksaan, bukan sinyal XAUUSD.

| Kasus | Input ringkas | Oracle |
| --- | --- | --- |
| Pivot terlambat | H=[101,102,105,102,101], L=R=2 | high index 2, confirmed index 4 |
| FVG bullish | A.high=101, C.low=103 | [101,103], buy, confirmed C |
| FVG bearish | A.low=103, C.high=101 | [101,103], sell, confirmed C |
| Min gap | tick=.01, gap=.02 | accepted; gap=.01 rejected |
| Bullish break | reference=105, buffer=.02 | close105.02 NONE; 105.03 BOS |
| Demand invalid | low=100, buffer=.02 | close99.98 false; 99.97 true |
| Net RR | entry100, SL98, TP106; costs .2/.1 | 5.8/2.1 |
| ATR | seed TR=2 x14, next TR4 | seed2; next15/7 |

Property tests berikut harus ditambahkan pada P2: future append tidak mengubah masa lalu yang sudah available; clone snapshot memberikan hash sama; harga tick-scaled tetap mengikuti rule yang sama; retry tidak menambah event; terminal lifecycle tidak revive; batch/chunk parity.

## 6. Test code yang disertakan

`tests/contracts/test_spec_contracts.py` berjalan sekarang untuk memvalidasi schema/fixture spesifikasi. Hasilnya tidak membuktikan backend, MT5, AI provider atau chart sudah bekerja.

`tests/engine/test_strategy_v01.py` berisi automated acceptance tests terhadap interface `xau_engine.contracts_v1` di PRD. File ini harus memakai implementasi produksi. Sebelum package dibuat, collection akan gagal dengan ModuleNotFoundError; itu expected untuk fase spesifikasi dan tidak boleh ditutup dengan skip/fake pass.

`tests/acceptance/hybrid_analyst.feature` adalah skenario BDD end-to-end. Step bindings/Playwright implementation dibuat dalam P3-P5. File .feature saja bukan browser test yang sudah dieksekusi.

## 7. Cara menjalankan

### 7.1 Memeriksa paket spesifikasi saat ini

```bash
python -m venv .venv-spec
source .venv-spec/bin/activate
python -m pip install -r requirements-spec-tests.txt
python -m pytest tests/contracts -q
```

### 7.2 Setelah implementasi engine tersedia

Kontrak layout: package berada di `services/analysis-engine/src/xau_engine` dan dipasang editable dari project metadata. Dependency project harus dikunci oleh Phase 0.

```bash
python -m pip install -e ./services/analysis-engine
python -m pytest tests/engine -q
```

### 7.3 CI aplikasi yang harus dibuat pada Phase 0

```bash
# Dari root Laravel setelah aplikasi dibootstrap
composer install --no-interaction --prefer-dist
npm ci
vendor/bin/pint --test
npm run lint
npm run typecheck
npm run test:unit -- --run
php artisan test
npm run build
npx playwright test
```

Package scripts `lint`, `typecheck`, `test:unit` adalah kontrak tooling yang wajib disediakan saat bootstrap, bukan file yang sudah ada pada paket PRD. Configure PHP coverage tool serta PostgreSQL/Redis test services sebelum mengaktifkan coverage gates.

## 8. Test reporting dan bug severity

Setiap run menyimpan commit SHA, strategy hash, dataset hash, environment, command, pass/fail/skipped counts, duration, dan artefak kegagalan. Browser failures menyimpan trace/screenshot; Python menyimpan failing fixture seed; backend menyimpan correlation IDs yang telah disanitasi.

Critical defect: future-data leakage, order execution path, credential leak, salah arah/level karena rounding, corrupt history, feed tercampur, atau stale setup diterbitkan sebagai valid. Block release. High: data recovery/alert penting gagal. Medium: fitur nonkritis/UX terganggu. Low: kosmetik tanpa mengubah interpretasi data.

## 9. UAT checklist owner

1. Login privat; pastikan feed/broker yang benar dan seluruh halaman custom shadcn, tanpa Filament.
2. Bandingkan beberapa candle dan zona dengan evidence, bukan hanya tampilan screenshot.
3. Putus collector; pastikan status stale/disconnected jelas dan READY baru berhenti.
4. Periksa report/alert lama setelah revisi data; evidence historis harus tetap sama.
5. Matikan AI; chart, technical engine, dan deterministic alerts tetap berguna.
6. Jalankan replay yang sama dua kali dan bandingkan hashes/results.
7. Restore backup pada isolated environment dan pastikan tidak ada pengiriman ulang alert.
8. Setujui rilis hanya setelah bukti test tersedia; tidak menyamakan hasil simulation dengan profit live.

## 10. Rujukan testing

Gunakan dokumentasi pytest, Vitest, Playwright, dan Laravel yang dicantumkan pada PRD. Rule strategi, fixture numerik, target coverage, serta release gates pada dokumen ini adalah keputusan proyek, bukan benchmark resmi vendor.
