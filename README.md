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

### Actualizar una base ya instalada

`db/schema.sql` y `db/seed.sql` solo se cargan cuando se crea el volumen por
primera vez. Si la base ya existe y se quiere aplicar los cambios de esquema
(por ejemplo, los de un modulo nuevo), hay que correr la migracion a mano:

```bash
docker exec -i tgs_db mysql -uroot -p<password> bluetech_base < db/migracion.sql
```

(o con Compose: `docker compose exec -T db mysql -uroot -p<password> bluetech_base < db/migracion.sql`)

`db/migracion.sql` es idempotente: se puede correr las veces que haga falta
sin romper nada ni duplicar datos.

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
| `SMTP_HOST` | -- | Servidor SMTP. Si se deja vacio, el sitio intenta usar `mail()` de PHP |
| `SMTP_PUERTO` | `587` | Puerto del servidor SMTP |
| `SMTP_USUARIO` | -- | Usuario de SMTP (con autenticacion `LOGIN`) |
| `SMTP_CLAVE` | -- | Contrasena de SMTP |
| `SMTP_REMITENTE` | `SMTP_USUARIO` | Direccion From de los avisos |
| `SMTP_TLS` | `0` | `1` = TLS implicito (puerto 465) |
| `SMTP_PORTO_SMTP` | `1` | `1` = puerto 587 con `STARTTLS` |
| `SMTP_TIMEOUT` | `10` | Segundos de espera al conectar |

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
| Operador del centro de computos (menu) | `bash scripts/operador.sh` |
| Operador: una tarea puntual | `bash scripts/operador.sh estado` |
| Crear los roles de MySQL | `bash scripts/crear_roles_bd.sh` |
| Aplicar la migracion del esquema | `docker compose exec -T db mysql -uroot -p<clave> bluetech_base < db/migracion.sql` |
| Ver logs de la aplicacion | `docker compose logs -f app` |
| Ver logs de la base | `docker compose logs -f db` |

### El script operador

`scripts/operador.sh` reune en un solo lugar las tareas de administracion del
servidor que pide la catedra de Administracion de Sistemas Operativos. Se abre
con `bash scripts/operador.sh` (menu) o se llama por tarea
`bash scripts/operador.sh <tarea>`:

| Tarea | Que muestra o hace |
|---|---|
| `servicios` | Estado de Docker, SSH y cron, y las tareas programadas del usuario |
| `red` | Interfaces, puertos en escucha, rutas, DNS, redes de Docker y la IP de acceso |
| `procesos` | Carga del sistema, memoria, disco y procesos mas pesados |
| `respaldos` | Lista los respaldos de la base con su fecha y tamano |
| `restauracion` | Restaura un respaldo pedido por nombre (pide confirmacion) |
| `usuarios` | Usuarios del sistema y de MySQL, con los permisos de cada rol |
| `grupos` | Grupos del sistema y pertenencia de los usuarios |
| `origenes` | Bases, tablas y vistas de MySQL; crea bases y usuarios nuevos |
| `registros` | Logs del servidor web, de la base, del cron y auditoria de las reservas |
| `estado` | Resumen general del servidor |

### Los roles de la base de datos

`scripts/crear_roles_bd.sh` deja tres perfiles de acceso a MySQL, con los
permisos justos para cada caso:

| Rol | Permisos | Para que se usa |
|---|---|---|
| `tgs` | Todo sobre la base del proyecto | La aplicacion web (`config.php`) |
| `tgs_lectura` | Solo `SELECT` | Consultas de informe y auditoria: puede ver y no puede escribir |
| `tgs_admin` | Todo sobre la base, lectura de `mysql.*` | Administracion de la base desde el operador |

### Sobre la caida simulada

`caida.sh` ejecuta `docker compose down -v`. La `-v` borra tambien el volumen
`db_data`, que es donde viven los datos. Por eso hay que respaldar antes de
usarlo. Con `--suave` solo detiene los contenedores y conserva los datos.

---

## Modulos del sistema

### Cliente

| Pantalla | Archivo | Que hace |
|---|---|---|
| Panel del cliente | `panel_cliente.php` | Resumen de la cuenta: turnos, importe a pagar, vehiculos, presupuestos y avisos |
| Mis turnos | `reservas.php` | Reservar, reprogramar (con disponibilidad), editar y cancelar turnos; editar vehiculos |
| Mi perfil | `perfil.php` | Editar nombre, correo, telefono y cambiar la contrasena |
| Mis presupuestos | `presupuestos.php` | Ver las propuestas del taller y **aceptarlas o rechazarlas** |
| **Mis pagos** | `pagos_cliente.php` | Ver el saldo, los datos bancarios con QR, **reportar un pago** o **pagar con el saldo prepago** |
| **Historial de servicios** | `historial_cliente.php` | Servicios ya realizados por vehiculo: taller, fecha, detalle, costo y su factura |
| Dar de baja | `eliminar_cuenta.php` | Eliminar la cuenta y todos sus datos, con confirmacion y contrasena |

### Gestor y administrador

