#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
mkdir -p "$ROOT/public/assets/vendor/bootstrap" "$ROOT/public/assets/vendor/sweetalert2" "$ROOT/public/assets/vendor/datatables"
curl -fsSL https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css -o "$ROOT/public/assets/vendor/bootstrap/bootstrap.min.css"
curl -fsSL https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js -o "$ROOT/public/assets/vendor/bootstrap/bootstrap.bundle.min.js"
curl -fsSL https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js -o "$ROOT/public/assets/vendor/sweetalert2/sweetalert2.all.min.js"
curl -fsSL https://cdn.datatables.net/2.3.4/css/dataTables.dataTables.min.css -o "$ROOT/public/assets/vendor/datatables/datatables.min.css"
curl -fsSL https://cdn.datatables.net/2.3.4/js/dataTables.min.js -o "$ROOT/public/assets/vendor/datatables/datatables.min.js"
echo "Frontend assets installed locally. Runtime internet access is not required."
