import assert from "node:assert/strict";
import { access, readFile } from "node:fs/promises";
import test from "node:test";

const read = (path) => readFile(new URL(`../${path}`, import.meta.url), "utf8");

test("keeps exported text endings stable across platforms", async () => {
  const attributes = await read(".gitattributes");
  assert.match(attributes, /^\* text=auto eol=lf$/m);
  assert.match(attributes, /^\*\.ps1 text eol=crlf$/m);
});

test("keeps local configuration reproducible and non-secret", async () => {
  const [env, ignore] = await Promise.all([read(".env.example"), read(".gitignore")]);
  assert.match(env, /WORDPRESS_PORT=8080/);
  assert.match(env, /morrow_local_only/);
  assert.match(ignore, /^\.env$/m);
  assert.match(ignore, /^uploads\/$/m);
  assert.doesNotMatch(env, /api[_-]?key|client[_-]?secret|BEGIN (RSA|OPENSSH) PRIVATE KEY/i);
});

test("theme declares the public project without private or production references", async () => {
  const theme = await read("wp-content/themes/morrow-house/style.css");
  assert.match(theme, /Theme Name:\s*Morrow House/);
  assert.match(theme, /Requires PHP:\s*8\.2/);
  assert.doesNotMatch(theme, /private reference|production database/i);
});

