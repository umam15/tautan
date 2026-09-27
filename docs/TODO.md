# TODO / Optimization Backlog

> Prioritas: P0 = keamanan/correctness kritis, P1 = penting, P2 = optimasi/maintainability berikutnya.
> Status: `[ ]` belum dikerjakan, `[x]` selesai, `[-]` ditunda sampai ada data/benchmark.

## P0 — Security & Correctness
- [ ] **P0** Harden outbound requests jika fitur pengambilan metadata/favicons eksternal diaktifkan kembali.
- [ ] **P0** Audit lifecycle password/session: logout, session invalidation, dan perubahan password.

## P1 — Performance & Reliability
- [ ] **P1** Jadikan backup atomic: temporary file → flush/sync yang sesuai → rename.
- [ ] **P1** Tambahkan `php scripts/check-db.php` untuk `PRAGMA integrity_check` dan validasi schema version.
- [ ] **P1** Tambahkan offline smoke test dengan network diblokir; pengecualian hanya icon bookmark eksternal.

## P2 — Maintainability & Scale
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
