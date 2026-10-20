# Fuente del documento del proyecto (PDF)

Esta carpeta contiene el **origen editable** del documento `../TGS-Proyecto-Pasaje-Grado.pdf`
(63 páginas) que se entrega en la segunda y tercera entrega.

| Archivo | Contenido |
|---|---|
| `p1.html` | Carátula (APA), hoja de estilos, índice y capítulos 1 a 3 |
| `p2.html` | Capítulo 4 (requisitos) y capítulo 5 (diseño y diagramas UML) |
| `p3.html` | Capítulo 6 (base de datos) y capítulo 7 (implementación con capturas) |
| `p4.html` | Capítulos 8 a 14 y anexos |

## Cómo regenerar el PDF

1. **Diagramas:** los siete diagramas están escritos en notación Mermaid dentro del
   propio HTML. Para renderizarlos hay que tener `mermaid.min.js` en la misma carpeta
   (se descarga de https://cdnjs.cloudflare.com/ajax/libs/mermaid/10.9.1/mermaid.min.js).
2. **Capturas:** las 15 figuras son capturas reales del sistema. Se vuelven a tomar
   iniciando sesión en la aplicación y guardando la imagen de cada pantalla en
   una carpeta `img/` con los nombres que usa el HTML (`19_panel_admin.png`,
   `05_panel_cliente.png`, `08_pagos_cliente.png`, etc.).
3. **Impresión:** concatenar los cuatro archivos en un único `index.html` (el `p1.html`
   va primero y el `p4.html` al final, con el `<script>` de Mermaid antes de
   `</body>`) y generar el PDF con:

   ```
   msedge --headless=new --print-to-pdf=documento.pdf --no-pdf-header-footer ^
           --virtual-time-budget=60000 index.html
   ```

## Notas

- Los diagramas y las capturas son **vectoriales y reales**: no son imágenes de
  adorno, se generan desde el código y desde el sistema en funcionamiento.
- El índice lleva los números de página reales, que se calculan midiendo la
  paginación del documento renderizado (el contenido se arma con un bloque
  `<div class="capitulo">` por capítulo, y cada capítulo empieza en página nueva).
- Si se edita el contenido, hay que volver a calcular la paginación y actualizar
  los números del índice antes de imprimir el PDF final.
