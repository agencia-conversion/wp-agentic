#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT_DIR}/dist/build"
ZIP_PATH="${ROOT_DIR}/dist/agent-readiness.zip"

rm -rf "${BUILD_DIR}" "${ZIP_PATH}"
mkdir -p "${BUILD_DIR}/agent-readiness" "${ROOT_DIR}/dist"

rsync -a --delete \
  --exclude-from="${ROOT_DIR}/.distignore" \
  "${ROOT_DIR}/" \
  "${BUILD_DIR}/agent-readiness/"

(cd "${BUILD_DIR}" && zip -qr "${ZIP_PATH}" agent-readiness)

echo "${ZIP_PATH}"
