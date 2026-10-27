#!/bin/bash
source /tmp/tgs_test_lib.sh
DB="mysql -N -uroot -p$TGS_DB_ROOT_PASS bluetech_base -e"
BASE="http://localhost:8080"
source /tmp/tgs_test_lib.sh
ADMIN="admin@bluetech.com"
PASS="$TGS_PASS_ADMIN"
JAR=/tmp/cookies_filtros.txt
rm -f $JAR

tgs_login $BASE $JAR "$ADMIN" "$PASS"

echo "== Datos de partida =="
docker exec tgs_db $DB "SELECT rol,COUNT(*) FROM usuario GROUP BY rol;"
TOTAL=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario;" 2>/dev/null)
echo "total_usuarios=$TOTAL"

echo "== 1. Sin filtros (todos los usuarios) =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php")
C=$(echo "$H" | grep -c "<tr>")
echo "filas_tabla=$C"

echo "== 2. Filtro por rol=cliente =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?rol_filtro=cliente")
echo "$H" | grep -o "[0-9]* resultados" | head -1
echo "$H" | grep -c ">admin<" | sed 's/^/celdas_admin_= /'

echo "== 3. Filtro por rol=gestor =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?rol_filtro=gestor")
echo "$H" | grep -o "[0-9]* resultados" | head -1

echo "== 4. Filtro rol=admin =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?rol_filtro=admin")
echo "$H" | grep -o "[0-9]* resultados" | head -1

echo "== 5. Rol invalido (debe ignorarse) =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?rol_filtro=superusuario")
echo "$H" | grep -o "[0-9]* resultados" | head -1

echo "== 6. Busqueda por texto (nombre) =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?q=Gomez")
echo "$H" | grep -o "[0-9]* resultados" | head -1
echo "$H" | grep -q "Carlos" && echo "ENCUENTRA_NOMBRE_OK" || echo "ENCUENTRA_NOMBRE_FALLO"

echo "== 7. Busqueda por email =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?q=bluetech")
echo "$H" | grep -o "[0-9]* resultados" | head -1

echo "== 8. Busqueda sin coincidencias =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?q=zzzznotfound")
echo "$H" | grep -o "[0-9]* resultados" | head -1
echo "$H" | grep -q "No hay usuarios que coincidan" && echo "SIN_RESULTADOS_OK" || echo "SIN_RESULTADOS_FALLO"

echo "== 9. Texto + rol combinados =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?q=bluetech&rol_filtro=admin")
echo "$H" | grep -o "[0-9]* resultados" | head -1

echo "== 10. Wildcards % y _ se literales =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?q=%25")
echo "$H" | grep -o "[0-9]* resultados" | head -1
echo "(0 resultados = comodin tratado como texto)"

echo "== 11. Injection en el filtro (debe devolver 0 y no romper) =="
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" "$BASE/administrar_usuarios.php?q=%27%20OR%20%271%27%3D%271")
echo "http_injection=$CODE"
docker exec tgs_db $DB "SELECT COUNT(*) FROM usuario;"

echo "== 12. Filtros de talleres =="
T=$(docker exec tgs_db $DB "SELECT COUNT(*) FROM taller;")
echo "total_talleres=$T"
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_talleres.php")
echo "$H" | grep -c "card-custom shadow" | sed 's/^/tarjetas_sin_filtro=/'
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_talleres.php?q=Taller")
echo "$H" | grep -c "card-custom shadow" | sed 's/^/tarjetas_con_q=Taller/'
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_talleres.php?q=zzznotfound")
echo "$H" | grep -q "No hay talleres que coincidan" && echo "TALLERES_SIN_RESULTADOS_OK" || echo "TALLERES_SIN_RESULTADOS_FALLO"
CODE=$(curl -s -c $JAR -b $JAR -o /dev/null -w "%{http_code}" "$BASE/administrar_talleres.php?q=%27%20OR%20%271%27%3D%271")
echo "http_injection_talleres=$CODE"
docker exec tgs_db $DB "SELECT COUNT(*) FROM taller;"

echo "== 13. El filtro se conserva al cambiar el rol (POST action vacio) =="
H=$(curl -s -c $JAR -b $JAR "$BASE/administrar_usuarios.php?q=bluetech&rol_filtro=admin")
echo "$H" | grep -o 'value="bluetech"' | head -1
echo "$H" | grep -o '<option value="admin" selected>' | head -1

rm -f $JAR
echo "FIN"
