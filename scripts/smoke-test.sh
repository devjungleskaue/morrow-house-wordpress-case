#!/usr/bin/env bash
set -Eeuo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

run_token="${GITHUB_RUN_ID:-local}-$(date +%s)-$$"
SMOKE_PROJECT="morrow-house-smoke-${run_token}"
if [[ ! "$SMOKE_PROJECT" =~ ^morrow-house-smoke-[a-z0-9][a-z0-9_-]*$ ]]; then
  printf 'Refusing unsafe Compose project name: %s\n' "$SMOKE_PROJECT" >&2
  exit 2
fi

export WORDPRESS_PORT=0
export WORDPRESS_DB_NAME=morrow_smoke
export WORDPRESS_DB_USER=morrow_smoke
export WORDPRESS_DB_PASSWORD=morrow_smoke_local
export WORDPRESS_DB_ROOT_PASSWORD=morrow_smoke_root_local

cookie_jar=""

compose() {
  # Git Bash otherwise rewrites container paths such as /project/tests before Docker sees them.
  MSYS_NO_PATHCONV=1 docker compose --project-name "$SMOKE_PROJECT" --env-file .env.example "$@"
}

cleanup() {
  status=$?
  trap - EXIT INT TERM

  if ! compose down --volumes --remove-orphans; then
    printf 'Smoke cleanup command failed for %s.\n' "$SMOKE_PROJECT" >&2
    status=1
  fi

  remaining_containers="$(docker ps -aq --filter "label=com.docker.compose.project=$SMOKE_PROJECT")"
  remaining_volumes="$(docker volume ls -q --filter "label=com.docker.compose.project=$SMOKE_PROJECT")"
  if [[ -n "$remaining_containers" || -n "$remaining_volumes" ]]; then
    printf 'Smoke cleanup left Docker resources in %s.\n' "$SMOKE_PROJECT" >&2
    status=1
  else
    printf 'Smoke cleanup verified for %s.\n' "$SMOKE_PROJECT"
  fi

  if [[ -n "$cookie_jar" ]]; then
    rm -f -- "$cookie_jar"
  fi

  exit "$status"
}

trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

for command in docker curl node; do
  command -v "$command" >/dev/null || { printf 'Required command not found: %s\n' "$command" >&2; exit 2; }
done

docker info >/dev/null
compose config --quiet
compose up -d db wordpress

binding=""
for _attempt in $(seq 1 30); do
  binding="$(compose port wordpress 80 2>/dev/null | tail -n 1 || true)"
  [[ -n "$binding" ]] && break
  sleep 1
done
if [[ -z "$binding" ]]; then
  printf 'Docker did not publish the disposable WordPress port.\n' >&2
  exit 1
fi

wordpress_port="${binding##*:}"
if [[ ! "$wordpress_port" =~ ^[0-9]{1,5}$ ]] || (( wordpress_port < 1 || wordpress_port > 65535 )); then
  printf 'Docker returned an invalid WordPress port: %s\n' "$binding" >&2
  exit 1
fi
local_url="http://127.0.0.1:$wordpress_port"

ready=false
for _attempt in $(seq 1 90); do
  if curl --fail --silent --show-error --max-time 3 "$local_url/wp-admin/install.php" >/dev/null; then
    ready=true
    break
  fi
  sleep 2
done
if [[ "$ready" != true ]]; then
  compose logs wordpress db >&2
  printf 'WordPress did not become ready at %s.\n' "$local_url" >&2
  exit 1
fi

compose run --rm cli wp core install \
  --url="$local_url" \
  --title='Morrow House' \
  --admin_user='morrow_smoke_admin' \
  --admin_password='morrow_smoke_admin_local' \
  --admin_email='admin@morrowhouse.example' \
  --skip-email
compose run --rm cli wp plugin install 'https://downloads.wordpress.org/plugin/woocommerce.10.9.4.zip' --activate
compose run --rm cli wp plugin install 'https://downloads.wordpress.org/plugin/elementor.4.2.1.zip' --activate
compose run --rm cli wp theme activate morrow-house
compose run --rm cli wp plugin activate morrow-house-core
compose run --rm cli wp eval-file /project/tests/runtime-smoke.php

campaign_html="$(curl --fail --silent --show-error --max-time 20 "$local_url/campaign/")"
printf '%s' "$campaign_html" | node -e '
const html = require("node:fs").readFileSync(0, "utf8");
const headings = html.match(/<h1\b/gi) ?? [];
if (headings.length !== 1) throw new Error(`Campaign rendered ${headings.length} h1 elements; expected one.`);
if (!html.includes("Start with a clear surface and keep the objects you reach for close at hand.")) {
  const visibleText = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, " ").replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, " ").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
  throw new Error(`Campaign shopper copy did not render. Visible text: ${visibleText.slice(0, 500)}`);
}
'

