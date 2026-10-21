cd /home/taller/Desktop/tgs
echo "=== 1) Crear los roles de MySQL ==="
bash scripts/crear_roles_bd.sh 2>&1 | tail -30
echo
echo "=== 2) Probar el acceso de solo lectura (tgs_lectura) ==="
CLAVE=$(grep -E '^DB_PASS=' .env.local | cut -d= -f2)
docker exec tgs_db mysql -utgs_lectura -p"$CLAVE" bluetech_base -e "SELECT taller_nombre, facturado, cobrado FROM v_facturacion_por_taller" 2>&1 | grep -v "Warning" | head -5
echo "  --- ahora con un SELECT sobre una tabla que no deberia poder escribir ---"
docker exec tgs_db mysql -utgs_lectura -p"$CLAVE" bluetech_base -e "DELETE FROM usuario WHERE cedula='00000000'" 2>&1 | grep -v "Warning" | head -3
echo
echo "=== 3) Script operador: tareas principales ==="
for t in estado red procesos respaldos usuarios grupos origenes registros; do
  echo "--- operador.sh $t ---"
  bash scripts/operador.sh $t 2>&1 | head -8
done
