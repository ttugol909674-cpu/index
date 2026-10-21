# Métricas del proyecto — TGS

**Proyecto de Pasaje de Grado · 3° ME – BT · UTU DGETP – Escuela Técnica de Pando**

Métricas del proyecto en la fecha de cierre de la segunda entrega y de preparación
de la tercera. Todas las cifras se tomaron del estado real del repositorio y de
la base de datos, no de estimaciones.

---

## 1. Tamaño del producto

| Métrica | Valor |
|---|---|
| Archivos PHP de la aplicación | 42 |
| Líneas de PHP | 12.771 |
| Hojas de estilo | 4 (1.096 líneas) |
| Archivos JavaScript | 1 |
| Archivos SQL (esquema, datos, migración, vistas) | 4 (1.008 líneas) |
| Scripts de operación del servidor | 12 (1.794 líneas) |
| Scripts de prueba automatizada | 35 |
| Documentos de documentación | 7 |
| Tablas de la base de datos | 16 |
| Vistas SQL de reportes | 4 |
| Permisos de la matriz de acceso | 29 × 3 roles |
| Diagrama UML | 6 (más el MER) |
| Commits | 35 |

## 2. Cobertura funcional

| Métrica | Valor | Cómo se midió |
|---|---|---|
| Requerimientos del enunciado implementados | 40 de 40 (100 %) | Matriz de trazabilidad del capítulo 4 del documento del proyecto |
| Brechas detectadas en la revisión y cerradas | 5 de 5 | Casos G1–G5 de `tests/test_gaps.sh` |
| Pantallas de la aplicación | 42 | Inventario de archivos PHP de vista |
| Roles con permisos propios | 3 (cliente, gestor, administrador) | `auth.php` y `tests/test_permisos.sh` |
| Métodos de pago soportados | 4 (efectivo, tarjeta, transferencia, cuenta prepaga) | `pagos_cliente.php` y `funciones_pago.php` |
| Idiomas de la interfaz | 2 (es/en) en las 24 pantallas revisadas | `tests/test_idiomas.sh` |

## 3. Calidad

| Métrica | Valor |
|---|---|
| Suites de prueba end-to-end | 28, **28 en verde** |
| Casos de verificación de brechas | 20, **20 en verde** |
| Errores de sintaxis en el código PHP | 0 |
| Advertencias PHP detectadas en la auditoría de las 42 pantallas | 0 |
| Consultas SQL construidas por concatenación | 0 (todo con consultas preparadas) |
| Formularios POST sin token CSRF | 0 |
| Rutas accesibles por web que exponen archivos sensibles | 0 (verificado por internet) |
| Roles de base de datos | 3, con el de solo lectura verificado como unable de escribir |

## 4. Operación e infraestructura

| Métrica | Valor |
|---|---|
| Contenedores | 2 (`tgs_app`, `tgs_db`) con *healthcheck* |
| Scripts de operación | 12, todos idempotentes |
| Tareas programadas | 3 (respaldo diario, vigilante del túnel, arranque) |
| Cobertura de respaldo | Local + remoto configurable (`RESPALDO_REMOTO`) |
| Retención de respaldos | Los 5 más recientes, con rotación automática |
| Servicios del sistema para el proyecto | 4 (`tgs-firewall`, `tgs-tunel`, `unattended-upgrades`, Docker) |
| Reglas de firewall | Entrada en DROP; SSH y sitio solo desde la red local; puertos Docker filtrados en `DOCKER-USER` |
| Publicación | Túnel de Cloudflare con HTTPS y servicio systemd de reinicio automático |
| Disponibilidad comprobada | Sitio respondiendo HTTP 200 desde internet |

## 5. Avance

| Métrica | Valor |
|---|---|
| Entregas del calendario oficial cumplidas | 3 de 3 (letra, 1ª, 2ª) |
| Entrega final en preparación | 3ª (defensa) |
| Documentos de entrega | 4 (proyecto, anexo de código, manual, entregables) |
| Commits con mensaje descriptivo | 35 de 35 |

## 6. Cómo se recalculan

Todas las cifras salvo las de cobertura funcional se obtienen con comandos
reproducibles sobre el repositorio y el servidor:

```bash
# tamaño del código
find . -name '*.php' -not -path './.git/*' | wc -l
find . -name '*.php' -not -path './.git/*' -exec cat {} + | wc -l

# historial y control de avance
git log --oneline | wc -l
git log --pretty=format:'%ad %s' --date=short

# calidad: la suite completa y la auditoría de sintaxis
bash /tmp/run_suite.sh
bash /tmp/lint_vm.sh

# operación
docker compose ps
crontab -l
sudo bash scripts/firewall.sh mostrar
```
