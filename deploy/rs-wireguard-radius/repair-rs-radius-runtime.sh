#!/usr/bin/env bash
set -Eeuo pipefail
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
echo "The dedicated /etc/freeradius-rs repair path was a regression from the proven v6 architecture."
echo "Restoring the v6-compatible system FreeRADIUS runtime instead."
exec "$SCRIPT_DIR/restore-v6-radius-runtime.sh" "$@"
