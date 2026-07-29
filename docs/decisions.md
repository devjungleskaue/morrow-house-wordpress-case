# Decisions

For the decisions that turned out to be wrong, see [postmortem.md](postmortem.md).

The README covers what this build does. These are the choices behind it, including the ones I would expect to defend in an interview.

## Elementor Free instead of native blocks

The brief was a storefront a shop owner can edit without touching code, and Elementor is what a large share of small WordPress shops already run. Choosing blocks would have produced cleaner markup and a lighter page, and it would also have handed the client an editing model their team does not use.

The cost I accepted: an extra plugin in the dependency chain, and one Playground-specific workaround, because WordPress's IDN encoder rejects the temporary session hostname that Elementor's library builder produces. That workaround is isolated behind a constant rather than shipped to every environment.

Only the Campaign page is Elementor. The rest of the storefront is theme templates, so the plugin is an editing surface for marketing content and not a dependency of the shopping flow. If Elementor were removed tomorrow, the shop, cart, and checkout would still work.

## Classic theme instead of a block theme

Block themes are where WordPress is going, and for a project starting today with no legacy that would be the default. This one is deliberately conventional because the case is about WooCommerce delivery, and WooCommerce template overrides in a classic theme are the shape most agency work still takes.

The tradeoff is real: a block theme would give the owner more control over layout without a page builder. I chose the model that matches the maintenance reality of the shops this represents.

## Opting out of the WooCommerce stylesheet

`functions.php` returns an empty array from `woocommerce_enqueue_styles`. The alternative is loading WooCommerce's CSS and overriding it, which means shipping two stylesheets where the second exists to cancel the first.

What this costs: every state WooCommerce styles for free has to be handled here, and missing one shows up as an unstyled notice or table rather than a slightly wrong color. That is why the smoke test renders cart, checkout, and empty-result states rather than only the shop page.

Block-based cart and checkout still load their own styles through a different path, which is why a few `!important` declarations remain. They are marked in the source.

## Product imagery from open museum collections

The catalogue shows three objects from the Cleveland Museum of Art's open access collection, all released CC0. Every file, its accession number and its source page are listed in [credits.md](credits.md).

This replaces an earlier approach that drew the product shapes with CSS gradients and suppressed `.woocommerce img` so those shapes stayed the only imagery. The reasoning had been that a fictional brand has no licensed photography and stock imagery would misrepresent the build. What that argument missed is how the result reads on screen: three identical brown gradient blobs do not say "no photography here by choice", they say "the images failed to load". A storefront whose whole subject is the shopping surface cannot afford a catalogue that looks broken.

Museum object photography solves it without the problem the CSS shapes were avoiding. The licence is unambiguous, the provenance is recorded rather than vague, and the house style of these collections is a single object on a neutral graded ground, which is the same convention premium homeware retailers use.

Two consequences worth stating. The product copy follows the photographs rather than the reverse: each listing's material and description describe the object actually pictured, and one product changed from a table lamp to a vase because no open collection holds a contemporary lamp that matches an editorial storefront. And the image frames carry a warm wash instead of white, because against the cream page a white box reads as a hole rather than a frame.

## Payments removed rather than stubbed

A fake gateway that always succeeds would demo better. It would also mean this repository contains code whose only purpose is pretending a payment happened, one copy-paste away from a real store.

Removing every gateway makes checkout visibly incomplete, which is the honest state for a build that must never take money. The Store API smoke asserts `payment_methods` is empty, so the guarantee is tested rather than assumed.

Demo mode defaults to ON and requires a deliberate line in `wp-config.php` to disable. A concept build that quietly starts accepting checkouts is a worse failure than one that refuses a sale it should have made.

## Playground alongside Docker

Docker is the honest local environment: persistent data, real database, the setup someone would actually develop in. Playground exists because a reviewer will not run `docker compose up` to look at a portfolio piece.

Keeping both means one project must boot two ways, which is why the seed is idempotent and tested twice in the same run. That constraint improved the code: a seed that cannot run twice is a seed nobody trusts.

## Testing repeatability instead of asserting it

The smoke boots the pinned stack, runs the seed twice, and compares database state. It also injects a real WordPress menu-creation failure to confirm the seed stops with context instead of leaving a half-built site.

That second part matters more than it looks. Most seed scripts are written for the happy path and fail silently halfway, and the site that results is worse than no site at all, because it looks finished.

## What I did not build

**Payment gateway integration.** Out of scope by design, and the reason is above.

**Multi-language.** The theme is translation-ready with a text domain throughout, but no locale ships. Adding one would mean maintaining copy in two languages for a fictional shop, without demonstrating anything the text domain does not already show.

**Product variations.** Three simple products exercise the catalogue, cart, and checkout path. Variations would add admin surface without changing what the case is about.

**A block theme port.** Interesting, and a different project. Doing both halfway would demonstrate neither.

**A product gallery.** A second view of each object needs a second photograph, and these collections hold one per accession. The alternative is manufacturing one by cropping or mirroring the first, and [credits.md](credits.md) says this repository does not fabricate its assets. `add_theme_support('wc-product-gallery-zoom')` stays declared because it costs nothing and becomes correct the day a real catalogue arrives.

## No published scores

The README states that this repository publishes no accessibility score, page-speed result, or conversion claim, and that is a decision rather than an omission.

A Lighthouse number from a seeded demo with three products says nothing about how the same code behaves on a real catalogue with hundreds of them, real plugins, and real traffic. Publishing it would invite a comparison that the number cannot support.

What the repository does instead is assert the structural pieces accessibility depends on, in the smoke test against rendered markup: a lang attribute, a skip link whose target exists, landmarks, an accessible name on navigation, state attributes on the menu button that point at a real element, and no image without alt text. Those either hold or fail the build. They are narrower than an audit and they do not go stale.
