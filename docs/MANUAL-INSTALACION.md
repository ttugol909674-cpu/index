# Manual de instalación y operación — TGS (Taller Global de Servicios)

**Proyecto de Pasaje de Grado · 3° ME - BT · UTU DGETP - Escuela Técnica de Pando**

Este es el manual que exige la cátedra de Administración de Sistemas Operativos:
*"Manual de instalación y ejecución de scripts, configuración de entorno,
configuración de servicios. Deberá incluir un script de instalación."*

Todo lo que está acá se comprobó sobre la máquina virtual real del proyecto
(Ubuntu 26.04 LTS en VirtualBox, con Docker y Docker Compose).

---

## 1. Requisitos

| Componente | Mínimo | recomendado | Para qué |
|---|---|---|---|
| VirtualBox | 7.0 | 7.1 | Máquina virtual |
| Sistema operativoGuest | Ubuntu 22.04 LTS | Ubuntu 26.04 LTS | Servidor |
| CPU / RAM asignada | 2 vCPU / 4 GB | 4 vCPU / 8 GB | Docker + MySQL + compilación |
| Disco virtual | 40 GB | 60 GB | Sistema, contenedores y respaldos |
| Red | Adaptador puente o NAT con reenvío | | Acceso desde la red local |
| Docker | 26 | 29 | Contenedores |
| Docker Compose | v2.20 | v2.40 | Orquestación de los dos contenedores |

Verificación en el sistema anfitrión:

```bash
VBoxManage --version
docker --version
docker compose version
```

---

## 2. Instalación desde cero

### 2.1 Preparar la máquina virtual

1. En VirtualBox, **Nueva → Máquina virtual**:
   - Tipo: Linux / Ubuntu (64 bits)
   - Memoria: 4096 MB (2048 MB como mínimo)
   - Procesadores: 2 vCPU
   - Disco: 50 GB (VDI dinámico)
   - Red: **Adaptador puente** (para que la VM sea alcanzable desde la red
     local) o NAT con reenvío de puertos
2. Instalar Ubuntu 26.04 LTS.
3. Crear el usuario `taller` con permisos de administrador (`sudo`).
4. Actualizar el sistema:

```bash
sudo apt update && sudo apt upgrade -y
```

### 2.2 Instalar Docker

```bash
sudo apt install -y docker.io docker-compose-v2
sudo systemctl enable --now docker
sudo usermod -aG docker $USER      # para usar docker sin sudo
newgrp docker                        # o cerrar y volver a iniciar sesion
```

Verificación: `docker run --rm hello-world`

### 2.3 Copiar el proyecto

```bash
mkdir -p ~/Desktop
cd ~/Desktop
# desde la maquina del proyecto:
#   scp -r tgs usuario@<ip-vm>:~/Desktop/
```

### 2.4 Instalación automática (un solo comando)

```bash
cd ~/Desktop/tgs
bash scripts/instalar.sh
```

**Qué hace `instalar.sh`:**

1. Verifica que Docker y Compose estén instalados y encendidos.
2. Genera `.env` y `.env.local` si no existen, con contraseñas nuevas
   para la base de datos.
3. Levanta los dos contenedores (`tgs_app` y `tgs_db`).
4. Espera a que MySQL acepte conexiones (hasta 80 segundos).
5. Carga el esquema (`db/schema.sql`) y los datos de prueba
   (`db/seed.sql`) **solo si la base estaba vacía**.
6. Ajusta los permisos de `.env` y `.env.local` para que el servidor web
   (usuario `www-data` dentro del contenedor) los pueda leer.
7. Verifica la instalación: comprueba que el sitio responde y que la base
   tiene las 16 tablas.

Si la base ya tiene datos, el paso 5 no se ejecuta: MySQL solo corre los
scripts de inicialización la primera vez que se crea el volumen. Para
actualizar el esquema de una instalación existente se usa
`db/migracion.sql` (ver la sección 5.2).

### 2.5 Verificación de la instalación

```bash
bash scripts/estado.sh
```

La salida esperada es:

```
  Base de datos: ACTIVA  (bluetech_base en el contenedor tgs_db)
    usuarios registrados : 5
    reservas registradas : 2
  Sitio web: RESPONDIENDO  http://localhost:8080  (HTTP 200)
  Ultimo respaldo: ...
```

