# Guion del video - TGS (Taller Global de Servicios)

Demostracion de caida y recuperacion del servidor con Docker, variables de
entorno y respaldo de la base de datos.

**Duracion estimada:** 8 a 12 minutos.

---

## Antes de grabar

- VirtualBox abierta con la VM `basedatos` encendida (Ubuntu 26.04.1 LTS).
- Terminal de Ubuntu abierta. Todos los comandos se ejecutan en
  `/home/taller/Desktop/tgs`.
- Navegador abierto en `http://localhost:8080`.
- Todo debe estar en **pantalla completa** y con la fuente grande.
- Grabar audio y video por separado suele dar mejor resultado que la
  camara capturing la pantalla. Si se puede, usar grabacion de pantalla.

> La VM debe seguir **encendida** durante toda la grabacion. Si se apaga o
> se reinicia, la IP puede cambiar y el sitio deja de estar en
> `localhost:8080`.

---

## Bloque 1 - Presentacion (30 segundos)

**Que decir:**

> "Este es el proyecto TGS, un sistema web de reservas de turnos para un
> taller mecanico. Esta corriendo en una maquina virtual con Ubuntu y lo
> vamos a mostrar dentro de contenedores de Docker. En el video voy a
> mostrar como el servidor arranca, como se cae, y como lo levanto de nuevo
> con los scripts, incluyendo la base de datos."

---

## Bloque 2 - Mostrar el servidor funcionando (1 minuto)

**Comandos:**

```bash
cd /home/taller/Desktop/tgs
bash scripts/estado.sh
```

**Que deberia verse:** los dos contenedores `tgs_app` y `tgs_db` en estado
Up, la base de datos ACTIVA con 5 usuarios, y el sitio web RESPONDIENDO con
HTTP 200.

**Que decir:**

> "Aca esta el servidor funcionando. Tengo dos contenedores: `tgs_app` que
> corre Apache con PHP, y `tgs_db` que corre MySQL. El script `estado.sh`
> me dice que la base tiene 5 usuarios y que el sitio responde."

**Despues, en el navegador:** abrir `http://localhost:8080`, hacer login con
`admin@bluetech.com` / `Admin123!` y entrar al panel de administrador.

**Que decir:**

> "El panel de administrador carga bien, con los datos que vienen de la base
> de datos dentro del contenedor."

---

## Bloque 3 - Explicar las variables de entorno (1 minuto)

**Comandos:**

```bash
cat .env
echo "---"
cat .env.local
```

**Que decir:**

> "El proyecto separa la configuracion en dos archivos. `.env` tiene las
> variables **globales**: son las que comparte todo el equipo y estan
> versionadas en git, con los valores que funcionan en cualquier maquina.
> `.env.local` tiene las variables **locales**: las crea el script de
> instalacion para esta maquina en particular, con sus propias contrasenas,
> y no se versiona nunca porque tiene secretos.
>
> Cuando las dos coinciden, el archivo local le gana al global. Eso lo
> resuelve `config.php`, que es el unico lugar donde el proyecto lee la
> configuracion. Antes las credenciales estaban escritas en el codigo, en
> `conexion.php` y en otras cuatro paginas; ahora ninguna."

Para reforzar el punto, se puede mostrar que `.env.local` esta ignorado por
git:

```bash
git check-ignore -v .env.local
```

Debe responder algo como `.gitignore:8:.env.local`.

---

## Bloque 4 - Generar el respaldo (1 minuto)

**Comando:**

```bash
bash scripts/respaldar.sh
```

**Que deberia verse:** `[OK] Respaldo generado: backups/tgs_2026XXXX_XXXXXX.sql`
con el tamano y la cantidad de tablas.

**Que decir:**

> "Antes de romper nada, genero el respaldo. El script saca un `mysqldump`
> del contenedor de MySQL y lo guarda como archivo `.sql` con la fecha y la
> hora en el nombre. Este archivo es el que va a permitir recuperar los
> datos despues de la caida."

---

## Bloque 5 - Simular la caida del servidor (1 minuto)

**Comando:**

```bash
bash scripts/caida.sh
```

Confirmar escribiendo `si` cuando lo pida.

**Que deberia verse:** `Volume tgs_db_data Removed` y el mensaje de que el
sitio ya no responde.

**Que decir:**

> "Ahora simulo la caida. El script ejecuta `docker compose down -v`. La `-v`
> es la parte importante: ademas de borrar los contenedores, borra el
> volumen, que es donde estaban los datos. Es el equivalente a que se
> hubiera perdido el servidor entero con su base de datos."

**Verificar la caida:**

```bash
curl -s -o /dev/null -w 'HTTP %{http_code}\n' --max-time 5 http://localhost:8080/
```

Debe devolver `HTTP 000`. En el navegador, recargar y mostrar que sale
**"No se puede conectar"** o similar.

**Que decir:**

