# Image credits

The three product photographs in `scripts/assets` come from the [Cleveland Museum of Art Open Access](https://www.clevelandart.org/open-access) collection. All three are published under [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/), which places them in the public domain: no permission or attribution is required. They are credited here anyway, because a repository that cannot say where its assets came from is not showing how the work was done.

| File | Object | Accession | Origin | Source |
|---|---|---|---|---|
| `vale-fluted-vase.jpg` | Artichoke Vase, glazed white clay, c. 1905–10 | 2018.292 | America, New York | [clevelandart.org/art/2018.292](https://clevelandart.org/art/2018.292) |
| `field-tray.jpg` | Footed Tray, lacquer over twisted and coiled paper, 1900s | 1988.248 | Korea | [clevelandart.org/art/1988.248](https://clevelandart.org/art/1988.248) |
| `low-vessel.jpg` | Jar with Four Lugs, stoneware with traces of ash glaze, 500s–600s | 1993.43 | Korea, Three Kingdoms period | [clevelandart.org/art/1993.43](https://clevelandart.org/art/1993.43) |

The files are the collection's `_web` derivatives, each padded to a square by extending its outermost row and column of pixels outward, then re-encoded as JPEG at quality 88. They land between 900 and 1263 pixels square, about 100 to 150 KB each.

Padding rather than cropping is the point. The three objects arrive in three orientations, a square grid needs one shape, and cropping a photograph to fit would cut into the object. Extending the edge works here because every one of these is a single object on a graded studio ground: the extension continues that gradient, so there is no visible seam and nothing is invented. Nothing inside the original frame is altered, scaled or recoloured.

The product names, prices and copy around them are invented for this build, as described in the [README](../README.md); the objects are real and the museum's records are the authority on them.

Nothing else in this repository carries a third-party licence. The reasoning behind using museum photography at all is in [decisions.md](decisions.md).
