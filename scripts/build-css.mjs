// Gera store.css a partir de store.src.css.
//
// Minificador proposital de 20 linhas em vez de uma dependencia: o arquivo tem
// 6 KB e nao usa nada alem de CSS padrao. Trazer um build chain inteiro pra
// isso custaria mais em manutencao do que economiza em bytes.
import { readFile, writeFile } from "node:fs/promises";

const DIR = new URL("../wp-content/themes/morrow-house/assets/css/", import.meta.url);

const fonte = await readFile(new URL("store.src.css", DIR), "utf8");

const minificado = fonte
  .replace(/\/\*[\s\S]*?\*\//g, "")   // comentarios
  .replace(/\s*([{}:;,>])\s*/g, "$1") // espaco em volta de pontuacao
  .replace(/;}/g, "}")                // ultimo ponto e virgula do bloco
  .replace(/\s+/g, " ")               // espaco restante
  .trim();

await writeFile(new URL("store.css", DIR), minificado + "\n");

console.log(`store.css: ${fonte.length} -> ${minificado.length} bytes`);