product_id="$(compose run --rm cli wp eval "echo wc_get_product_id_by_sku('MH-VASE-VALE');")"
nonce="$(compose run --rm cli wp eval "echo wp_create_nonce('wc_store_api');")"
cookie_jar="$(mktemp)"
cart_json="$(curl --fail --silent --show-error --max-time 20 \
  --cookie "$cookie_jar" \
  --cookie-jar "$cookie_jar" \
  --header "Nonce: $nonce" \
  --request POST \
  "$local_url/wp-json/wc/store/v1/cart/add-item?id=$product_id&quantity=1")"
printf '%s' "$cart_json" | node -e '
const cart = JSON.parse(require("node:fs").readFileSync(0, "utf8"));
if (cart.items_count !== 1) throw new Error(`Store API cart count was ${cart.items_count}; expected one.`);
if (!Array.isArray(cart.payment_methods) || cart.payment_methods.length !== 0) throw new Error("Store API exposed a usable payment method.");
'

checkout_html="$(curl --fail --location --silent --show-error --max-time 20 --cookie "$cookie_jar" "$local_url/checkout/")"
printf '%s' "$checkout_html" | node -e '
const html = require("node:fs").readFileSync(0, "utf8");
if (!html.includes("mh-checkout-disclosure") || !html.includes("Payment methods are intentionally unavailable.")) {
  const visibleText = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, " ").replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, " ").replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
  throw new Error(`Checkout Block disclosure did not render. Visible text: ${visibleText.slice(0, 500)}`);
}
'


# Accessibility contract, on every page a shopper can reach.
#
# Static checks against real markup, not a full audit: they catch the failures
# that survive a refactor unnoticed, like a landmark disappearing when someone
# rewrites the header, or the skip link losing the target it points at. A
# browser-driven audit would catch more and needs a browser; this needs curl.
#
# It used to run on /shop/ alone, which is how the 404 page shipped with no
# heading at all. The pages below cover the shopping path plus the two states a
# visitor reaches by accident, and the exit code is checked per page so the
# failure message says which one broke.
for mh_path in "/shop/" "/product/vale-fluted-vase/" "/cart/" "/checkout/" "/about/" "/contact/" "/" "/no-such-page/" "/?s=zzzznothing&post_type=product"; do
printf 'Accessibility contract: %s\n' "$mh_path"
# The leading slash is stripped out of MH_PATH and put back in the message
# below: Git Bash on Windows rewrites an environment value that starts with a
# slash into a Windows path, which turned the failure message into nonsense.
curl --location --silent --show-error --max-time 20 "$local_url$mh_path" | MH_PATH="${mh_path#/}" node -e '
const html = require("node:fs").readFileSync(0, "utf8");
const falhas = [];

if (!/<h1[\s>]/i.test(html)) falhas.push("no <h1>");
if ((html.match(/<h1[\s>]/gi) ?? []).length > 1) falhas.push("more than one <h1>");

if (!/<html[^>]+lang=/i.test(html)) falhas.push("<html> has no lang attribute");

const skip = html.match(/<a[^>]+class="skip-link"[^>]+href="#([^"]+)"/i);
if (!skip) falhas.push("skip link is missing");
else if (!new RegExp("id=\"" + skip[1] + "\"").test(html)) falhas.push("skip link target #" + skip[1] + " does not exist");

for (const [nome, re] of [["main", /<main\b/i], ["header", /<header\b/i], ["nav", /<nav\b/i]]) {
  if (!re.test(html)) falhas.push("no <" + nome + "> landmark");
}

if (!/<nav[^>]+aria-label=/i.test(html)) falhas.push("<nav> has no accessible name");

const toggle = html.match(/<button[^>]*class="menu-toggle"[^>]*>/i);
if (!toggle) falhas.push("menu toggle is missing");
else {
  if (!/aria-expanded=/.test(toggle[0])) falhas.push("menu toggle has no aria-expanded");
  const controls = toggle[0].match(/aria-controls="([^"]+)"/);
  if (!controls) falhas.push("menu toggle has no aria-controls");
  else if (!new RegExp("id=\"" + controls[1] + "\"").test(html)) falhas.push("aria-controls target #" + controls[1] + " does not exist");
}

const semAlt = (html.match(/<img\b(?![^>]*\balt=)[^>]*>/gi) ?? []).length;
if (semAlt) falhas.push(semAlt + " <img> without alt");

if (falhas.length) {
  throw new Error("Accessibility contract failed on /" + process.env.MH_PATH + ":\n  - " + falhas.join("\n  - "));
}
'
done

compose run --rm cli wp eval-file /project/tests/runtime-menu-failure.php
printf 'Runtime smoke passed at %s using project %s.\n' "$local_url" "$SMOKE_PROJECT"
