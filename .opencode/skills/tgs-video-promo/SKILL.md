---
name: tgs-video-promo
description: Genera un video corto de promocion y defensa del sistema TGS (Taller Global de Servicios) a partir de capturas reales de la aplicacion, animadas con Motion (motion.dev) y narradas en espanol con la voz de Windows. Use when the user pide un video, una demo, un reel o una presentacion del proyecto TGS, capturar pantallas del sistema, o los temas promo, defensa, capturas, motion, video del proyecto TGS.
---

# Video de promocion del TGS (Taller Global de Servicios)

Pipeline completo: levanta la app **sin Docker**, captura las pantallas reales de
los tres roles, arma un video 16:9 de ~95 s con animaciones de Motion, narrado
con la voz de Windows en espanol, y lo encodea a MP4.

**El entregable es un MP4 estatico: una vez generado, la web no hace falta para
verlo.** La web solo se enciende durante la fase de capturas.

## Requisitos y como se resuelven en esta maquina

Esta maquina (Windows + XAMPP) **no tiene** Docker, Node, npm ni ffmpeg. El
pipeline los resuelve solo:

| Falta | Como lo resuelve el pipeline |
|---|---|
| Node/npm | Descarga Node portable (zip oficial) a la carpeta de trabajo, sin instalar nada |
| ffmpeg | `npm i ffmpeg-static` (trae el binario) |
| Navegador | Playwright **usando el Chrome ya instalado** (`playwright-core`, no descarga navegadores) |
| Base de datos | Clona la base local y la repara (ver fase 1) |
| Voz en off | `System.Speech` de Windows con la voz `Microsoft Helena Desktop` (es-ES) |

## Estructura de carpetas

```
$env:TEMP\tgs-video\
  tools\      node portable + esbuild
  work\       node_modules, scripts, bundle de Motion, app copiada
  app\        COPIA del proyecto TGS (el repo original NO se toca)
  capturas\   PNG 1920x1080 de cada pantalla
  audio\      WAV por escena + manifiesto.json + pista.wav
  frames\     f00000.jpg ... (cuadros del render)
  out\        tgs-video.mp4
```

## Fases

### 0. Preparar el entorno
```powershell
powershell -ExecutionPolicy Bypass -File .opencode/skills/tgs-video-promo/scripts/preparar-entorno.ps1
```
Descarga Node portable, instala `playwright-core motion ffmpeg-static esbuild` y
arma el bundle de Motion para el navegador.

> npm 11 bloquea los scripts de instalacion por defecto: hay que correr
> `npm install-scripts approve esbuild` (y `ffmpeg-static`) o el binario no baja.

### 1. Base de datos de capturas (clon reparado)
```powershell
& .opencode/skills/tgs-video-promo/scripts/preparar-base.php
```
Clona `bluetech_base` a `bluetech_capturas` con `mysqldump` y repara el clon.
**La base original no se toca.** El clon queda con:
- contrasenas bcrypt con claves conocidas (`Admin123!`, `Gestor123!`, `Cliente123!`),
- roles normalizados a `cliente` / `gestor` / `admin`,
- horarios semanales de las dos sedes, precios de catalogo reales, marcas y tipos de servicio,
- la tabla legacy `servicios` eliminada y la FK de `reserva` apuntando a `servicio`,
- turnos futuros en las franjas reales (08:30, 09:30, ... 17:30) para que la agenda se vea viva,
- un cobro con estado `reportado` para que el gestor tenga algo que aprobar.

#### Trampas de esta base (ya resueltas, no repetirlas)
1. El MySQL local es **MariaDB 10.4** y el dump queda en `utf8mb4_general_ci`,
   mientras que `db/schema.sql` usa `utf8mb4_unicode_ci`. Con esa diferencia el
   `CREATE TABLE` de `presupuesto` falla con *errno 150* (FK incompatible).
   Solucion: convertir todas las tablas del clon a `utf8mb4_unicode_ci`
   (primero soltando las FKs, despues reponiendolas desde `db/schema.sql`).
2. La base local es una copia vieja: las contrasenas estan en texto plano o con
   bcrypt de clave desconocida, `taller_horario` esta vacia, los servicios
   valen 0 y hay vehiculos duplicados. Por eso se clona y se repara.
3. Las franjas de reserva son de 60 minutos desde la hora de apertura
   (08:30 -> 08:30, 09:30, ... 17:30). Usar horas como 10:00 deja el turno
   desalineado en la grilla de la agenda.