| Pantalla | Archivo | Que hace |
|---|---|---|
| Panel de administrador | `dashboard_admin.php` | Acceso a todos los modulos |
| Panel del gestor | `dashboard_gestor.php` | Resumen operativo del dia |
| Agenda de talleres | `agenda_gestor.php` | Grilla del dia: una fila por sede, una columna por franja, con edicion rapida en modal |
| Detalle del turno | `detalle_reserva.php` | Ficha completa del turno, con acciones (estado, reprogramacion, cobros) |
| Reservas | `administrar_reservas.php` | Listado, reprogramacion y cambio de estado |
| Pagos y facturacion | `administrar_pagos.php` | Registrar y anular cobros, ajustar el total, ver saldos |
| Presupuestos | `administrar_presupuestos.php` | Crear y editar propuestas con sus items |
| Avisos al cliente | `notificaciones.php` | Historial de avisos enviados y reenvio de los pendientes |
| **Pagos por validar** | `administrar_pagos.php` | Aprobar o rechazar los pagos que reportan los clientes; al aprobar se emite la factura |
| **Datos bancarios** | `datos_pago.php` | Publicar las cuentas con alias, CBU y QR que ve el cliente |
| **Vista de una sede** | `taller.php?id=N` | Informacion de la sede, marcas y servicios, horario, lugares libres y opciones de reservar o cancelar |
| Usuarios | `administrar_usuarios.php` | Alta de cuentas, cambio de rol, borrado y busqueda |
| Talleres | `administrar_talleres.php` | Sedes: horarios, capacidad, busqueda |
| Servicios | `administrar_servicios.php` | Catalogo de servicios con precio |
| Matriz de permisos | `permisos.php` | Editar que permisos tiene cada rol (solo administrador) |

### Logica de autorizacion

Los permisos viven en `auth.php`, en `tgs_matriz_permisos()`. Cada pagina
pide un permiso concreto con `verificarPermiso('...')` en lugar de una lista
de roles, asi que cambiar un permiso se hace en un solo lugar.

La matriz se puede **editar desde el panel** (`permisos.php`): lo que se
guarda ahi va a la tabla `permiso_rol` y tiene prioridad sobre los valores
por defecto del codigo. Un permiso sin fila guardada usa el valor por
defecto. Los permisos del rol administrador que hacen falta para administrar
el sistema no se pueden deshabilitar.

---

## Estructura

```
.
├── .env.example                         plantilla de variables (versionada)
├── .env                                 variables globales (generada, no versionada)
├── .env.local                           variables locales (generada, no versionada)
├── config.php                          cargador de variables y conexiones
├── conexion.php                        enlace PDO, usa config.php
├── auth.php                            sesion, roles y matriz de permisos
├── Dockerfile                          imagen php:8.4-apache
├── docker-compose.yml                  servicios app y db
│
├── # Paginas publicas
├── index.php                           portada institucional
├── login.php                           inicio de sesion
├── registro.php                        alta de cuenta
├── lang_config.php / lang_setup.php    carga de textos
│
├── # Paginas del cliente
├── panel_cliente.php                   panel personal
├── reservas.php                        turnos y vehiculos del cliente
├── perfil.php                          datos de la cuenta
├── presupuestos.php                    propuestas a aceptar o rechazar
├── eliminar_cuenta.php                 baja de cuenta
│
├── # Paginas de operacion
├── dashboard_admin.php / dashboard_gestor.php / dashboard.php
├── agenda_gestor.php                   agenda diaria por sede
├── detalle_reserva.php                 detalle de un turno
├── administrar_reservas.php            gestion de turnos
├── administrar_pagos.php               cobros y facturacion
├── administrar_presupuestos.php        propuestas de trabajo
├── administrar_usuarios.php            cuentas y roles
├── administrar_talleres.php            sedes
├── agregar_taller.php / editar_taller.php
├── administrar_servicios.php           catalogo de servicios
├── notificaciones.php                  avisos enviados al cliente
├── permisos.php                        matriz de permisos editable
├── error_403.php                       acceso no autorizado
│
├── # Logica compartida
├── correo.php                          envio de avisos (SMTP propio o mail)
├── funciones_pago.php                  recalculo del estado de cobro
├── taller_disponibilidad.php           horarios, franjas y capacidad
├── disponibilidad.php                  endpoint JSON de horarios libres
├── cancelar_reserva.php / procesar_reserva.php
│
├── db/
│   ├── schema.sql                      esquema, se carga al crear el volumen
│   ├── seed.sql                        datos iniciales y usuarios de prueba
│   └── migracion.sql                   cambios de esquema para bases existentes
├── scripts/
│   ├── _entorno.sh                     libreria compartida, precedencia de variables
│   ├── instalar.sh                     instalacion completa
│   ├── respaldar.sh                    mysqldump a backups/
│   ├── restaurar.sh                    restaura un .sql en el contenedor
│   ├── caida.sh                        baja total conservando el respaldo
│   └── estado.sh                       diagnostico del sistema
├── backups/                            respaldos .sql (no se versiona)
└── docs/
    ├── GUION-VIDEO.md                  guion de la demostracion
    ├── TGS-Documentacion.pdf           documentacion del proyecto
    └── TGS-Scripts-Guion.pdf           scripts del guion en PDF
```