Y desde el navegador: `http://<ip-vm>:8080`.

---

## 3. Configuración del entorno

La configuración vive en dos archivos, con esta precedencia:

| Archivo | Se versiona | Qué contiene |
|---|---|---|
| `.env` | Sí (plantilla con valores de ejemplo) | Variables globales del proyecto |
| `.env.local` | **No** (está en `.gitignore`) | Valores reales de esta máquina |

### 3.1 Variables de entorno

| Variable | Por defecto | Qué hace |
|---|---|---|
| `APP_ENV` | `produccion` | Modo de la aplicación |
| `APP_DEBUG` | `0` | `1` muestra errores PHP en pantalla (solo para depurar) |
| `APP_PUERTO` | `8080` | Puerto del host donde se publica la aplicación |
| `DB_HOST` | `db` | Nombre del servicio de base de datos en Docker |
| `DB_NAME` | `bluetech_base` | Nombre de la base |
| `DB_USER` / `DB_PASS` | `tgs` / generado | Usuario de la aplicación (sin permisos administrativos) |
| `DB_ROOT_PASS` | generado | Clave de `root`, solo para respaldos y restauración |
| `DB_CHARSET` | `utf8mb4` | Codificación de la base |
| `RESPALDO_CONSERVAR` | `5` | Cuántos respaldos locales se conservan |
| `RESPALDO_REMOTO` | vacío | Destino remoto: `usuario@servidor:/ruta` |
| `RESPALDO_REMOTO_LLAVES` | vacío | Clave privada de SSH para el respaldo remoto |
| `REGISTRO_ABIERTO` | `1` | `0` cierra el registro de cuentas nuevas |
| `REGISTRO_CODIGO` | vacío | Código obligatorio para registrarse (si está vacío, no se pide) |
| `SMTP_HOST`, `SMTP_PUERTO`, `SMTP_USUARIO`, `SMTP_CLAVE`, `SMTP_REMITENTE` | vacío | Correo de los avisos al cliente (si queda vacío se usa `mail()`) |
| `SMTP_TLS` | `0` | `1` = TLS implícito (puerto 465) |
| `SMTP_PORTO_SMTP` | `1` | `1` = puerto 587 con `STARTTLS` |
| `SMTP_TIMEOUT` | `10` | Segundos de espera al conectar |

### 3.2 Cambiar una variable

```bash
cd ~/Desktop/tgs
nano .env.local            # o cualquier editor
# Verificar que la app sigue leyendo bien:
bash scripts/estado.sh
```

Los archivos deben quedar en `644`: el servidor web corre como `www-data`
dentro del contenedor y tiene que poder leerlos.

---

## 4. Configuración de los servicios

### 4.1 Servicios de Docker

| Contenedor | Imagen | Puerto | Función |
|---|---|---|---|
| `tgs_app` | `php:8.4-apache` | `8080` en el host → 80 en el contenedor | Sitio web (Apache + PHP 8.4) |
| `tgs_db` | `mysql:8.4` | 3306 (interno) | Base de datos, con *healthcheck* |

```bash
docker compose ps                      # estado
docker compose logs -f app             # log del sitio
docker compose logs -f db              # log de la base
docker compose restart app             # reiniciar solo el sitio
docker compose down                    # detener (conserva los datos)
docker compose down -v                 # detener y BORRAR el volumen de datos
```

### 4.2 Tareas programadas (cron)

| Tarea | Cuándo | Qué hace |
|---|---|---|
| `17 3 * * *` | Todos los días a las 3:17 | Respaldo automático de la base (`scripts/respaldar.sh`) |
| `*/2 * * * *` | Cada 2 minutos | Vigilante del túnel: publica la URL vigente en `url-publica.txt` |
| `@reboot` | Al arrancar la VM | Levanta el túnel de publicación |

```bash
crontab -l                 # ver las tareas
sudo crontab -u taller -l  # (con privilegios)
tail -f backups/cron.log   # ver el registro
```

### 4.3 Firewall (iptables)

