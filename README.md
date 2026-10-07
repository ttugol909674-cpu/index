# TGS - Taller Global de Servicios

Sistema web de reservas de turnos para un taller mecanico, con interfaz en
espanol e ingles y tres roles de acceso: administrador, gestor y cliente.

El proyecto corre sobre PHP con Apache y MySQL, empaquetados en contenedores
de Docker Compose. Toda la configuracion sale de variables de entorno y la
base de datos se respalda y restaura con scripts.

---

## Requisitos

- Docker Engine y Docker Compose v2
- Linux (probado en Ubuntu 26.04 LTS) o Windows con Docker Desktop
- Para ejecutar los scripts: `bash`

Instalar Docker en Ubuntu:

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-v2
sudo systemctl enable --now docker
sudo usermod -aG docker $USER     # cerrar sesion y volver a entrar
```

---

## Instalacion

```bash
git clone <url-del-repositorio> tgs
cd tgs
bash scripts/instalar.sh
```

El script deja el sitio disponible en `http://localhost:8080`.

### Que hace `instalar.sh`

1. Verifica que Docker y Compose esten disponibles.
2. Crea `.env.local` con contrasenas propias de la maquina, si no existe.
3. Muestra la configuracion final que quedo aplicada.
4. Construye la imagen y levanta los contenedores `app` y `db`.
5. Espera a que MySQL responda.
6. Sincroniza la password del usuario de aplicacion si el volumen ya
   existia, y comprueba que el esquema y los usuarios esten cargados.

Es idempotente: se puede ejecutar las veces que haga falta.

---

## Variables de entorno

El proyecto usa dos archivos, con esta precedencia:

```
.env.local   (local,  no se versiona)   <-  gana
.env         (global, se versiona)
valores por defecto en config.php       <-  ultimo recurso
```

| Archivo | Ambito | Se versiona | Contenido |
|---|---|---|---|
| `.env.example` | -- | Si | Plantilla que documenta todas las variables |
| `.env` | Global | **No** | Valores globales de la maquina, con claves |
| `.env.local` | Local | **No** | Valores locales, con secretos |

Ni `.env` ni `.env.local` se versionan: los dos llevan claves. Un clon
nuevo no trae ninguno de los dos, y `instalar.sh` los genera a partir de
`.env.example` en el primer paso, asi que no hay que hacer nada a mano.

Para compartir configuración entre máquinas, editá `.env.example` (que sí
está en el repo) y corré `instalar.sh` de nuevo: los archivos existentes se
respetan, así que solo cambia lo que todavía no habías generado.

`config.php` es el unico lugar del proyecto que lee la configuracion. Aporta
`tgs_env('CLAVE')` para leer una variable, mas `tgs_pdo()` y `tgs_mysqli()`
para obtener conexiones ya configuradas.

### Variables disponibles

| Variable | Por defecto | Para que sirve |
|---|---|---|
| `APP_ENV` | `produccion` | Entorno de ejecucion |
| `APP_DEBUG` | `0` | Mostrar errores PHP en pantalla |
| `APP_PUERTO` | `8080` | Puerto del host donde se publica el sitio |
| `DB_HOST` | `db` | Host de MySQL. `db` dentro de Docker, `127.0.0.1` si se usa Apache nativo |
| `DB_NAME` | `bluetech_base` | Nombre de la base |
| `DB_USER` | `tgs` | Usuario de la base para la aplicacion |
| `DB_PASS` | -- | Contrasena del usuario de la base |
| `DB_ROOT_PASS` | -- | Contrasena de root, usada solo por los scripts |
| `DB_CHARSET` | `utf8mb4` | Codificacion de la conexion |
| `DB_PUERTO` | `3306` | Puerto de MySQL |
| `RESPALDO_CONSERVAR` | `5` | Cuantos respaldos se guardan antes de rotar |

> Para trabajar con Apache nativo en vez de Docker, crear `.env.local` con
> `DB_HOST=127.0.0.1` y las credenciales del MySQL local.

---

## Comandos

| Accion | Comando |
|---|---|
| Ver estado del sistema | `bash scripts/estado.sh` |
| Instalar o reinstalar | `bash scripts/instalar.sh` |
| Respaldar la base | `bash scripts/respaldar.sh` |
| Restaurar el ultimo respaldo | `bash scripts/restaurar.sh` |
| Restaurar uno puntual | `bash scripts/restaurar.sh tgs_20261001_002927.sql` |
| Simular la caida | `bash scripts/caida.sh` |
| Detener conservando datos | `bash scripts/caida.sh --suave` |
| Ver logs de la aplicacion | `docker compose logs -f app` |
| Ver logs de la base | `docker compose logs -f db` |

### Sobre la caida simulada

`caida.sh` ejecuta `docker compose down -v`. La `-v` borra tambien el volumen
`db_data`, que es donde viven los datos. Por eso hay que respaldar antes de
usarlo. Con `--suave` solo detiene los contenedores y conserva los datos.

---

## Estructura

