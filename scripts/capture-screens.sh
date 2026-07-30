#!/usr/bin/env bash
#
# Captures the screenshots the README shows, from a running storefront.
#
#   bash scripts/capture-screens.sh [base-url] [output-dir]
#
# Defaults to http://localhost:8080 and docs/screenshots, which is what a local
# stack from scripts/reset-local.ps1 answers on.
#
# The committed captures would otherwise go stale in silence: someone changes
# the type scale, the README keeps showing last month's storefront, and nothing
# says so. CI runs this against the disposable smoke stack and uploads the
# result as a build artifact, so the current truth is always one download away
# without a pixel-diff gate that fails builds over font rendering.
#
# The checkout capture needs a cart with something in it, or WooCommerce
# redirects to the cart page. A throwaway browser profile carries that session
# between the warm-up request and the captures that need it.
set -Eeuo pipefail

base_url="${1:-http://localhost:8080}"
saida="${2:-docs/screenshots}"

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"
mkdir -p "$saida"

navegador=""
for candidato in "${CHROME_BIN:-}" google-chrome chromium chromium-browser \
  "/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe" \
  "/c/Program Files/Google/Chrome/Application/chrome.exe"; do
  [[ -z "$candidato" ]] && continue
  if command -v "$candidato" >/dev/null 2>&1 || [[ -x "$candidato" ]]; then
    navegador="$candidato"
    break
  fi
done

if [[ -z "$navegador" ]]; then
  printf 'No headless Chrome, Chromium or Edge found. Set CHROME_BIN to one.\n' >&2
  exit 1
fi

perfil="$(mktemp -d)"
trap 'rm -rf -- "$perfil"' EXIT

disparar() {
  "$navegador" --headless=new --disable-gpu --no-sandbox --hide-scrollbars \
    --force-device-scale-factor=1 "$@" >/dev/null 2>&1
}

produto_id="$(curl --fail --silent --show-error --max-time 20 \
  "$base_url/wp-json/wc/store/v1/products?slug=vale-fluted-vase" \
  | node -e 'const p=JSON.parse(require("node:fs").readFileSync(0,"utf8")); process.stdout.write(String(p[0]?.id ?? ""))')"

if [[ -z "$produto_id" ]]; then
  printf 'Could not find the sample product at %s. Has the seed run?\n' "$base_url" >&2
  exit 1
fi

# Warm-up: puts an item in the cart so checkout renders instead of redirecting.
disparar --user-data-dir="$perfil" --window-size=1280,900 \
  --screenshot="$perfil/warmup.png" "$base_url/?add-to-cart=$produto_id"

# nome | caminho | janela | usa a sessao com carrinho
capturas=(
  "shop|/shop/|1280,1400|sim"
  "product|/product/low-vessel/|1280,1250|sim"
  "checkout|/checkout/|1280,1500|sim"
  "cart-empty|/cart/|1280,1250|nao"
  "mobile|/shop/|390,1150|sim"
)

for linha in "${capturas[@]}"; do
  IFS='|' read -r nome caminho janela sessao <<<"$linha"
  destino="$saida/$nome.png"
  if [[ "$sessao" == "sim" ]]; then
    disparar --user-data-dir="$perfil" --window-size="$janela" --screenshot="$destino" "$base_url$caminho"
  else
    disparar --window-size="$janela" --screenshot="$destino" "$base_url$caminho"
  fi
  if [[ ! -s "$destino" ]]; then
    printf 'Capture failed for %s.\n' "$caminho" >&2
    exit 1
  fi
  printf '  %-12s %s\n' "$nome" "$(wc -c <"$destino" | tr -d ' ') bytes"
done

printf 'Captured %d screens from %s into %s.\n' "${#capturas[@]}" "$base_url" "$saida"
