#!/usr/bin/env bash
#
# waffle-commons/runtime — Memory-drift audit wrapper (Igor-PHP)
#
# Igor-PHP is an ultra-fast Go static linter purpose-built for FrankenPHP worker
# mode. This wrapper enforces the runtime's ΔM = 0 invariant (no state bleed
# between requests) by flagging persistent state mutation, incomplete reset()
# handlers, and dangerous global/superglobal access.
#
# It mirrors `composer mago`: the linter is expected either as the Composer
# dev-dependency (vendor/bin/igor-php, installed via
# `composer require --dev igor-php/igor-php`) or as a binary on PATH
# (`go install github.com/igor-php/igor-php@latest`). Run it inside the
# `waffle-dev` container, from anywhere — paths resolve relative to the script:
#
#     ./bin/run-igor.sh
#
# Exit codes: 0 = no findings (ΔM = 0 holds); 1 = findings or setup error.

set -euo pipefail

# --- ANSI colors for local developer feedback ------------------------------
readonly COLOR_INFO=$'\033[0;34m'     # Blue   — progress
readonly COLOR_SUCCESS=$'\033[0;32m'  # Green  — pass
readonly COLOR_WARN=$'\033[1;33m'     # Yellow — non-fatal notice
readonly COLOR_ERROR=$'\033[0;31m'    # Red    — failure
readonly COLOR_RESET=$'\033[0m'

log_info()    { printf '%s[*]%s %s\n'   "${COLOR_INFO}"    "${COLOR_RESET}" "$1"; }
log_success() { printf '%s[OK]%s %s\n'  "${COLOR_SUCCESS}" "${COLOR_RESET}" "$1"; }
log_warn()    { printf '%s[!]%s %s\n'   "${COLOR_WARN}"    "${COLOR_RESET}" "$1"; }
log_error()   { printf '%s[ERR]%s %s\n' "${COLOR_ERROR}"   "${COLOR_RESET}" "$1" >&2; }

# --- Resolve paths relative to this script, not the caller's CWD -----------
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
readonly SCRIPT_DIR
COMPONENT_ROOT="$(cd -- "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
readonly COMPONENT_ROOT
readonly CONFIG_FILE="${COMPONENT_ROOT}/igor.json"

log_info "Starting memory-drift audit (Igor-PHP) — runtime component…"

# --- The igor.json config must exist ---------------------------------------
if [[ ! -f "${CONFIG_FILE}" ]]; then
    log_error "Configuration file not found: ${CONFIG_FILE}"
    log_error "Generate one with 'vendor/bin/igor-php init', or restore igor.json."
    exit 1
fi

# --- Locate the Igor binary, preferring the Composer dev-dependency --------
IGOR_BIN=""
if [[ -x "${COMPONENT_ROOT}/vendor/bin/igor-php" ]]; then
    IGOR_BIN="${COMPONENT_ROOT}/vendor/bin/igor-php"
    log_info "Using Composer dev-dependency: vendor/bin/igor-php"
elif command -v igor-php >/dev/null 2>&1; then
    IGOR_BIN="$(command -v igor-php)"
    log_info "Using Igor-PHP binary on PATH: ${IGOR_BIN}"
else
    log_error "Igor-PHP is not installed."
    log_error "Install it as a dev-dependency (inside the waffle-dev container):"
    log_error "    composer require --dev igor-php/igor-php"
    log_error "…or place the Go binary on PATH:"
    log_error "    go install github.com/igor-php/igor-php@latest"
    exit 1
fi
readonly IGOR_BIN

# --- Run the audit from the component root ---------------------------------
# `.` scans the component; igor.json's "exclude" skips vendor/, tests/, var/.
log_info "Auditing src/ for state mutation, incomplete resets, and global access…"

if (cd -- "${COMPONENT_ROOT}" && "${IGOR_BIN}" --config "${CONFIG_FILE}" .); then
    log_success "Audit passed — ΔM = 0 invariant holds. No memory drift detected."
    exit 0
else
    log_error "Igor-PHP reported findings — persistent state or incomplete reset detected."
    log_error "See ./igor_local_setup.md for the resolution patterns (no baselines)."
    exit 1
fi
