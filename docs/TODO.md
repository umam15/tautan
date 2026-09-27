# TODO / Optimization Backlog

> Prioritas: P0 = keamanan/correctness kritis, P1 = penting, P2 = optimasi/maintainability berikutnya.
> Status: `[ ]` belum dikerjakan, `[x]` selesai, `[-]` ditunda sampai ada data/benchmark.

## P0 — Security & Correctness
- [ ] **P0** Harden outbound requests jika fitur pengambilan metadata/favicons eksternal diaktifkan kembali.
- [ ] **P0** Terapkan CSRF protection pada seluruh operasi state-changing (CRUD, reorder, import, backup/restore, pengaturan).
- [ ] **P0** Audit dan perketat validasi URL bookmark; hanya `http://` dan `https://`, dengan normalisasi yang konsisten.
- [ ] **P0** Audit lifecycle password/session: logout, session invalidation, dan perubahan password.

## P1 — Performance & Reliability
- [ ] **P1** Tambahkan security headers (CSP, X-Content-Type-Options, Referrer-Policy, Permissions-Policy; HSTS hanya untuk deployment HTTPS).
- [ ] **P1** Jadikan backup atomic: temporary file → flush/sync yang sesuai → rename.
- [ ] **P1** Tambahkan `php scripts/check-db.php` untuk `PRAGMA integrity_check` dan validasi schema version.
- [ ] **P1** Tambahkan offline smoke test dengan network diblokir; pengecualian hanya icon bookmark eksternal.

- [x] **P1** Enable SQLite WAL + `busy_timeout` setelah memastikan deployment memakai local storage.
- [x] **P1** Pindahkan schema checks/migrations sepenuhnya dari request path ke migration/setup flow. Migration dijalankan eksplisit dengan `php scripts/migrate.php` saat deployment/setup.
- [x] **P1** Bungkus import bookmark dalam satu transaction.
- [x] **P1** Harden session cookie configuration.
- [x] **P1** Tambahkan login throttling/backoff.
- [x] **P1** Perbaiki error handling backup/restore agar detail exception tidak bocor.
- [x] **P1** Tambahkan batas ukuran backup secara eksplisit.

## P2 — Maintainability & Scale
- [x] **P2** Centralize validation dan resource limits.
- [x] **P2** Tambahkan automated PHP syntax/static checks di CI.
- [x] **P2** Tambahkan regression tests authorization dan import/export.
- [-] **P2** Split `includes/functions.php` berdasarkan domain — ditahan: fungsi global masih menjadi API internal lintas halaman; perlu test coverage lebih luas sebelum refactor struktural.
- [-] **P2** Normalize tags — hanya jika dataset/search menjadi bottleneck terukur.
- [-] **P2** SQLite FTS5 — hanya jika search menjadi bottleneck terukur.
- [-] **P2** Tambah index baru — hanya berdasarkan query profiling.

## Performance Policy
- Measure → change → test → compare.
- Jangan menambah dependency untuk optimasi kecil.
- Asset aplikasi harus tetap local/offline-first.
- Asset/data bookmark milik user boleh berasal dari internet.
- Jangan melakukan architectural rewrite tanpa bottleneck terukur.