### 2. Levantar la app
```powershell
powershell -ExecutionPolicy Bypass -File .opencode/skills/tgs-video-promo/scripts/levantar-servidor.ps1
```
Copia el codigo del repo a `work\app` y levanta `php -S 127.0.0.1:8099` con su
propio `.env` apuntando al clon.

> `config.php` **solo** lee `.env` y `.env.local` (no usa `getenv()`), asi que
> cambiar de base obliga a escribir un `.env.local`. Por eso se copia el codigo
> en vez de tocar el `.env.local` del repo.
> `php -S` ignora `.htaccess`: nunca publicar ese puerto, solo `localhost`.

### 3. Capturas
```powershell
node work\capturar.mjs
```
Inicia sesion con los tres roles y captura ~26 pantallas a 1920x1080.
Se puede(rs) dejar una pantalla en su estado mas interesante antes de capturarla
(por ejemplo, elegir fecha en `reservas.php` para que aparezcan los horarios
libres). Ver el bloque `PREPARAR` en `capturar.mjs`.

### 4. Guion y voz (el audio manda sobre el video)
```powershell
powershell -ExecutionPolicy Bypass -File ...\scripts\voz.ps1
```
Genera un WAV por escena y escribe `audio/manifiesto.json` con la duracion real
de cada una. **El timeline del video se arma despues, con esas duraciones**:
asi la voz y las imagenes quedan sincronizadas por construccion.

### 5. Render con Motion
```powershell
node work\render.mjs --fps=30
```
Abre `video.html` y captura un cuadro por frame. Cada cuadro se avanza con
`animacion.time = t`, que es lo que hace el render **determinista**: el mismo
frame sale siempre igual.

### 6. Audio y encode
```powershell
powershell -ExecutionPolicy Bypass -File ...\scripts\armar-audio.ps1
powershell -ExecutionPolicy Bypass -File ...\scripts\encodear.ps1
```

## Como usar Motion en este pipeline (lo que hay que saber)

- **Animar `transform` como string**, no con `x` / `y` / `scale` sueltos:
  ```js
  animate(el, { transform: ['translateX(120px) scale(.965)', 'translateX(0px) scale(1)'] },
          { duration: .85, ease: [0.16, 1, 0.3, 1], autoplay: false });
  ```
  En este entorno las transformaciones independientes de la build hybrid no se
  aplican (el `transform` queda en `none`), pero la API de string si, y es la
  que respeta el easing.
- `opacity` y `stagger` funcionan bien y son deterministas.
- `motionValue` + `animate` **no** actualiza al buscar con `.time`: no usarlo.
- Se necesita `autoplay: false` + `animacion.pause()` y despues `animacion.time = t`.
- El paquete `motion` no se puede cargar directo en el navegador (son ESM con
  especificadores sin resolver). Hay que empaquetarlo:
  `esbuild entrada.js --bundle --format=iife --global-name=Motion --outfile=motion.bundle.js`

## Editar el video sin rehacer todo

| Quiero cambiar... | Toco... |
|---|---|
| Los textos de pantalla | `scripts/video.html`, bloque `ESCENAS` |
| El guion de la voz | `scripts/guion.json` y vuelvo a correr `voz.ps1` + `render.mjs` + `encodear.ps1` |
| El encuadre de una captura | `ESCENAS[].foco` (porcentaje x/y dentro de la captura) |
| La duracion | El guion manda: acorto el texto, no el video |
| La musica de fondo | `encodear.ps1`: agregar `-i musica.mp3` y mezclar con `-filter_complex` |

## Errores frecuentes

- **`npm install` no baja el binario de esbuild/ffmpeg**: falta
  `npm install-scripts approve <paquete>`.
- **`powershell ... .ps1` no corre**: la politica de ejecucion bloquea los
  `.ps1`; usar `powershell -ExecutionPolicy Bypass -File ...`.
- **`php -S` se queda colgado**: es monohilo en Windows; las peticiones AJAX de
  la propia pagina se atienden en cola. No es un error, solo es mas lento.
- **Las capturas salen con la cabecera de la app cortada o con barras**: el
  recorte esta en el CSS de `.navegador .pantalla img` de `video.html`
  (`left`/`top` negativo = que parte de la captura de 1920x1080 se ve).
- **El video sale mudo**: falta correr `armar-audio.ps1` antes de `encodear.ps1`.
