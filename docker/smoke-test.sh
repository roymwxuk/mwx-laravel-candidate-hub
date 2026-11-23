#!/usr/bin/env bash
# End-to-end check against `docker compose up`:
# log in as HR → upload the sample resume → queue worker calls the extractor → draft is ready.
set -euo pipefail

BASE="${BASE_URL:-http://127.0.0.1:8000}"
PDF="${SAMPLE_PDF:-extractor/tests/fixtures/sample_resume.pdf}"
JAR="$(mktemp)"
trap 'rm -f "$JAR" /tmp/smoke-page.html' EXIT

token() { grep -o 'name="_token" value="[^"]*"' /tmp/smoke-page.html | head -1 | sed 's/.*value="//; s/"$//'; }

echo "→ extractor health"
curl -sf http://127.0.0.1:8001/health

echo "→ login page"
curl -sf -c "$JAR" -b "$JAR" -o /tmp/smoke-page.html "$BASE/login"
grep -q 'Candidate Hub' /tmp/smoke-page.html

echo "→ log in as HR"
curl -sf -c "$JAR" -b "$JAR" -o /dev/null \
  --data-urlencode "_token=$(token)" \
  --data-urlencode "email=hr@example.com" \
  --data-urlencode "password=demopassword56" \
  "$BASE/login"

echo "→ upload sample resume"
curl -sf -c "$JAR" -b "$JAR" -o /tmp/smoke-page.html "$BASE/resumes/create"
LOCATION=$(curl -sf -c "$JAR" -b "$JAR" -o /dev/null -w '%{redirect_url}' \
  -F "_token=$(token)" \
  -F "resume=@${PDF};type=application/pdf" \
  "$BASE/resumes")
echo "   redirected to $LOCATION"
case "$LOCATION" in */resumes/[0-9]*) ;; *) echo "upload did not redirect to a resume page"; exit 1 ;; esac

echo "→ wait for the queued extraction"
for _ in $(seq 1 30); do
  STATUS=$(curl -sf -b "$JAR" -H 'Accept: application/json' "$LOCATION/status")
  echo "   $STATUS"
  case "$STATUS" in
    *'"extracted"'*) break ;;
    *'"failed"'*) curl -sf -b "$JAR" "$LOCATION" | grep -A2 '解析に失敗' || true; exit 1 ;;
  esac
  sleep 2
done
case "$STATUS" in *'"extracted"'*) ;; *) echo "extraction did not finish in time"; exit 1 ;; esac

echo "→ review screen shows the AI draft"
curl -sf -b "$JAR" -o /tmp/smoke-page.html "$LOCATION"
grep -q '架空 太郎' /tmp/smoke-page.html

echo "✓ end-to-end smoke test passed"
