#!/usr/bin/env bash
# =====================================================================
# TGS - Sincronizacion automatica del codigo con GitHub
#
# El codigo de la aplicacion esta montado en caliente en el contenedor
# (docker-compose.yml: "- .:/var/www/html"). Por eso, con un "git pull"
# el sitio actualizado esta en linea de inmediato: no hay que
# reconstruir la imagen ni reiniciar nada.
#
# Este script se ejecuta cada 2 minutos desde el cron. Es prudente:
#   - nunca hace merge, solo fast-forward
#   - no pisa cambios locales reales: si el contenido del archivo en
#     disco es distinto al que trae GitHub, lo deja como esta
#   - descarta lo que es identico: si el sincronizador de Windows ya
#     dejo el archivo igual que GitHub, lo marca como limpio para que
#     el fast-forward pueda aplicarse
# =====================================================================
set -uo pipefail

RAIZ="$HOME/Desktop/tgs"
LOG="$RAIZ/backups/actualizacion.log"
cd "$RAIZ" || exit 1

export GIT_TERMINAL_PROMPT=0

registrar() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG"
}

# 1) Traer las referencias remotas.
if ! git fetch origin >>"$LOG" 2>&1; then
    registrar "ERROR: no se pudo hacer fetch de origin"
    exit 1
fi

PENDIENTES="$(git rev-list HEAD..origin/main 2>/dev/null | wc -l)"
if [ "$PENDIENTES" -eq 0 ]; then
    exit 0
fi

registrar "hay $PENDIENTES commit(s) por traer"

# 2) Separar los archivos con cambios REALES de los que ya son iguales
#    a la version de GitHub (esos los trajo el sincronizador de Windows).
IGUALES_RESTRACKED=""
IGUALES_NUEVOS=""

for archivo in $(git diff --name-only); do
    if git cat-file -e "origin/main:$archivo" 2>/dev/null &&
       git show "origin/main:$archivo" | diff -q - "$archivo" >/dev/null 2>&1; then
        IGUALES_RESTRACKED="$IGUALES_RESTRACKED $archivo"
    fi
done

for archivo in $(git ls-files --others --exclude-standard); do
    if git cat-file -e "origin/main:$archivo" 2>/dev/null &&
       git show "origin/main:$archivo" | diff -q - "$archivo" >/dev/null 2>&1; then
        IGUALES_NUEVOS="$IGUALES_NUEVOS $archivo"
    fi
done

# Los modificados que ya son iguales a GitHub: se devuelven al estado
# limpio para que el fast-forward los pueda escribir.
if [ -n "$IGUALES_RESTRACKED" ]; then
    git checkout -- $IGUALES_RESTRACKED 2>/dev/null
    registrar "archivos que ya coincidian con GitHub, devueltos a limpios:$IGUALES_RESTRACKED"
fi

# Los archivos sin seguimiento que GitHub ya trae: se borran para que el
# fast-forward pueda escribir encima en vez de quejarse.
for archivo in $IGUALES_NUEVOS; do
    rm -f "$archivo" 2>/dev/null
done
[ -n "$IGUALES_NUEVOS" ] && registrar "archivos nuevos que GitHub ya trae, eliminados:$IGUALES_NUEVOS"

# 4) Intentar el fast-forward. Si queda algun cambio local real que
#    chocaria con el commit entrante, git lo rechaza solo.
if git merge --ff-only origin/main >>"$LOG" 2>&1; then
    registrar "actualizado a $(git rev-parse --short HEAD): $(git log -1 --format=%s)"
else
    registrar "no se pudo avanzar: hay cambios locales reales que chocan. Archivos:$(git diff --name-only | tr '\n' ' ')"
fi
