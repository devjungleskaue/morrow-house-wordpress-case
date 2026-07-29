# Morrow House — custom WooCommerce storefront

Morrow House is a conceptual reference build, not a client project. It demonstrates a reproducible WordPress, Elementor Free and WooCommerce delivery with no real payments or customer data.

The shop, its products and the `.example` contact address are invented and match no existing business.

## What this proves

The case covers a small home-goods catalogue, product details, search, cart, a Checkout Block and an editable campaign page. The exact tested stack is WordPress 7.0.2, PHP 8.3, WooCommerce 10.9.4, Elementor Free 4.2.1 and MariaDB 11.4.

The companion plugin removes every available WooCommerce payment gateway. A normal paragraph block above checkout states that payment methods are intentionally unavailable. Product material and care notes remain editable WooCommerce fields, and the Campaign heading, copy and link remain editable Elementor data.

## Launch the temporary store

[Launch Morrow House in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/devjungleskaue/morrow-house-wordpress-case/v1.0.1/blueprint.json)

The Blueprint installs the exact WooCommerce and Elementor ZIPs from WordPress.org, then reads the theme, companion plugin and seed from the `v1.0.1` repository tag. A Playground session is temporary; its store data disappears when the browser session ends.

The Blueprint defines `MORROW_HOUSE_IS_PLAYGROUND` before plugin activation. In Playground, that flag swaps one Elementor Library URL builder because WordPress's IDN encoder rejects the service's long temporary address. The Cloud Library module stays enabled. Docker keeps Elementor's default URL handling.

## Business brief

Morrow House is a fictional Toronto shop for small-batch lighting, vessels and trays. The seed creates three sample products and pages for Shop, Cart, Checkout, Campaign, About and Contact. Names, prices and the `.example` email address are demo content.

The storefront keeps the product journey inspectable without pretending to take orders. No real customer, order or payment data belongs in either the local stack or a temporary Playground session.

## Architecture

`wp-content/themes/morrow-house` owns the storefront shell, WooCommerce templates, styles and navigation behavior. Mobile navigation stays usable without JavaScript and collapses only after enhancement. WooCommerce cart fragments replace the full header cart link, while supported Blocks events request an authoritative refresh.

`wp-content/plugins/morrow-house-core` owns demo safeguards and product fields. It declares Cart and Checkout Block compatibility, removes payment gateways and renders the site-wide concept notice.

`scripts/seed.php` upserts pages, products, navigation and the Elementor campaign. Menu API errors stop the seed with context. The primary location is assigned only after every menu item succeeds, and rewrite rules flush once per seed schema version. `blueprint.json` and `docker-compose.yml` provide the browser and retained local setup around the same project code.

## Hard parts

Checkout remains a real WooCommerce Block flow without becoming a transaction surface. The integration smoke adds a priced product through the Store API, checks a cart count of one, confirms that `payment_methods` is empty and renders the disclosure above Checkout.

Repeatability is tested instead of assumed. The smoke boots the pinned stack, runs the seed twice, compares database state, verifies the rendered Campaign has one `h1`, injects a real WordPress menu-creation failure and checks its own Docker cleanup.

Version references:

- WordPress releases: https://wordpress.org/download/releases/
- WooCommerce: https://wordpress.org/plugins/woocommerce/
- Elementor: https://wordpress.org/plugins/elementor/
- Playground Blueprint format: https://wordpress.github.io/wordpress-playground/blueprints/data-format/

## Run locally

Requirements: Docker Desktop with Compose and Node.js 22.13 or newer.

```powershell
npm ci
npm test
.\scripts\reset-local.ps1
```

The reset copies `.env.example` to `.env` when needed, validates `WORDPRESS_PORT`, derives one local URL, installs the pinned plugins, activates the project code and runs the seed. With the example file, the store uses `http://localhost:8080`.

The reset removes volumes only from the `morrow-house-wordpress-case-local` Compose project before rebuilding it. That deletes every local WordPress and MariaDB record in that namespace. Do not run it when you need to keep that data.

## Validation

The fast suite checks repository contracts, including a fake-Docker PowerShell test that proves native command failures stop the reset without starting services. The integration smoke uses a separate `morrow-house-smoke-*` Compose project and an available port.

```powershell
npm test
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tests\reset-local.test.ps1
```

Run the integration smoke from Ubuntu, macOS or Git Bash:

```bash
bash scripts/smoke-test.sh
```

Additional local checks:

```powershell
docker run --rm -v "${PWD}:/work:ro" -w /work php:8.3-cli sh -lc "find wp-content scripts tests -name '*.php' -print0 | xargs -0 -n1 php -l"
docker compose --env-file .env.example config --quiet
git diff --check
```

GitHub Actions is configured to run the PowerShell reset contract on Windows and the fast tests, PHP lint, disposable integration smoke and disclosure scanner on Ubuntu. No remote result or badge is claimed here.

## Accessibility, SEO and performance

The theme has a skip link, labelled primary navigation, a menu button with state attributes and a live cart count. Campaign renders one main heading. WordPress supplies title tags, and theme assets remain local.

This repository publishes no accessibility score, search ranking, page-speed result, commercial metric or conversion claim.

## Trade-offs

Longer reasoning, including the parts I chose not to build, is in [docs/decisions.md](docs/decisions.md).

The case has no product-image pipeline, payment integration, account flow, tax setup, shipping rules or persistent operational data. Playground needs network access to download the pinned WordPress.org artifacts and retrieve the `v1.0.1` release tag. Docker is the route for retained local changes.

The `wordpress:cli-php8.3` helper image follows a PHP-line tag rather than an exact WP-CLI release. WordPress application core remains pinned to 7.0.2 in the shared volume.

## Disclosure

Morrow House is fictional. It is not a client project or commercial storefront. No real payments, orders, customers or customer data are used. The site-wide notice and checkout paragraph both state the limitation.

Local credentials and the `.example` email address are placeholders, not production credentials or contact details. Do not enter real personal or payment information.

## License

The theme, companion plugin and supporting source are available under [GPL-2.0-or-later](LICENSE), which is compatible with WordPress derivative work requirements.
