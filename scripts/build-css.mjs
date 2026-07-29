// Builds store.css and theme.json from store.src.css.
//
// The stylesheet's :root block is the theme's single source of truth for
// colour, width and type. Both outputs are generated from it, so the editor and
// the front end cannot disagree: that had already happened once, with --muted
// and --clay-ink living in the stylesheet and never reaching theme.json, and
// with theme.json declaring a 760px content width against the stylesheet's
// 1180px.
//
// The minifier is a deliberate 20 lines rather than a dependency: the file is
// 7 KB of plain CSS, and a build chain would cost more to maintain than it
// saves in bytes.
import { readFile, writeFile } from "node:fs/promises";

const THEME = new URL("../wp-content/themes/morrow-house/", import.meta.url);
const CSS = new URL("assets/css/", THEME);

// Every colour token, with the name the editor shows. A colour present in :root
// but missing here fails the build, which is the check that stops a new colour
// from being added to the stylesheet and forgotten in the editor.
const PALETTE = [
  ["paper", "Paper"],
  ["ink", "Ink"],
  ["pine", "Pine"],
  ["clay", "Clay"],
  ["clay-ink", "Clay ink"],
  ["line", "Line"],
  ["muted", "Muted"],
];

const source = await readFile(new URL("store.src.css", CSS), "utf8");

// --- tokens -----------------------------------------------------------------

const rootBlock = source.match(/:root\s*\{([\s\S]*?)\n\}/);
if (!rootBlock) throw new Error("store.src.css has no :root block to read tokens from.");

const tokens = new Map();
for (const line of rootBlock[1].replace(/\/\*[\s\S]*?\*\//g, "").split(/;|\n/)) {
  const decl = line.trim();
  if (!decl.startsWith("--")) continue;
  const at = decl.indexOf(":");
  tokens.set(decl.slice(2, at).trim(), decl.slice(at + 1).trim());
}

const need = (name) => {
  const value = tokens.get(name);
  if (!value) throw new Error(`Token --${name} is missing from :root in store.src.css.`);
  return value;
};

const declared = new Set(PALETTE.map(([slug]) => slug));
const undeclared = [...tokens].filter(([slug, value]) => /^#[0-9a-f]{3,8}$/i.test(value) && !declared.has(slug));
if (undeclared.length) {
  throw new Error(
    `These colours are in :root but not in the palette manifest in scripts/build-css.mjs, ` +
      `so the editor would not offer them: ${undeclared.map(([s]) => `--${s}`).join(", ")}.`,
  );
}

// --- theme.json -------------------------------------------------------------

const themeJson = {
  $schema: "https://schemas.wp.org/trunk/theme.json",
  version: 3,
  settings: {
    appearanceTools: true,
    color: {
      palette: PALETTE.map(([slug, name]) => ({ slug, name, color: need(slug).toUpperCase() })),
    },
    // contentSize is the prose measure and wideSize the shell, matching what
    // the stylesheet gives each, so a block dropped into the editor lands in
    // the column the front end would give it.
    layout: { contentSize: need("measure"), wideSize: need("shell") },
    spacing: { units: ["px", "rem", "vw"] },
    typography: {
      fluid: true,
      fontFamilies: [
        { slug: "display", name: "Display", fontFamily: need("font-display") },
        { slug: "body", name: "Body", fontFamily: need("font-body") },
      ],
      fontSizes: [
        { slug: "small", name: "Small", size: need("type-small") },
        { slug: "sub", name: "Sub", size: need("type-sub") },
        { slug: "section", name: "Section", size: need("type-section") },
        { slug: "title", name: "Title", size: need("type-title") },
      ],
    },
  },
};

await writeFile(new URL("theme.json", THEME), JSON.stringify(themeJson, null, 2) + "\n");

// --- store.css --------------------------------------------------------------

const minified = source
  .replace(/\/\*[\s\S]*?\*\//g, "")   // comments
  .replace(/\s*([{}:;,>])\s*/g, "$1") // space around punctuation
  .replace(/;}/g, "}")                // last semicolon of a block
  .replace(/\s+/g, " ")               // remaining space
  .trim();

await writeFile(new URL("store.css", CSS), minified + "\n");

console.log(`store.css: ${source.length} -> ${minified.length} bytes`);
console.log(`theme.json: ${PALETTE.length} colours, contentSize ${themeJson.settings.layout.contentSize}, wideSize ${themeJson.settings.layout.wideSize}`);
