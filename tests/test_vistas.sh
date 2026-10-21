cd /home/taller/Desktop/tgs
chmod +x scripts/operador.sh scripts/crear_roles_bd.sh
echo "=== 1) Aplicar la migracion (vistas) ==="
docker compose exec -T db mysql -uroot -p"$(grep -E '^DB_ROOT_PASS=' .env.local | cut -d= -f2)" bluetech_base < db/migracion.sql 2>&1 | grep -v "^mysql: \[Warning\]" | head -5
echo "  (sin errores arriba = aplicado)"
echo
echo "=== 2) Vistas creadas ==="
docker exec tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF -N -e "SELECT table_name FROM information_schema.views WHERE table_schema='bluetech_base' ORDER BY table_name" 2>/dev/null | sed 's/^/    /'
echo
echo "=== 3) Probar cada vista ==="
echo "--- v_historial_servicios ---"
docker exec tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e "SELECT cliente_nombre, patente, taller_nombre, servicio_nombre, precio_total, total_cobrado, saldo_pendiente FROM v_historial_servicios LIMIT 3" 2>/dev/null
echo "--- v_facturacion_por_taller ---"
docker exec tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e "SELECT * FROM v_facturacion_por_taller" 2>/dev/null
echo "--- v_ocupacion_sedes ---"
docker exec tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e "SELECT * FROM v_ocupacion_sedes LIMIT 3" 2>/dev/null
echo "--- v_cobros_por_metodo ---"
docker exec tgs_db mysql -uroot -pRootXFTiT6rACvYkYNnF bluetech_base -e "SELECT * FROM v_cobros_por_metodo" 2>/dev/null