El script `scripts/firewall.sh` deja el servidor con la entrada en `DROP`:
solo se acepta el bucle local, el tráfico ya establecido, el ping, las redes
de Docker y el acceso por SSH y al sitio **únicamente desde la red local**.
Los puertos que Docker publica (el 8080) se filtran en la cadena
`DOCKER-USER`, que es la única que Docker no administra.

```bash
sudo bash scripts/firewall.sh aplicar    # aplica y guarda las reglas
sudo bash scripts/firewall.sh mostrar     # reglas vigentes
sudo bash scripts/firewall.sh revertir   # vuelve a las reglas anteriores
```

Las reglas se guardan en `/etc/iptables/rules.v4` y el servicio
`tgs-firewall.service` las restaura al arrancar el servidor. El script tiene
un temporizador de seguridad: si la conexión se corta durante la aplicación,
restaura solo las reglas previas.

### 4.4 Servicio del túnel de publicación

```bash
systemctl status tgs-tunel.service      # estado
sudo systemctl restart tgs-tunel.service # reiniciar (cambia la URL)
journalctl -u tgs-tunel -n 20            # ver el registro
```

### 4.5 Actualizaciones de seguridad

El sistema tiene `unattended-upgrades` habilitado: las actualizaciones de
seguridad se aplican solas.

```bash
systemctl status unattended-upgrades
grep APT::Periodic /etc/apt/apt.conf.d/20auto-upgrades
```

---

## 5. Ejecución de los scripts

Todos los scripts están en `scripts/` y se ejecutan desde la raíz del
proyecto.

| Script | Permisos | Qué hace | Cuándo usarlo |
|---|---|---|---|
| `instalar.sh` | usuario | Instala y verifica todo el sistema | Primera vez, o para reinstalar |
| `estado.sh` | usuario | Estado de contenedores, base, sitio y respaldos | Antes y después de cada paso de la demo |
| `respaldar.sh` | usuario | Genera un respaldo de la base y lo envía al destino remoto si está configurado | A diario (ya programado) o antes de un riesgo |
| `restaurar.sh` | usuario | Restaura un respaldo: borra el volumen y reimporta los datos | Cuando la base se corrompió o hay que volver a un estado anterior |
| `caida.sh` | usuario | **Simula la caída total** del servidor (borra el volumen) | Para demostrar la política de recuperación en la defensa |
| `operador.sh` | usuario (sudo para algunas tareas) | Menú del operador de centro de cómputos: servicios, red, procesos, respaldos, restauración, usuarios, grupos, orígenes de datos y registros | Cuando hay que administrar el servidor en vivo |
| `crear_roles_bd.sh` | usuario | Crea los roles de MySQL (`tgs`, `tgs_lectura`, `tgs_admin`) | Al instalar, o si se cambian los permisos de la base |
| `firewall.sh` | **sudo** | Configura las reglas de iptables | Al cambiar la red o el servidor |
| `tunel.sh` | usuario | Levanta el túnel de publicación temporal | Cuando se quiere publicar el sitio |
| `tunel_nombrado.sh` | usuario | Túnel con nombre permanente (URL que no cambia) | Cuando hay un dominio en Cloudflare |
| `tunel_vigilante.sh` | usuario | Publica la URL vigente en `url-publica.txt` | Está programado cada 2 minutos |

### 5.1 Ejemplos de uso

```bash
# Ver el estado general
bash scripts/estado.sh

# Respaldar y ver el resultado
bash scripts/respaldar.sh

# Restaurar el último respaldo
bash scripts/restaurar.sh

# Restaurar uno puntual
bash scripts/restaurar.sh tgs_20261020_031700.sql

# Simular la caída y recuperar el servicio
bash scripts/caida.sh
bash scripts/restaurar.sh

# Operador: ver la red del servidor
bash scripts/operador.sh red

# Operador: auditar los registros de las reservas
bash scripts/operador.sh registros auditoria

# Publicar el sitio y ver la URL
bash scripts/tunel.sh iniciar
bash scripts/tunel.sh estado
```

### 5.2 Actualizar el esquema de una base existente

```bash
docker compose exec -T db mysql -uroot -p<clave-root> bluetech_base < db/migracion.sql
```

`db/migracion.sql` es **idempotente**: se puede aplicar todas las veces que
haga falta sin romper nada (crea tablas con `IF NOT EXISTS` y agrega columnas
solo si no existen). `db/vistas.sql` aplica las vistas para los reportes.

