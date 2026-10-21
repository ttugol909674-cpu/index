# Suite de pruebas del proyecto TGS

Estas son las pruebas automatizadas del proyecto: se ejecutan **contra la
aplicación real** que corre en Docker (no contra simulaciones), por eso
verifican de verdad lo que el sistema hace.

## Qué cubren

| Grupo | Qué verifica |
|---|---|
| `test_csrf.sh` | Que todos los formularios POST pidan token CSRF y que sin token no se ejecute nada |
| `test_alta.sh` | Alta de usuarios desde el panel: correos duplicados, roles inválidos, claves cortas, clave automática |
| `test_editar.sh` | Alta, edición y borrado de vehículos, con intento de inyección incluido |
| `test_perfil.sh` | Cambio de datos del perfil y de la contraseña (con validación de la clave actual) |
| `test_baja.sh` | Baja de cuenta: con clave incorrecta, sin confirmación, sin token y con todo correcto |
| `test_permisos.sh` | Acceso de cada rol a cada pantalla, redirección de intrusos y acceso sin sesión |
| `test_matriz.sh` | Edición de la matriz de permisos y efecto inmediato en el sistema |
| `test_disp.sh` | Reserva de turno: día cerrado, hora fuera de horario, franja completa y franja libre |
| `test_repro.sh` | Reprogramación y cancelación de turnos, con auditoría de cambios |
| `test_alta_taller.sh` | Alta de una sede con horario, marcas y servicios, y reserva sobre esa sede |
| `test_pagos.sh`, `test_pagos2.sh` | Registro, aprobación, rechazo y anulación de cobros; recálculo del estado |
| `test_pago_cliente.sh`, `test_a_pagar.sh` | Pago con saldo prepago, reporte de pago y cuentas pendientes |
| `test_presupuestos.sh` | Alta de presupuesto con ítems, totales, aceptación y rechazo |
| `test_notificaciones.sh`, `test_smtp.sh` | Cola de avisos al cliente, estados de envío y reenvío |
| `test_panel.sh`, `test_nav.sh` | Contenido de los tres paneles y navegación de cada rol |
| `test_agenda.sh`, `test_detalle.sh`, `test_modal.sh` | Agenda del gestor, detalle del turno y edición rápida |
| `test_filtros.sh`, `test_filtros2.sh`, `test_filtros3.sh` | Filtros por texto, rol, marca y servicio, con y sin resultados |
| `test_ownership.sh` | Que un cliente no pueda ver ni modificar datos de otro cliente |
| `test_idiomas.sh` | Bilingüismo en las 24 pantallas, persistencia del idioma y selector en todas |
| `test_gaps.sh` | Los 20 casos de verificación de las brechas G1 a G5 del enunciado |

Además: `run_suite.sh` (corre todo), `restore_demo_full.sh` (restaura los datos
de demostración), `smoke.sh` (comprobación rápida) y `lint_vm.sh` (sintaxis de
los 42 archivos PHP).

## Cómo ejecutarlas

Las pruebas se ejecutan **dentro de la máquina virtual del proyecto**,
porque necesitan `docker exec` para comprobar el estado de la base.

```bash
# 1. Copiar la suite a la maquina virtual
#    (desde la maquina del proyecto: scp -r tests usuario@vm:~/Desktop/tgs/)

# 2. Configurar las credenciales de las cuentas de demostracion
cp tests/credenciales.ejemplo.sh tests/credenciales.local.sh
#    y completar con las claves reales (este archivo NO se versiona)

# 3. Correr la suite completa
bash /tmp/run_suite.sh
```

El resultado esperado es que las 28 suites terminen en `OK` y que la ultima
linea sea `SUITE_FALLOS=0`.

## Nota sobre las credenciales

El archivo `credenciales.local.sh` queda fuera del repositorio a proposito:
las claves de las cuentas de demostracion **no se publican**, porque el sitio
esta expuesto en internet durante la defensa. La libreria compartida
(`tgs_test_lib.sh`) las carga de ese archivo; si no existe, los tests fallan
con un mensaje claro de credenciales faltantes.
