#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d)"
RUN_ID="$(date +%s)-$RANDOM"
NETWORK="agent-readiness-zip-${RUN_ID}"
DB_CONTAINER="agent-readiness-zip-db-${RUN_ID}"
WEB_CONTAINER="agent-readiness-zip-web-${RUN_ID}"
WP_VOLUME="agent-readiness-zip-wp-${RUN_ID}"
DB_VOLUME="agent-readiness-zip-db-${RUN_ID}"
PORT="${AGENT_READINESS_ZIP_TEST_PORT:-${WP_AGENTIC_ZIP_TEST_PORT:-8090}}"

cleanup() {
  docker rm -f "${WEB_CONTAINER}" "${DB_CONTAINER}" >/dev/null 2>&1 || true
  docker network rm "${NETWORK}" >/dev/null 2>&1 || true
  docker volume rm "${WP_VOLUME}" "${DB_VOLUME}" >/dev/null 2>&1 || true
  rm -rf "${TMP_DIR}"
}

trap cleanup EXIT

"${ROOT_DIR}/tools/build-zip.sh" >/dev/null
unzip -q "${ROOT_DIR}/dist/agent-readiness.zip" -d "${TMP_DIR}"

docker network create "${NETWORK}" >/dev/null
docker volume create "${WP_VOLUME}" >/dev/null
docker volume create "${DB_VOLUME}" >/dev/null

docker run -d \
  --name "${DB_CONTAINER}" \
  --network "${NETWORK}" \
  -e MYSQL_DATABASE=wordpress \
  -e MYSQL_USER=wordpress \
  -e MYSQL_PASSWORD=wordpress \
  -e MYSQL_ROOT_PASSWORD=wordpress \
  -v "${DB_VOLUME}:/var/lib/mysql" \
  mysql:8.4 >/dev/null

for _ in $(seq 1 60); do
  if docker exec "${DB_CONTAINER}" mysqladmin ping -h localhost -pwordpress --silent >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

docker run -d \
  --name "${WEB_CONTAINER}" \
  --network "${NETWORK}" \
  -p "${PORT}:80" \
  -e WORDPRESS_DB_HOST="${DB_CONTAINER}:3306" \
  -e WORDPRESS_DB_USER=wordpress \
  -e WORDPRESS_DB_PASSWORD=wordpress \
  -e WORDPRESS_DB_NAME=wordpress \
  -v "${WP_VOLUME}:/var/www/html" \
  -v "${TMP_DIR}/agent-readiness:/var/www/html/wp-content/plugins/agent-readiness:ro" \
  wordpress:6.8-php8.2-apache >/dev/null

for _ in $(seq 1 60); do
  if curl -fsS "http://localhost:${PORT}/wp-admin/install.php" >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

wp_cli() {
  docker run --rm \
    --network "${NETWORK}" \
    -e WORDPRESS_DB_HOST="${DB_CONTAINER}:3306" \
    -e WORDPRESS_DB_USER=wordpress \
    -e WORDPRESS_DB_PASSWORD=wordpress \
    -e WORDPRESS_DB_NAME=wordpress \
    -v "${WP_VOLUME}:/var/www/html" \
    -v "${TMP_DIR}/agent-readiness:/var/www/html/wp-content/plugins/agent-readiness:ro" \
    --workdir /var/www/html \
    wordpress:cli-php8.2 \
    "$@" --allow-root
}

wp_cli wp core install \
  --url="http://localhost:${PORT}" \
  --title='Agent Readiness ZIP Test' \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.com \
  --skip-email

wp_cli wp plugin activate agent-readiness
wp_cli wp rewrite structure '/%postname%/' --hard

curl -fsS "http://localhost:${PORT}/" >/dev/null
curl -fsS -H 'Accept: text/markdown' "http://localhost:${PORT}/" | grep -q '^# Agent Readiness ZIP Test'
curl -fsS "http://localhost:${PORT}/robots.txt" | grep -q 'Content-Signal: ai-train=yes, search=yes, ai-input=yes'
curl -fsS "http://localhost:${PORT}/llms.txt" | grep -q '## Agent resources'
curl -fsS "http://localhost:${PORT}/.well-known/api-catalog" | php -r 'json_decode(stream_get_contents(STDIN), true); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);'
curl -fsS "http://localhost:${PORT}/.well-known/agent-skills/index.json" | grep -q 'schemas.agentskills.io/discovery/0.2.0'
curl -fsS "http://localhost:${PORT}/.well-known/agent-skills/search-site/SKILL.md" | grep -q 'name: search-site'
curl -fsS "http://localhost:${PORT}/wp-json/agent-readiness/v1/context" | grep -q 'agent_resources'
curl -fsS "http://localhost:${PORT}/" | grep -q 'agent-readiness-webmcp'

echo "ZIP install smoke test passed on http://localhost:${PORT}"