test("seed fails contextually on menu errors and gates rewrite flushing", async () => {
  const seed = await read("scripts/seed.php");
  assert.match(seed, /is_wp_error\(\$created_menu\)/);
  assert.match(seed, /Could not create navigation menu.+%s.+\$menu_name/s);
  assert.match(seed, /\$created_item\s*=\s*wp_update_nav_menu_item/);
  assert.match(seed, /is_wp_error\(\$created_item\)/);
  assert.match(seed, /Could not add page.+primary navigation.+get_the_title\(\$page_id\).+\$page_id/s);

  const itemCheck = seed.indexOf("is_wp_error($created_item)");
  const locationAssignment = seed.indexOf("$locations['primary'] = $menu_id");
  assert.ok(itemCheck >= 0 && locationAssignment > itemCheck, "menu location must be assigned only after every item succeeds");

  assert.match(seed, /MORROW_HOUSE_SEED_VERSION/);
  assert.match(seed, /set_permalink_structure\('\/%postname%\/'\)/);
  assert.match(seed, /get_option\('morrow_house_seed_version'/);
  assert.equal(seed.match(/flush_rewrite_rules\(\)/g)?.length, 1);
  assert.match(seed, /update_option\('morrow_house_seed_version',\s*MORROW_HOUSE_SEED_VERSION\)/);
});

test("Checkout stays block-based with a supported disclosure and no payment gateways", async () => {
  const [plugin, demo, seed] = await Promise.all([
    read("wp-content/plugins/morrow-house-core/morrow-house-core.php"),
    read("wp-content/plugins/morrow-house-core/includes/class-demo-mode.php"),
    read("scripts/seed.php"),
  ]);
  assert.match(plugin, /WC tested up to:\s*10\.9\.4/);
  assert.match(plugin, /before_woocommerce_init/);
  assert.match(plugin, /declare_compatibility\(\s*'cart_checkout_blocks'/);
  assert.match(demo, /woocommerce_available_payment_gateways/);
  assert.doesNotMatch(demo, /woocommerce_order_button_text|woocommerce_checkout_before_customer_details/);
  assert.match(demo, /no real orders or payments/i);
  assert.match(seed, /<!-- wp:woocommerce\/checkout \/-->/);
  assert.match(seed, /mh-checkout-disclosure/);
  assert.match(seed, /Payment methods are intentionally unavailable/);
});

test("Campaign leaves its only h1 and shopper copy under Elementor ownership", async () => {
  const [seed, page] = await Promise.all([read("scripts/seed.php"), read("wp-content/themes/morrow-house/page.php")]);
  assert.match(seed, /'header_size'\s*=>\s*'h1'/);
  assert.match(seed, /Start with a clear surface and keep the objects you reach for close at hand\./);
  assert.doesNotMatch(seed, /assembled with Elementor|kept deliberately light/i);
  assert.match(page, /get_post_meta\(get_the_ID\(\),\s*'_elementor_edit_mode',\s*true\)/);
  assert.match(page, /if\s*\(\s*'builder'\s*!==/);
});

test("mobile navigation enhances from a usable fallback and cart count can refresh", async () => {
  const [css, js, functions, header] = await Promise.all([
    read("wp-content/themes/morrow-house/assets/css/store.css"),
    read("wp-content/themes/morrow-house/assets/js/store.js"),
    read("wp-content/themes/morrow-house/functions.php"),
    read("wp-content/themes/morrow-house/header.php"),
  ]);
  assert.match(css, /@media\(max-width:760px\).*\.site-header nav\{display:block/s);
  assert.match(css, /\.js \.menu-toggle\{display:block\}/);
  assert.match(css, /\.js \.site-header nav\{display:none/);
  assert.match(js, /document\.documentElement\.classList\.add\("js"\)/);
  assert.match(js, /wc-blocks_added_to_cart/);
  assert.match(js, /wc_fragment_refresh/);
  assert.match(functions, /woocommerce_add_to_cart_fragments/);
  assert.match(functions, /wc-cart-fragments/);
  assert.match(functions, /aria-live="polite"/);
  assert.match(header, /morrow_house_cart_link\(\)/);
});

test("blueprint pins supported releases and planned immutable repository tag", async () => {
  const blueprint = JSON.parse(await read("blueprint.json"));
  assert.equal(blueprint.$schema, "https://playground.wordpress.net/blueprint-schema.json");
  assert.equal(blueprint.landingPage, "/");
  assert.deepEqual(blueprint.preferredVersions, { php: "8.3", wp: "7.0.2" });
  assert.deepEqual(blueprint.features, { networking: true });
  assert.match(blueprint.meta.description, /data is discarded when the browser session ends/i);
  assert.doesNotMatch(JSON.stringify(blueprint), /password|api[_-]?key|secret/i);
  assert.deepEqual(blueprint.steps[0], {
    step: "defineWpConfigConsts",
    consts: { MORROW_HOUSE_IS_PLAYGROUND: true },
    method: "rewrite-wp-config",
  });
  assert.equal(blueprint.steps[1]?.step, "setSiteOptions");
  const firstInstall = blueprint.steps.findIndex(({ step }) => step === "installPlugin" || step === "installTheme");
  assert.ok(blueprint.steps.findIndex(({ step }) => step === "setSiteOptions") < firstInstall);

  const expectedPluginArtifacts = new Map([
    ["woocommerce", "https://downloads.wordpress.org/plugin/woocommerce.10.9.4.zip"],
    ["elementor", "https://downloads.wordpress.org/plugin/elementor.4.2.1.zip"],
  ]);
  for (const [folder, url] of expectedPluginArtifacts) {
    assert.ok(
      blueprint.steps.some(
        ({ step, pluginData, options }) =>
          step === "installPlugin" &&
          pluginData?.resource === "url" &&
          pluginData.url === url &&
          options?.activate === true &&
          options?.targetFolderName === folder,
      ),
    );
  }
  const pluginIndex = (targetFolderName) =>
    blueprint.steps.findIndex(({ step, options }) => step === "installPlugin" && options?.targetFolderName === targetFolderName);
  assert.ok(pluginIndex("woocommerce") < pluginIndex("morrow-house-core"));
  assert.ok(pluginIndex("morrow-house-core") < pluginIndex("elementor"));

  const repositoryResources = [];
  for (const step of blueprint.steps) {
    if (step.pluginData?.resource === "git:directory") repositoryResources.push(step.pluginData);
    if (step.themeData?.resource === "git:directory") repositoryResources.push(step.themeData);
    if (step.filesTree?.resource === "git:directory") repositoryResources.push(step.filesTree);
  }
  assert.equal(repositoryResources.length, 3);
  for (const resource of repositoryResources) {
    assert.equal(resource.url, "https://github.com/devjungleskaue/morrow-house-wordpress-case");
    assert.equal(resource.ref, "v1.0.1");
    assert.equal(resource.refType, "tag");
  }

  const seedFiles = blueprint.steps.find(({ step }) => step === "writeFiles");
  const runSeed = blueprint.steps.find(({ step }) => step === "runPHP");
  const siteOptions = blueprint.steps.find(({ step }) => step === "setSiteOptions");
  assert.deepEqual(siteOptions, {
    step: "setSiteOptions",
    options: {
      blogname: "Morrow House",
      blogdescription: "Objects for considered rooms",
      permalink_structure: "/%postname%/",
      woocommerce_currency: "CAD",
      woocommerce_enable_guest_checkout: "yes",
    },
  });
  assert.match(runSeed.code, /^<\?php require_once '\/wordpress\/wp-load\.php'; require '\/tmp\/morrow-house-seed\/seed\.php';$/);
  assert.ok(blueprint.steps.indexOf(seedFiles) < blueprint.steps.indexOf(runSeed));
});

test("Playground replaces only Elementor's Library URL builder", async () => {
  const [plugin, adapter] = await Promise.all([
    read("wp-content/plugins/morrow-house-core/morrow-house-core.php"),
    read("wp-content/plugins/morrow-house-core/includes/class-playground-elementor-library.php").catch(() => ""),
  ]);
  const playgroundBranch = plugin.match(
    /if\s*\(\s*defined\(\s*'MORROW_HOUSE_IS_PLAYGROUND'\s*\).*?\n\}/s,
  )?.[0] ?? "";

  assert.match(playgroundBranch, /MORROW_HOUSE_IS_PLAYGROUND/);
  assert.match(playgroundBranch, /elementor\/connect\/apps\/register/);
  assert.match(playgroundBranch, /register_app\(\s*'library'/);
  assert.match(playgroundBranch, /class-playground-elementor-library\.php/);
  assert.match(plugin, /Version:\s*1\.0\.1/);
  assert.match(adapter, /extends\s+\\Elementor\\Core\\Common\\Modules\\Connect\\Apps\\Library/);
  assert.match(adapter, /public function get_admin_url\(\s*\$action,\s*\$params\s*=\s*\[\]\s*\)/);
  assert.match(adapter, /'app'\s*=>\s*\$this->get_slug\(\)/);
  assert.match(adapter, /'action'\s*=>\s*\$action/);
  assert.match(adapter, /'nonce'\s*=>\s*wp_create_nonce\(\s*\$this->get_slug\(\)\s*\.\s*\$action\s*\)/);
  assert.match(adapter, /get_admin_url\(\)/);
  assert.match(adapter, /\\Elementor\\Core\\Common\\Modules\\Connect\\Admin::PAGE_ID/);
  assert.doesNotMatch(adapter, /Str::encode_idn_url|Reflection/);
  assert.doesNotMatch(`${plugin}\n${adapter}`, /remove_(?:action|filter).*cloud|disable.*cloud-library/i);
});

test("release metadata stays aligned at 1.0.1", async () => {
  const [manifest, lockfile, plugin] = await Promise.all([
    read("package.json").then(JSON.parse),
    read("package-lock.json").then(JSON.parse),
    read("wp-content/plugins/morrow-house-core/morrow-house-core.php"),
  ]);

  assert.equal(manifest.version, "1.0.1");
  assert.equal(lockfile.version, "1.0.1");
  assert.equal(lockfile.packages[""].version, "1.0.1");
  assert.match(plugin, /Version:\s*1\.0\.1/);
});

test("Compose pins WordPress and mounts only project code and smoke checks", async () => {
  const compose = await read("docker-compose.yml");
  assert.match(compose, /^  db:\r?\n    image: mariadb:11\.4$/m);
  assert.match(compose, /^  wordpress:\r?\n    image: wordpress:7\.0\.2-php8\.3-apache$/m);
  assert.match(compose, /^  cli:\r?\n    image: wordpress:cli-php8\.3$/m);
  assert.match(compose, /ports: \["\$\{WORDPRESS_PORT\}:80"\]/);
  assert.match(compose, /MARIADB_DATABASE: \$\{WORDPRESS_DB_NAME\}/);
  assert.match(compose, /WORDPRESS_DB_HOST: db/);
  assert.match(compose, /wordpress_data:\/var\/www\/html/);
  assert.match(compose, /\.\/wp-content\/themes\/morrow-house:\/var\/www\/html\/wp-content\/themes\/morrow-house/);
  assert.match(compose, /\.\/wp-content\/plugins\/morrow-house-core:\/var\/www\/html\/wp-content\/plugins\/morrow-house-core/);
  assert.match(compose, /\.\/scripts:\/project\/scripts:ro/);
  assert.match(compose, /\.\/tests:\/project\/tests:ro/);
  assert.doesNotMatch(compose, /morrow_local_only|root_local_only/);
});

test("reset derives one validated URL and uses a repository-specific namespace", async () => {
  const reset = await read("scripts/reset-local.ps1");
  assert.match(reset, /Join-Path \$projectRoot 'blueprint\.json'/);
  assert.match(reset, /wp-content\\themes\\morrow-house\\style\.css/);
  assert.match(reset, /\$originalWordPressPort = \[Environment\]::GetEnvironmentVariable\('WORDPRESS_PORT', 'Process'\)/);
  assert.match(reset, /if \(!\(Test-Path -LiteralPath '\.env'\)\) \{ Copy-Item -LiteralPath '\.env\.example' -Destination '\.env' \}/);
  assert.match(reset, /\$composeProject = 'morrow-house-wordpress-case-local'/);
  assert.match(reset, /\$wordpressPort -notmatch '\^\\d\{1,5\}\$'/);
  assert.match(reset, /\$env:WORDPRESS_PORT = \$wordpressPort/);
  assert.match(reset, /\$localUrl = "http:\/\/localhost:\$wordpressPort"/);
  assert.match(reset, /\$localUrl\/wp-admin\/install\.php/);
  assert.match(reset, /'wp', 'core', 'install', "--url=\$localUrl"/);
  assert.match(reset, /'wp', 'eval', "require '\/project\/scripts\/seed\.php';"/);
  assert.match(reset, /Write-Host "Morrow House is ready at \$localUrl"/);
  assert.match(reset, /finally\s*\{\s*\[Environment\]::SetEnvironmentVariable\('WORDPRESS_PORT', \$originalWordPressPort, 'Process'\)/s);
  assert.match(reset, /function Invoke-MorrowHouseCompose/);
  assert.match(reset, /\$exitCode = \$LASTEXITCODE/);
  assert.match(reset, /if \(\$exitCode -ne 0\)/);
  assert.doesNotMatch(reset, /localhost:8080/);
  assert.doesNotMatch(reset, /--project-name morrow-house(?:\s|$)/);

  const composeInvocations = reset.match(/^  Invoke-MorrowHouseCompose .+$/gm) ?? [];
  assert.equal(composeInvocations.length, 7);
  assert.equal(reset.match(/^\s*& docker compose /gm)?.length, 1);
});

test("runtime smoke is disposable, integration-level, and required by CI", async () => {
  const smokePath = new URL("../scripts/smoke-test.sh", import.meta.url);
  await access(smokePath);
  const [smoke, runtime, menuFailure, workflow] = await Promise.all([
    read("scripts/smoke-test.sh"),
    read("tests/runtime-smoke.php"),
    read("tests/runtime-menu-failure.php"),
    read(".github/workflows/quality.yml"),
  ]);

  assert.match(smoke, /morrow-house-smoke-/);
  assert.match(smoke, /\^morrow-house-smoke-\[a-z0-9\]/);
  assert.doesNotMatch(smoke, /\$\{run_token,,\}/);
  assert.match(smoke, /WORDPRESS_PORT=0/);
  assert.match(smoke, /^\s*MSYS_NO_PATHCONV=1 docker compose/m);
  assert.doesNotMatch(smoke, /export MSYS_NO_PATHCONV/);
  assert.match(smoke, /trap cleanup EXIT/);
  assert.match(smoke, /trap 'exit 130' INT/);
  assert.match(smoke, /trap 'exit 143' TERM/);
  assert.match(smoke, /down --volumes --remove-orphans/);
  assert.match(smoke, /com\.docker\.compose\.project/);
  assert.match(smoke, /wp eval-file \/project\/tests\/runtime-smoke\.php/);
  assert.match(smoke, /wp eval-file \/project\/tests\/runtime-menu-failure\.php/);
  assert.match(smoke, /wp-json\/wc\/store\/v1\/cart\/add-item/);
  assert.match(smoke, /elementor\.4\.2\.1\.zip/);
  assert.match(smoke, /payment_methods/);
  assert.match(smoke, /mh-checkout-disclosure/);
  assert.match(smoke, /<h1/i);

  assert.match(runtime, /morrow_house_seed\(\)/);
  assert.match(runtime, /ELEMENTOR_VERSION === '4\.2\.1'/);
  assert.match(runtime, /flush_rewrite_rules_hard/);
  assert.match(runtime, /get_nav_menu_locations/);
  assert.match(runtime, /_elementor_data/);
  assert.match(runtime, /get_available_payment_gateways/);
  assert.doesNotMatch(runtime, /declare\(strict_types/);
  assert.match(menuFailure, /pre_insert_term/);
  assert.match(menuFailure, /forced menu creation failure/);
  assert.doesNotMatch(menuFailure, /declare\(strict_types/);
  assert.match(workflow, /timeout-minutes:\s*15/);
  assert.match(workflow, /bash scripts\/smoke-test\.sh/);
  assert.match(workflow, /permissions:\r?\n  contents: read/);
  assert.match(workflow, /runs-on:\s*windows-latest/);
  assert.match(workflow, /tests\\reset-local\.test\.ps1/);
});

test("public copy documents the real disclosure, matrix, release gate, and license", async () => {
  const [readme, license] = await Promise.all([read("README.md"), read("LICENSE")]);
  const normalized = readme.replace(/\r\n/g, "\n");
  const normalizedLicense = license.replace(/\r\n/g, "\n");
  const opening = "Morrow House is a conceptual reference build, not a client project. It demonstrates a reproducible WordPress, Elementor Free and WooCommerce delivery with no real payments or customer data.";
  assert.ok(normalized.startsWith(`# Morrow House — custom WooCommerce storefront\n\n${opening}\n`));
  const headings = [...normalized.matchAll(/^(#{1,6})\s+(.+)$/gm)].map(([, hashes, title]) => `${hashes} ${title}`);
  assert.deepEqual(headings, [
    "# Morrow House — custom WooCommerce storefront",
    "## What this proves",
    "## Launch the temporary store",
    "## Business brief",
    "## Architecture",
    "## Hard parts",
    "## Run locally",
    "## Validation",
    "## Accessibility, SEO and performance",
    "## Trade-offs",
    "## Disclosure",
    "## License",
  ]);
  assert.match(readme, /^# Morrow House — custom WooCommerce storefront$/m);
  assert.match(readme, /conceptual reference build/i);
  assert.match(readme, /no real payments/i);
  assert.match(readme, /checkout block/i);
  assert.match(readme, /WordPress 7\.0\.2/);
  assert.match(readme, /WooCommerce 10\.9\.4/);
  assert.match(readme, /Elementor Free 4\.2\.1/);
  assert.match(
    readme,
    /https:\/\/playground\.wordpress\.net\/\?blueprint-url=https:\/\/raw\.githubusercontent\.com\/devjungleskaue\/morrow-house-wordpress-case\/v1\.0\.1\/blueprint\.json/,
  );
  assert.match(readme, /`v1\.0\.1` repository tag/i);
  assert.doesNotMatch(readme, /planned `v1\.0\.1`|has not been published/i);
  assert.match(readme, /MORROW_HOUSE_IS_PLAYGROUND/);
  assert.match(readme, /Cloud Library module stays enabled/i);
  assert.match(readme, /Docker keeps Elementor's default URL handling/i);
  assert.match(readme, /GPL-2\.0-or-later/);
  assert.doesNotMatch(normalizedLicense, /\n\n$/);
  assert.doesNotMatch(readme, /names, prices, address/i);
  assert.doesNotMatch(readme, /conversion lift|revenue generated|used in production|live commercial store/i);
  assert.doesNotMatch(readme, /details to follow|coming soon|will be documented|after a public snapshot is available/i);
});

test("keeps the minified stylesheet in sync with its source", async () => {
  // The build is a plain text transform, so drift is easy and silent: someone
  // edits the source, forgets `npm run build:css`, and ships the old CSS.
  const fonte = await read("wp-content/themes/morrow-house/assets/css/store.src.css");
  const build = await read("wp-content/themes/morrow-house/assets/css/store.css");

  const esperado = fonte
    .replace(/\/\*[\s\S]*?\*\//g, "")
    .replace(/\s*([{}:;,>])\s*/g, "$1")
    .replace(/;}/g, "}")
    .replace(/\s+/g, " ")
    .trim();

  assert.equal(build.trim(), esperado, "store.css is stale; run npm run build:css");
});