### 5.3 Respaldos

- Los respaldos quedan en `backups/` con nombre `tgs_AAAAMMDD_HHMMSS.sql`.
- Se conservan los 5 más recientes (configurable con `RESPALDO_CONSERVAR`).
- El permiso del archivo es `600`: solo el usuario del sistema lo puede leer.
- **Respaldo remoto**: se configura con `RESPALDO_REMOTO=usuario@servidor:/ruta`
  en `.env.local`. El script copia el archivo con `rsync` (o `scp` si `rsync`
  no está instalado) sin borrar la copia local.

```bash
# En .env.local:
RESPALDO_REMOTO=root@192.168.1.30:/var/backups/tgs
RESPALDO_REMOTO_LLAVES=/home/taller/.ssh/id_ed25519
```

Para restaurar desde el servidor remoto, primero se baja el archivo y luego
se usa `restaurar.sh` con el nombre del archivo.

---

## 6. Entrega de la máquina virtual (.OVA)

La imagen exportada es **`basedatos.ova`** (unos 7 GB), con el sistema
completo: Docker, los contenedores, la base con los datos de demostración,
los scripts, el firewall configurado y el servicio del túnel.

### 6.1 Importar

1. VirtualBox → **Archivo → Importar appliance virtual** → `basedatos.ova`.
2. Configuración de red: **Adaptador puente**, para que la VM sea
   alcanzable desde la red local.
3. Iniciar la VM e iniciar sesión con el usuario `taller`.
4. El sitio queda en `http://<ip-nueva>:8080`.

### 6.2 Datos de acceso de la imagen

| Dato | Valor |
|---|---|
| Usuario del sistema | `taller` |
| Contraseña del sistema | `2811` |
| Base de datos | `bluetech_base` (contenedor `tgs_db`) |
| Clave de la base | la que figura en `.env.local` de la imagen |
| Sitio web | `http://<ip>:8080` |

Las claves de las cuentas de demostración **no se incluyen en el
repositorio**: se entregan por separado (ver el Anexo A del documento del
proyecto).

### 6.3 Comprobar la imagen recién importada

```bash
cd ~/Desktop/tgs
bash scripts/estado.sh          # contenedores, base y sitio
bash scripts/operador.sh estado # resumen del servidor
docker compose ps
```

Si la IP de la VM cambia al importarla, se actualiza en el `.env.local` o se
deja que el túnel sepublique de nuevo con `bash scripts/tunel.sh iniciar`.

---

## 7. Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| `Cannot connect to the Docker daemon` | El demonio no está activo | `sudo systemctl start docker` |
| El sitio no responde en el puerto 8080 | Los contenedores están caídos | `bash scripts/estado.sh` y `docker compose up -d` |
| `Access denied for user` en la base | `.env.local` con permisos restrictivos | `chmod 644 .env.local` |
| La página muestra errores PHP | `APP_DEBUG=1` está activo o falta un archivo | `APP_DEBUG=0` y `bash scripts/estado.sh` |
| `Login falló` después de cambiar una clave | La base tiene el hash viejo | `docker compose exec -T db mysql -uroot -p<clave> bluetech_base < db/migracion.sql` |
| El túnel cambia de URL | Se reinició el servicio | La URL vigente está en `http://<ip>:8080/url-publica.txt` |
| `unmount /dev/...` o la base no levanta | El volumen quedó corrupto | `bash scripts/restaurar.sh` |
| No se entra por SSH después del firewall | Reglas demasiado restrictivas | `sudo bash scripts/firewall.sh revertir` |
| El registro pide un código | `REGISTRO_CODIGO` está configurado | Vaciar la variable o usar el código del administrador |

---

## 8. Verificación final de la instalación

```bash
# 1. Estado del sistema
bash scripts/estado.sh

# 2. La suite completa de pruebas (28 suites)
bash /tmp/run_suite.sh

# 3. Sintaxis de los 42 archivos PHP
bash /tmp/lint_vm.sh

# 4. Reglas del firewall
sudo bash scripts/firewall.sh mostrar

# 5. El sitio publicado
bash scripts/tunel.sh estado
```

Con los cinco pasos en verde, la instalación está completa y documentada.
