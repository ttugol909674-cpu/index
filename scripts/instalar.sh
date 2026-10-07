#!/usr/bin/env bash
# =====================================================================
# TGS - Instalacion completa del proyecto
#
# Que hace, en orden:
#   1. Verifica que Docker este disponible
#   2. Crea las variables LOCALES (.env.local) a partir de la plantilla
#   3. Muestra la configuracion final mezclando global + local
#   4. Construye la imagen y levanta los contenedores
#   5. Espera a que MySQL este listo
#   6. Se asegura de que existan el esquema y los usuarios
#
# Es idempotente: se puede volver a ejecutar sin romper nada.
# =====================================================================
set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/_entorno.sh"

cd "$TGS_RAIZ"

echo "==========================================================="
echo " TGS - Instalacion del proyecto"
echo "==========================================================="
echo

echo "[1/6] Verificando Docker"
tgs_verificar_docker
echo

echo "[2/6] Variables de configuracion"

# MySQL graba las claves en el volumen cuando se crea, y no las vuelve a
# leer. Si el volumen ya existe pero .env.local no, generar claves nuevas
# deja la base inaccesible, asi que se avisa ANTES de tocar nada.
if [ -f "$TGS_RAIZ/.env" ] && [ ! -f "$TGS_RAIZ/.env.local" ] \
   && docker volume ls -q | grep -q .; then
    if docker compose ps -q db 2>/dev/null | grep -q .; then
        cat >&2 <<'AVISO'

[AVISO] Hay un volumen de datos de una instalacion anterior, pero no
        existe .env.local. Se van a generar claves nuevas que MySQL no
        va a reconocer, porque las del volumen quedaron fijadas al
        crearse.

        Si querias conservar los datos, cancela con Ctrl+C y restituye
        el .env.local anterior. Si querias empezar de cero, segui y
        despues borra el volumen con:  bash scripts/caida.sh

AVISO
    fi
fi

# El .env global no se versiona: en un clone nuevo no existe y hay que
# generarlo antes de poder leer nada.
tgs_crear_env_global
tgs_cargar_entorno
tgs_crear_env_local

# Aunque .env.local ya existiera, sus permisos pueden haber quedado en 600
# de una corrida anterior y Apache no podria leerlo dentro del contenedor.
tgs_ajustar_permisos_env_local

# Hay que volver a cargar: recien creados, .env y .env.local ya traen
# los valores definitivos de esta maquina.
tgs_cargar_entorno

echo "       .env        (global)  -> presente"
echo "       .env.local  (local, prioritaria)  -> $TGS_RAIZ/.env.local"
echo
echo "       Valores que queden aplicados:"
printf "         DB_HOST      = %s\n" "$DB_HOST"
printf "         DB_NAME      = %s\n" "$DB_NAME"
printf "         DB_USER      = %s\n" "$DB_USER"
printf "         APP_PUERTO   = %s\n" "$APP_PUERTO"
printf "         APP_ENV      = %s\n" "$APP_ENV"
echo "         DB_PASS      = ********  (oculta a proposito)"
echo

echo "[3/6] Construyendo la imagen de la aplicacion"
docker compose build
echo

echo "[4/6] Levantando los contenedores"
docker compose up -d
echo

echo "[5/6] Esperando a la base de datos"
tgs_esperar_db 40
echo

echo "[6/6] Comprobando esquema y usuarios"

# MySQL graba MYSQL_USER / MYSQL_PASSWORD / MYSQL_ROOT_PASSWORD en el
# volumen solo la primera vez que se crea. Despues, cambiar .env.local no
# cambia nada dentro de MySQL: el volumen sigue con la clave vieja.
#
# Eso deja dos casos distintos:
#   a) el usuario tgs no entra, pero root si -> se puede arreglar
#   b) root tampoco entra -> el volumen tiene una clave que no conocemos,
#      y sin ella los datos son inalcanzables
# El caso (b) no se puede recuperar solo: hay que avisar y parar.
tgs_puede_entrar() {
    docker compose exec -T db mysql -u"$1" -p"$2" -e "SELECT 1;" >/dev/null 2>&1
}

if tgs_puede_entrar "$DB_USER" "$DB_PASS"; then
    echo "[OK]   las credenciales de .env.local coinciden con el volumen"
elif tgs_puede_entrar root "$DB_ROOT_PASS"; then
    echo "[AVISO] el usuario '$DB_USER' no acepta la password de .env.local"
    echo "        (el volumen ya existia con otra clave). Sincronizando..."
    docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -e \
        "ALTER USER '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS'; FLUSH PRIVILEGES;" >/dev/null 2>&1
    echo "[OK]   password del usuario '$DB_USER' sincronizada"
else
    cat >&2 <<'AVISO'

[ERROR] El volumen de datos ya existe pero sus credenciales no coinciden
        con .env.local, y no se puede entrar ni como root.

        MySQL guarda las claves DENTRO del volumen, y solo las lee la
        primera vez que se crea. Por eso regenerar .env.local con el
        volumen ya existente deja la base inaccesible: las claves nuevas
        no coinciden con las que quedaron guardadas.

    Tenes dos salidas, segun lo que necesites:

      1. Queria empezar de cero (no me importan los datos)
         bash scripts/caida.sh      # borra el volumen
         bash scripts/instalar.sh   # y reinstalla con claves nuevas

      2. Queria conservar los datos
         Volve a poner el .env.local que tenias antes. Si lo perdiste,
         no hay forma de recuperarlo: solo queda restaurar un respaldo
         sobre un volumen limpio, como en la opcion 1.

AVISO
    exit 1
fi

echo

# En un volumen recien creado MySQL ya ejecuto schema.sql y seed.sql.
# Si el volumen ya existia sin tablas, se aplican aqui a mano.
if docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" \
       -e "USE \`$DB_NAME\`; SELECT 1 FROM usuario LIMIT 1;" >/dev/null 2>&1; then
    echo "[OK]   el esquema y los usuarios ya estaban cargados"
else
    echo "[....] aplicando db/schema.sql"
    docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"
    docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < "$TGS_RAIZ/db/schema.sql"
    echo "[....] aplicando db/seed.sql"
    docker compose exec -T db mysql -uroot -p"$DB_ROOT_PASS" "$DB_NAME" < "$TGS_RAIZ/db/seed.sql"
    echo "[OK]   esquema y usuarios cargados"
fi

echo
echo "==========================================================="
echo " Instalacion completada"
echo "==========================================================="
echo
echo "  Sitio web   : http://localhost:${APP_PUERTO}"
echo
echo "  Usuarios de prueba:"
echo "    admin@bluetech.com      Admin123!     (administrador)"
echo "    santiiuyy12@gmail.com   Gestor123!    (gestor)"
echo "    carlos.gomez@gmail.com  Cliente123!   (cliente)"
echo
echo "  Ver estado   : bash scripts/estado.sh"
echo "  Respaldar BD : bash scripts/respaldar.sh"
echo "  Simular caida: bash scripts/caida.sh"
echo "  Restaurar BD : bash scripts/restaurar.sh"
echo