---

## Base de datos

`db/schema.sql` crea quince tablas:

| Tabla | Para que sirve |
|---|---|
| `usuario` | Cuentas de acceso, con rol `admin`, `gestor` o `cliente` |
| `vehiculo` | Autos registrados por cada cliente |
| `taller` | Sedes, con direccion, telefono, mapa y capacidad simultanea |
| `taller_horario` | Dias y horas de atencion de cada sede |
| `servicio` | Catalogo de servicios, con precio y estado |
| `reserva` | Turnos reservados, con precio congelado y estado de cobro |
| `reserva_historial` | Auditoria de cambios sobre las reservas |
| `pago` | Cobros asociados a una reserva |
| `presupuesto` | Propuesta de trabajo enviada al cliente |
| `presupuesto_detalle` | Items de cada presupuesto |
| `notificacion` | Avisos enviados al cliente, con su estado de envio |
| `permiso_rol` | Permisos efectivos de cada rol, editables desde el panel |
| `datos_pago` | Cuentas bancarias (alias, CBU y QR) que ve el cliente para transferir |
| `factura` | Factura electronica emitida al cliente cuando un pago queda concretado |
| `taller_marca` | Marcas de vehiculo que atiende cada sede (filtro del enunciado) |
| `taller_servicio` | Tipos de servicio que ofrece cada sede (filtro del enunciado) |

`db/seed.sql` carga dos sedes con su horario, tres servicios, cinco usuarios
y una reserva de ejemplo con sus cobros. Se ejecuta automaticamente la
primera vez que se crea el volumen.

### Como se calcula el estado de cobro

`reserva.estado_pago` se recalcula en cada movimiento (`funciones_pago.php`):

- **pendiente**: no hay cobros vigentes.
- **parcial**: se cobro menos que el total.
- **pagado**: se cobro al menos el total (`reserva.precio_total`).

`reserva.precio_total` es el precio del servicio congelado en el momento de
reservar, asi un cambio en el catalogo no modifica lo que el cliente
acepto. Cuando el cliente acepta un presupuesto, ese total pasa a ser el
importe a cobrar.

### Como paga el cliente

1. Al reservar, el turno queda con `estado_pago = pendiente` y el precio
   congelado.
2. El cliente entra en **Mis pagos** (`pagos_cliente.php`), ve el saldo de
   cada turno y los datos bancarios con el codigo QR para transferir.
3. Reporta el pago que ya hizo (monto, metodo y comprobante). El cobro
   queda con estado `reportado` y **todavia no cuenta como pagado**.
4. El taller revisa la cola de **Pagos por validar**
   (`administrar_pagos.php`) y lo aprueba o lo rechaza. Al aprobarlo, el
   estado de cobro de la reserva se recalcula (pendiente / parcial / pagado).

Si el cliente paga en el local, el personal registra el cobro directamente
desde `administrar_pagos.php` o desde el detalle del turno.

### Disponibilidad y capacidad

Cada sede tiene un horario por dia de la semana (`taller_horario`) y una
capacidad de turnos simultaneos (`taller.capacidad`). Las franjas son de 60
minutos y arrancan en la hora de apertura. Un turno solo se puede tomar
dentro de la ventana de atencion y si la franja tiene lugar libre; la
validacion se hace siempre en el servidor, tanto al reservar como al
reprogramar.

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
- **Todos los formularios POST exigen un token CSRF** de sesion: el token se
  genera en `auth.php` (`csrfToken()`), viaja en un campo oculto y se valida
  con `validarCsrf()`. Si el token no es valido, la accion no se ejecuta.
  Aplica tanto a las pantallas nuevas como a las que ya existian (reservas,
  administracion de usuarios, de reservas y de servicios, alta y edicion de
  talleres, login, registro y panel del gestor).

### Pendiente

Estos puntos son conocidos y siguen sin resolverse:

- **`administrar_talleres.php` borra mediante GET** (`?eliminar=`), lo que
  hace el borrado vulnerable a CSRF. Hoy solo un administrador o gestor con
  el permiso `eliminar_taller` puede hacerlo.
- **`taller.mapa` se imprime sin sanitizar** en `index.php` y
  `administrar_talleres.php`, es decir, se guarda HTML de un iframe y se
  muestra tal cual. Es un XSS almacenado acotado, porque solo un
  administrador o gestor puede escribir ese campo.
- **No hay limitacion de intentos** en el inicio de sesion.
- **No hay recuperacion de contrasena.**

---

## Datos de la VM de desarrollo

| Dato | Valor |
|---|---|
| SO | Ubuntu 26.04.1 LTS |
| IP | Cambia segun la red (la ultima fue `192.168.1.21`) |
| Proyecto | `/home/taller/Desktop/tgs` |
| Sitio | `http://localhost:8080` |
| Docker | 29.1.3 |
| Compose | 2.40.3 |

> La IP de la VM puede cambiar cuando se reinicia o cuando el servidor de
> DHCP le asigna otra. El sitio se sigue viendo en `http://localhost:8080`
> desde adentro de la VM.
