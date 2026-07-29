# Post-mortem: four bugs this repository shipped

This file exists because the interesting part of a build is not the code that works. Everything below was in a published version of this repository, found by running the storefront and measuring the rendered pages rather than by reading the source. Three of the four share one root cause, which is the part worth reading.

## The pattern: a content class that is also a body class

WordPress puts a long list of classes on `<body>`: `page`, `page-id-7`, `woocommerce-checkout`, `woocommerce-page`, `single-product`, `search-results`. A stylesheet that names one of those without naming an element is not selecting the thing it looks like it is selecting. It is selecting the document.

### Checkout torn into two columns

`store.src.css` carried this, written for the classic checkout form:

```css
.woocommerce-checkout {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
}
```

This store renders the Checkout Block, so that form never exists. `woocommerce-checkout` is also the body class WordPress adds on the checkout page, and it was the only element on the page the selector matched. Measured at 1280px before the fix:

| Element | x | width |
|---|---|---|
| `body` | grid, `574px 574px` | |
| `header.site-header` | 649 | 574 |
| `main` | 43 | 574 |
| `footer.site-footer` | 649 | 574 |

The header sat top right, the checkout form bottom left, and the footer ran down the right side alongside the entire form. On mobile the media query collapsed the grid to one column, so the damage was invisible at the width most people test first.

The same rule had a second effect. `.skip-link` is `position: fixed` with no `top`, so it anchored to its static position, and once `<body>` became a grid that position moved to 121px. `translateY(-150%)` lifts it 75px. The skip link sat on screen, unfocused, on every checkout visit.

### Skip link visible on every page

Even away from checkout the skip link never hid. The demo notice injected at `wp_body_open` renders above it and occupies 58px. Static position 58.4px, transform −75.6px, final position **−17px** on an element 50px tall: 33px of a black button parked over the top of every page in the store. The fix is `top: 0`, one line, and the lesson is that `position: fixed` without an offset is not fixed to the viewport, it is fixed to wherever the element happened to land.

### Content constrained by accident, hiding a missing container

`article.page` is the theme's own wrapper, but the stylesheet said `.page`, so `body.page` matched on every page-type URL and the whole document was being held to `min(1180px, 100% - 2rem)` and centred. That is why the demo notice and the header stopped short of the screen edges on the home page and reached them on the shop.

Scoping the selector to the element exposed a second bug that the accident had been covering: `front-page.php` never wrapped `the_content()` in a container at all. With the body no longer constrained, the home page ran edge to edge while the header kept its own padding, and the headline sat 38px to the left of the brand above it. Seven measured points on the page now share one left edge, deviation 0.

## Cart and checkout pages that rendered nothing

Unrelated to the above, and worse, because it broke function rather than layout.

`seed.php` wrote `<!-- wp:woocommerce/cart /-->` over the Cart and Checkout pages that WooCommerce creates during install. That is the void form of the block. These blocks render their inner content, and WooCommerce's installer writes a nested template into the page: `filled-cart-block`, `cart-items-block`, `cart-line-items-block`, and a dozen more. A void block has no inner content, so it renders nothing.

Measured with `do_blocks()`:

```
void form      ->    6 bytes
WooCommerce's  -> 7107 bytes
```

Both pages came up in a fresh install carrying only their `<h1>`. A shopper could add to cart and then land on a page that said "Cart" and nothing else.

The fix is not to rebuild WooCommerce's template by hand, which would mean owning a copy of its internals that goes stale on the next release. The seed asks `wc_get_page_id()` where WooCommerce put its pages and prepends the demo disclosure with a marker guard so a second run does not stack a second copy.

## The check that asserted less than it appeared to

The accessibility contract in `scripts/smoke-test.sh` verifies `<html lang>`, a skip link whose target exists, three landmarks, an accessible name on navigation, state attributes on the menu button, no `<img>` without alt, and exactly one `<h1>`.

Adding `404.php` gave the error page all of those. The contract runs without `curl --fail`, because one of the paths under test is the 404 itself. So from that moment, deleting a product would make `/product/vale-fluted-vase/` answer 404 with one `h1`, a lang attribute, a working skip link and every landmark in place, and the contract would have reported a pass.

Each path now carries the status it must answer with. Asking for a product that does not exist while expecting 200 fails with `answered 404, expected 200`.

This is the same shape as the void cart block: a thing that looked like it was checking something and was checking something adjacent. Worth stating plainly, because the fix that created the blind spot was itself a fix for a missing heading.

## What changed in how this repository is tested

Every bug above survived a code review of the file it lived in, because reading `.woocommerce-checkout` tells you nothing is wrong. What found them was booting the storefront and measuring the rendered result. So the checks moved in that direction:

- The accessibility contract runs on nine paths instead of one, including the 404 and the empty search, and asserts the HTTP status each must answer with.
- A test walks every class this project writes into markup and fails on any the stylesheet ignores. Five had none, which is why the eyebrow rendered as body copy and the product's Material and Care list fell back to the browser's indented default.
- `theme.json` is generated from the stylesheet's `:root`, and the build fails if a colour reaches the CSS without reaching the editor. The two files had already drifted: two colours existed only in the CSS, and they disagreed about content width by 420px.
- The contract test that pinned the void cart block now pins its absence, checked in both directions.

Each of those was verified by reintroducing the bug and watching the test fail, then reverting and watching it pass. A regression test nobody has seen fail is a regression test nobody should trust.

## Two habits that produced most of this

**Reading part of a file and acting as if I had read all of it.** A stylesheet rewritten from its first 600 characters lost 33 of 71 selectors. A README "gap analysis" from its first 45 lines recommended three sections that were already there.

**Writing a check that passes for the wrong reason.** The void block, and then the 404 blind spot in the fix for it. Both looked correct in review. Both needed the failure demonstrated before the pass meant anything.
