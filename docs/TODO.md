# TODO / Optimization Backlog

> Prioritas: P0 = keamanan/correctness kritis, P1 = penting, P2 = optimasi/maintainability berikutnya.
> Status: `[ ]` belum dikerjakan, `[x]` selesai, `[-]` ditunda sampai ada data/benchmark.

## P0 — Security & Correctness
- [x] **P0** Enforce link ownership pada edit/update.
- [x] **P0** Enforce link ownership pada delete.
- [x] **P0** Validate reorder IDs dan ownership.
- [x] **P0** Remove remote favicon service dependency; favicon aplikasi memakai fallback lokal.
- [x] **P0** Remove frontend CDN dependency; aplikasi tidak bergantung pada CDN untuk berjalan.
- [ ] **P0** Harden outbound requests jika fitur pengambilan metadata/favicons eksternal diaktifkan kembali.

## P1 — Performance & Reliability
- [x] **P1** Enable SQLite WAL + `busy_timeout` setelah memastikan deployment memakai local storage.
- [ ] **P1** Pindahkan schema checks/migrations dari request path ke migration/setup flow.
- [x] **P1** Bungkus import bookmark dalam satu transaction.
- [x] **P1** Harden session cookie configuration.
- [ ] **P1** Tambahkan login throttling/backoff.
- [ ] **P1** Perbaiki error handling backup/restore agar detail exception tidak bocor.
- [ ] **P1** Tambahkan batas ukuran backup secara eksplisit.

## P2 — Maintainability & Scale
- [ ] **P2** Centralize validation dan resource limits.
- [ ] **P2** Tambahkan automated PHP syntax/static checks di CI.
- [ ] **P2** Tambahkan regression tests authorization dan import/export.
- [ ] **P2** Split `includes/functions.php` berdasarkan domain.
- [-] **P2** Normalize tags — hanya jika dataset/search menjadi bottleneck terukur.
- [-] **P2** SQLite FTS5 — hanya jika search menjadi bottleneck terukur.
- [-] **P2** Tambah index baru — hanya berdasarkan query profiling.

## Performance Policy
- Measure → change → test → compare.
- Jangan menambah dependency untuk optimasi kecil.
- Asset aplikasi harus tetap local/offline-first.
- Asset/data bookmark milik user boleh berasal dari internet.
- Jangan melakukan architectural rewrite tanpa bottleneck terukur.
