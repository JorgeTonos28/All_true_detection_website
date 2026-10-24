# Dirección artística de la portada ATD

Rediseño de evolución: conservar marca, azul profundo/verde, catálogo, anclajes y proceso. Mejorar jerarquía y ritmo; retirar consola ficticia y decoración vectorial de fondos. Los textos y enlaces siguen siendo HTML. El proceso mantiene su composición. Animaciones con IntersectionObserver/CSS y alternativa de movimiento reducido, sin secuestrar el scroll.

## Recursos generados

Generación mediante la herramienta integrada de imágenes, solicitando alpha real. Originales conservados fuera del repositorio; WebP de producción en `public/assets/images/art/`:

| Archivo | Uso | Dimensiones | Peso |
| --- | --- | --- | --- |
| hero-lattice-v1.webp | Hero y detalle de apoyo humano | 1536 × 1024 | 525.534 bytes |
| services-evidence-v1.webp | Catálogo de servicios | 733 × 1100 | 119.452 bytes |
| risk-topography-v1.webp | Prevención y cierre | 1536 × 1024 | 223.732 bytes |
| sector-network-v1.webp | Sectores y confidencialidad | 1536 × 1024 | 369.796 bytes |

`php scripts/prepare-home-art.php DIRECTORIO_ORIGINALES` convierte PNG a WebP mediante GD, preserva alpha y verifica transparencia completa. No dibuja ni modifica el contenido artístico. No sobrescribe archivos existentes. Las fotografías existentes se conservan. No hay texto, estadísticas, logotipos ni interfaces ficticias dentro de las imágenes.

## Prompts finales

### Hero

Use case: stylized-concept. Asset type: transparent hero artwork for All True Detection, a professional forensic verification and human-risk consultancy website. Create an exceptionally art-directed, wide landscape 3D filament sculpture: a sweeping woven lattice of ultra-fine silver-white and emerald green (#35a854) threads, transitioning from precise fingerprint-like contour ridges into a flowing scientific mesh. Rich depth, restrained silver reflections, meticulous microstructure, elegant rather than sci-fi. It will be layered on a deep navy (#071d2d) website. Composition: sculpture dominates the right two-thirds, an open sweeping arc, generous completely transparent negative space on the left for HTML headline. Small individual intersecting threads and a few subtle detached filaments around the sculpture. The material itself visible and dimensional, not a flat grid. True transparent alpha background everywhere outside the sculpture and through lattice openings, no opaque backdrop, no haze rectangle, edges fade naturally into transparency. No text, no logos, no interface, no numbers, no icons, no neon glow, no generic gradient blobs. Wide landscape composition about 3:2, crisp high quality.

### Servicios

Use case: stylized-concept. Asset type: transparent sculptural background accent for the services catalogue of All True Detection, a forensic document and professional-credential verification consultancy. Create a premium editorial 3D still life made of three suspended, thin translucent glass document planes and finely layered silver-white paper sheets. An elegant emerald-green (#35a854) optical lens refracts one corner, with very fine silver contour filaments threading through the planes, evoking careful examination and verified evidence. Restrained realistic glass, brushed silver, frosted paper, meticulous studio craftsmanship, sophisticated contemporary graphic design. Vertical composition, object cluster concentrated in lower central area, isolated silhouette with generous empty margins. Designed to sit behind a service section on pale cool gray or navy. Genuine transparent alpha background outside objects and through glass, no floor, no opaque backdrop or rectangular haze. No readable writing, no letters, no words, no numbers, no logos, no shields, no icons, no interface or fake dashboard, no neon. Standalone asset only.

### Riesgo

Use case: stylized-concept. Asset type: transparent layered background artwork for an editorial risk-prevention section of All True Detection, a serious forensic verification consultancy. A wide, gracefully folded terrain of hundreds of thin silver-white and deep emerald contour ribbons, a precise topographic paper sculpture with one narrow green seam running through it, suggesting tracing hidden signals and understanding complex risk. Beautiful tactile brushed-silver and paper-like edges, precise soft studio lighting, nuanced depth, no neon. The relief occupies mainly the right half and lower edge, leaving the left third almost empty for HTML copy. Designed to overlay deep navy #071d2d. Genuine transparent alpha background, gaps and outside margins transparent, no opaque background, no floor, no haze rectangle, no text, no numbers, no labels, no dashboard, no logo, no icons. Landscape 3:2. Premium scientific editorial art, sculptural but restrained.

### Sectores

Use case: stylized-concept. Asset type: transparent background artwork for the sectors/coverage section of All True Detection. Create a beautiful sparse network sculpture built from tiny brushed-silver joints and dark emerald-green glass junctions connected by impossibly fine silver threads, forming a loose open architectural constellation with several curved bridges. Suggest interconnected organizations and rigorous connections, not space or galaxies. Wide horizontal composition with concentrated elements along outer edges and upper-right, center largely transparent for HTML sector labels. Crisp material detail, restrained studio lighting, contemporary Swiss scientific art direction, professional rather than sci-fi. Colors silver-white, #35a854 emerald, deep navy details. True transparent alpha backdrop throughout gaps and outside objects, no opaque or gradient rectangle, no text, no numbers, no icons, no logos, no glow blobs, no stock UI. Landscape 3:2. Refined subtle backdrop designed to layer on pale cool-gray or navy sections.

## Capa fija complementaria del hero

`public/assets/images/art/hero-static-filaments-v1.webp`, 1536 × 1024, 234.242 bytes. Generada con la herramienta integrada y alpha real, optimizada mecánicamente con GD. Complementa el lado izquierdo y superior, sin sustituir ni animar junto a la malla derecha. Se conserva el original en el directorio de imágenes generadas. La clase `hero-static-art` no tiene animación ni timeline; la malla original conserva `art-drift`. La máscara de legibilidad y opacidad se ajustan para escritorio y móvil.

Para convertir únicamente este recurso sin sobrescribir los anteriores: `php scripts/prepare-home-art.php DIRECTORIO_ORIGINALES hero-static-filaments-v1.webp`.

Prompt final:

Use case: stylized-concept. Asset type: genuinely transparent static background layer for the LEFT and UPPER side of All True Detection's website hero. Primary request: a refined scientific engraving made of many fine brushed-silver and muted emerald (#35a854) contour filaments, flowing as an open diagonal fan from the upper-left corner along the left edge. A few precision-cut translucent glass slivers interleaved among the strands, beautifully crafted tactile detail, elegant forensic/editorial art, not sci-fi. This is a complementary layer to an existing silver/emerald woven mesh sculpture on the RIGHT; do not reproduce a second large sculpture. Composition: wide landscape 3:2; detailed threads along upper edge and leftmost third, sparse thin contours reaching inward, very open transparent center and lower-right for HTML headline and buttons. Designed to sit on deep navy #071d2d, restrained silver reflections, no bright solid masses behind the text. True alpha transparency outside every filament and through openings, no opaque background, no haze or gradient rectangle. No text, no numbers, no letters, no logos, no UI, no particles or dots, no neon, no watermark. Standalone bitmap layer with crisp delicate edges.