```
.
├── .env.example                         plantilla de variables (versionada)
├── .env                                 variables globales (generada, no versionada)
├── .env.local                           variables locales (generada, no versionada)
├── config.php                          cargador de variables y conexiones
├── conexion.php                        enlace PDO, usa config.php
├── Dockerfile                          imagen php:8.4-apache
├── docker-compose.yml                  servicios app y db
├── db/
│   ├── schema.sql                      esquema, se carga al crear el volumen
│   └── seed.sql                        datos iniciales y usuarios de prueba
├── scripts/
│   ├── _entorno.sh                     libreria compartida, precedencia de variables
│   ├── instalar.sh                     instalacion completa
│   ├── respaldar.sh                    mysqldump a backups/
│   ├── restaurar.sh                    restaura un .sql en el contenedor
│   ├── caida.sh                        baja total conservando el respaldo
│   └── estado.sh                       diagnostico del sistema
├── backups/                            respaldos .sql (no se versiona)
└── docs/GUION-VIDEO.md                 guion de la demostracion
```

---

## Base de datos

`db/schema.sql` crea seis tablas:

| Tabla | Para que sirve |
|---|---|
| `usuario` | Cuentas de acceso, con rol `admin`, `gestor` o `cliente` |
| `vehiculo` | Autos registrados por cada cliente |
| `taller` | Sedes, con direccion, telefono y mapa |
| `servicio` | Catalogo de servicios, con precio y estado |
| `reserva` | Turnos reservados |
| `reserva_historial` | Auditoria de cambios sobre las reservas |

`db/seed.sql` carga dos sedes, tres servicios, cinco usuarios y una reserva de
ejemplo. Se ejecuta automaticamente la primera vez que se crea el volumen.

### Usuarios de prueba

| Rol | Correo | Contrasena |
|---|---|---|
| Administrador | `admin@bluetech.com` | `Admin123!` |
| Gestor | `santiiuyy12@gmail.com` | `Gestor123!` |
| Cliente | `carlos.gomez@gmail.com` | `Cliente123!` |
| Cliente | `mariana.silva@gmail.com` | `Cliente123!` |
| Cliente | `bysantiiuyy@gmail.com` | `Cliente123!` |

Son contrasenas de demostracion: hay que cambiarlas antes de usar el sistema
con datos reales. Las contrasenas se guardan como hash bcrypt.

---

## Problema resuelto: dos tablas de servicios

La base original tenia dos tablas de servicios: `servicio` (la que usa todo el
codigo, con columnas `nombre`, `precio` y `activo`) y `servicios` (una version
anterior, con `nombre_servicio` y `costo`). La clave foranea de `reserva`
apuntaba por error a la tabla vieja, de modo que un servicio creado desde el
panel no se podia reservar. Como ambas tenian los ids 1, 2 y 3, el error no se
notaba hasta que se creaba un servicio nuevo.

`db/schema.sql` deja una sola tabla `servicio` y la clave foranea de `reserva`
apuntando a ella.

Ademas, varias paginas detectaban columnas probando nombres en cascada porque
el esquema habia cambiado con el tiempo. Con el esquema unico eso ya no hace
falta.

---

## Problema resuelto: finales de linea

Los archivos editados en Windows entran con CRLF, y en Linux `git status` los
muestra todos como modificados aunque no haya ningun cambio real: el diff
muestra cada linea borrada y agregada de vuelta.

`.gitattributes` fuerza finales de linea LF en todo el repositorio. Con eso el
diff vuelve a mostrar solo los cambios verdaderos.

Para normalizar los archivos que ya estaban en CRLF:

```bash
git add --renormalize .
```

---

## Seguridad

- Las contrasenas se guardan como hash bcrypt, nunca en texto plano.
- Las consultas usan sentencias preparadas.
- Las cookies de sesion son `httponly` y `samesite`, y el identificador de
  sesion se regenera al iniciar sesion para evitar fijacion de sesion.
- `.env.local` y `backups/` estan en `.gitignore` y nunca se versionan.
- `.dockerignore` evita que los secretos entren en la imagen.

### Pendiente

Estos puntos son conocidos y siguen sin resolverse:

- **No hay tokens CSRF** en ningun formulario. Alguien podria induced a un
  administrador a borrar usuarios o reprogramar citas mediante un enlace
  externo.
- **`administrar_talleres.php` borra mediante GET** (`?eliminar=`), lo que
  hace el borrado vulnerable a CSRF.
- **`taller.mapa` se imprime sin sanitizar** en `index.php`, es decir, se
  guarda HTML de un iframe y se muestra tal cual. Es un XSS almacenado
  acotado, porque solo un administrador o gestor puede escribir ese campo.
- **No hay limitacion de intentos** en el inicio de sesion.
- **No hay recuperacion de contrasena.**

---

## Datos de la VM de desarrollo

| Dato | Valor |
|---|---|
| SO | Ubuntu 26.04.1 LTS |
| IP | 192.168.1.19 (red bridge) |
| Proyecto | `/home/taller/Desktop/tgs` |
| Sitio | `http://localhost:8080` |
| Docker | 29.1.3 |
| Compose | 2.40.3 |