> "Como se ve, el sitio ya no responde. El codigo HTTP 000 significa que no
> hay nada escuchando. La base de datos se fue con el volumen."

---

## Bloque 6 - Reinstalar con los scripts (2 minutos)

**Comando:**

```bash
bash scripts/instalar.sh
```

**Que deberia verse**, en orden:

1. La version de Docker y Compose.
2. ` .env.local ya existe, se respeta` (o "creado" si se borro antes).
3. Los valores de configuracion que quedaron aplicados, con la contrasena
   oculta.
4. La construccion de la imagen.
5. Los contenedores levantandose, con `tgs_db Healthy`.
6. `MySQL responde`.
7. `Instalacion completada` con la direccion del sitio.

**Que decir:**

> "Con un solo comando el script hace la instalacion completa. Primero
> verifica que Docker este disponible. despues carga las variables
> globales y las locales, y si el archivo local no existe lo crea con
> contrasenas generadas para esta maquina. A continuacion construye la
> imagen, levanta los dos contenedores, y espera a que MySQL este listo
> usando el healthcheck. Finalmente comprueba que el esquema y los
> usuarios esten cargados."

**Opcional, para mostrar el healthcheck:**

```bash
docker compose ps
```

**Que decir:**

> "El contenedor de la base tiene que quedar en estado *healthy* antes de
> que la aplicacion arranque. Eso lo define el `healthcheck` en el
> `docker-compose.yml`, y es lo que evita que la aplicacion se caiga
> trying de conectarse a una base que todavia no esta lista."

---

## Bloque 7 - Restaurar el respaldo (1 minuto)

**Comando:**

```bash
bash scripts/restaurar.sh
```

Confirmar escribiendo `si`.

**Que deberia verse:** el nombre del respaldo que se restaura, y al final
`Tablas en la base: 6` y `Usuarios cargados: 5`.

**Que decir:**

> "Ahora restauro el respaldo. Si no le paso ningun argumento, el script
> agarra el mas reciente. Vacia la base y vuelve a cargar el `.sql` dentro
> del contenedor. Al final me confirma quantas tablas y cuantos usuarios
> quedaron."

---

## Bloque 8 - Verificacion final (1 minuto)

**Comandos:**

```bash
bash scripts/estado.sh
```

**Que deberia verse:** contenedores Up, base ACTIVA con 5 usuarios,
sitio RESPONDIENDO HTTP 200, y el ultimo respaldo disponible.

En el navegador: recargar `http://localhost:8080`, hacer login con
`admin@bluetech.com` / `Admin123!`.

**Que decir:**

> "El sistema quedo funcionando otra vez, con los mismos datos que tenia
> antes de la caida. Los mismos 5 usuarios, la misma base, el mismo
> panel. Eso es exactamente lo que pide el enunciado: instalar el
> respaldo actualizado con la base de datos y que todo vuelva a
> funcionar."

---

## Bloque 9 - Cierre (20 segundos)

**Que decir:**

> "En resumen: el servidor arranca con `instalar.sh`, se respalda con
> `respaldar.sh`, se rompe con `caida.sh` y se recupera con
> `instalar.sh` mas `restaurar.sh`. Toda la configuracion sale de las
> variables de entorno, separadas en globales y locales. Gracias."

---

## Comandos rapidos (chuleta para tener a mano)

| Accion | Comando |
|---|---|
| Ver estado | `bash scripts/estado.sh` |
| Instalar / reinstalar | `bash scripts/instalar.sh` |
| Respaldar | `bash scripts/respaldar.sh` |
| Restaurar (el ultimo) | `bash scripts/restaurar.sh` |
| Restaurar (uno puntual) | `bash scripts/restaurar.sh tgs_20261001_002927.sql` |
| Provocar la caida | `bash scripts/caida.sh` |
| Ver logs de la app | `docker compose logs -f app` |
| Ver logs de la base | `docker compose logs -f db` |
| Entrar a MySQL | `docker compose exec db mysql -u root -p` |

## Credenciales de la demo

| Rol | Correo | Contrasena |
|---|---|---|
| Administrador | `admin@bluetech.com` | `Admin123!` |
| Gestor | `santiiuyy12@gmail.com` | `Gestor123!` |
| Cliente | `carlos.gomez@gmail.com` | `Cliente123!` |

## Problemas frecuentes durante la grabacion

| Sintoma | Causa | Solucion |
|---|---|---|
| El sitio no abre en `localhost:8080` | La VM se reinicio y cambio la IP | Reencender la VM y esperar a que arranque |
| `Cannot connect to the Docker daemon` | Docker no arranco tras el inicio de Ubuntu | `sudo systemctl start docker` |
| El puerto 8080 esta ocupado | Quedo un contenedor previo | `docker compose down` |
| `Access denied for user 'tgs'` | El volumen tiene una contrasena vieja | `bash scripts/instalar.sh` (el paso 6 lo corrige solo) |
