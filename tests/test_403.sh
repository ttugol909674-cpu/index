#!/bin/bash
source /tmp/tgs_test_lib.sh
BASE="http://localhost:8080"

probar() {
  local email=$1 pass=$2 nombre=$3
  local J=/tmp/j_$3.txt
  tgs_login $BASE $J "$email" "$pass" > /dev/null
  echo "--- $nombre ($email) ---"
  for p in panel_cliente.php pagos_cliente.php historial_cliente.php presupuestos.php reservas.php; do
    code=$(curl -s -o /tmp/out.html -w "%{http_code}" -b $J "$BASE/$p")
    loc=$(curl -s -o /dev/null -w "%{redirect_url}" -b $J "$BASE/$p")
    echo "$p -> http=$code redirect=$loc"
  done
}

probar "carlos.gomez@gmail.com" "$TGS_PASS_CLIENTE" cliente
probar "santiiuyy12@gmail.com" "$TGS_PASS_GESTOR" gestor
probar "admin@bluetech.com" "$TGS_PASS_ADMIN" admin

echo "=== rol real de los usuarios ==="
docker exec tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e "SELECT email, rol, estado FROM usuario ORDER BY rol" 2>&1 | grep -v Warning
