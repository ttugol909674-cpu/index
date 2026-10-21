# Actas de reunión — TGS

**Proyecto de Pasaje de Grado · 3° ME – BT · UTU DGETP – Escuela Técnica de Pando**

La cátedra de Tutoría de Proyecto Utulab pide, para la segunda entrega, las
**actas de reuniones realizadas hasta el momento**. Este documento tiene dos
partes:

1. La **estructura** que debe tener cada acta (la que hay que imprimir, completar
   y firmar).
2. El **registro de avance** del equipo, extraído del historial de versiones del
   repositorio, que es la evidencia objetiva de lo que se hizo en cada etapa.

> **Pendiente del equipo:** imprimir y firmar las actas de cada reunión con
> asistencia, hora de inicio y fin, temas tratados, acuerdos y firmas. La
> estructura de abajo es la plantilla a completar.

---

## 1. Estructura de las actas (plantilla)

```
ACTA DE REUNIÓN N.º ____
Proyecto de Pasaje de Grado — TGS (Taller Global de Servicios)
3° ME – BT — UTU DGETP, Escuela Técnica de Pando

Fecha: ______________        Hora de inicio: ______   Hora de fin: ______
Lugar / medio: ________________________________________
Convocada por: ______________________

ASISTENTES
  1. Santiago Figueroa  — C.I. 5.777.974-1
  2. Alex Torres        — C.I. 6.747.915-7
  3. Facundo Fernandez  — C.I. 5.717.364-8
  4. Mariano Di Trani   — C.I. 5.743.443-6
  5. Alejo Mussetti     — C.I. 5.750.437-6

AUSENTES (con motivo): ____________________________________

ORDEN DEL DÍA
  1._______________________________________________________
  2._______________________________________________________
  3._______________________________________________________

DESARROLLO Y ACUERDOS
  (qué se discutió, qué se decidió, quién queda a cargo de cada tarea)

  - ______________________________________________________________________
  - ______________________________________________________________________

TAREAS ACORDADAS
  | Tarea                                      | Responsable      | Fecha límite |
  |___________________________________________|__________________|_____________|
  |                                           |                  |             |
  |                                           |                  |             |
  |                                           |                  |             |

RIESGOS Y PROBLEMAS DETECTADOS
  - ______________________________________________________________________

PRÓXIMA REUNIÓN: ______________________

FIRMAS
  ________________________   ________________________
  ________________________   ________________________
  ________________________   ________________________
  ________________________   ________________________
  ________________________
```

## 2. Registro de avance del equipo

Tabla construida con el historial real del repositorio
(`git log --pretty=format:'%ad %s' --date=short`). Cada línea es una entrega
verificada: el commit se sube solo después de correr la suite de pruebas y la
auditoría de sintaxis.

| Fecha | Hito de avance | Evidencia en el repositorio |
|---|---|---|
| 2026-05 | Lectura y desglose del enunciado en 40 requerimientos por rol | Documento de Requisitos y primer modelo de datos |
| 2026-06 | Diseño de la base: MER, 16 tablas, diccionario y normalización 3FN | `db/schema.sql`, `docs/ENTREGABLES-TECNICOS.md` |
| 2026-06 | Núcleo: sesión, matriz de permisos, registro, login y panel del cliente | `auth.php`, `registro.php`, `panel_cliente.php` |
| 2026-07 | Turnos, vehículos, disponibilidad por sede y franja de 60 minutos | `reservas.php`, `taller_disponibilidad.php` |
| 2026-07 | Primera entrega: versión funcional y presentación oral | Commit de cierre de la primera entrega |
| 2026-08 | Módulos de gestión: pagos, presupuestos, facturación, agenda y permisos editables | `administrar_pagos.php`, `administrar_presupuestos.php`, `agenda_gestor.php`, `permisos.php` |
| 2026-08 | Infraestructura: Docker Compose y scripts de operación | `Dockerfile`, `docker-compose.yml`, `scripts/instalar.sh`, `scripts/respaldar.sh`, `scripts/restaurar.sh`, `scripts/caida.sh` |
| 2026-09 | Unificación de la interfaz de los tres paneles y auditoría de los dos idiomas | `css/tgs-panel.css`, `lang/es.php`, `lang/en.php` |
| 2026-09 | Cierre de las cinco brechas del enunciado (G1–G5) | `factura.php`, `historial_cliente.php`, `taller.php`, `db/vistas.sql`, `usuario.saldo_prepago` |
| 2026-09 | Segunda entrega: documento técnico, entregables de UML y despliegue documentado | `docs/ENTREGABLES-TECNICOS.md`, `docs/TGS-Proyecto-Pasaje-Grado.pdf` |
| 2026-10 | Suite de pruebas completa: 28 suites y 20 casos de brechas | `tests/` |
| 2026-10 | Migración definitiva a PDO y corrección del error 500 al editar una sede | Commit "Unificar la conexión a la base en PDO…" |
| 2026-10 | Sitio publicado en internet y endurecimiento: `.htaccess`, firewall con iptables, registro controlado, claves rotadas | `scripts/firewall.sh`, `scripts/tunel.sh`, `registro.php` |
| 2026-10 | Adaptación de la interfaz al celular sin alterar la versión de escritorio | `css/movil.css`, `js/movil.js` |
| 2026-10 | Documentación final: manual de instalación, anexo de código por materia y carpeta de Tutoría | `docs/MANUAL-INSTALACION.md`, `docs/TGS-Anexo-Codigo-Fuente.pdf`, `docs/UTULAB/` |

## 3. Cómo se verifica el avance

| Evidencia | Comando |
|---|---|
| Historial de trabajo | `git log --pretty=format:'%ad %s' --date=short` |
| Cambios por etapa | `git log --stat --oneline` |
| Calidad del código | `bash /tmp/run_suite.sh` y `bash /tmp/lint_vm.sh` |
| Estado del servidor | `bash scripts/estado.sh` y `bash scripts/operador.sh estado` |
| Cumplimiento del alcance | Matriz de trazabilidad del capítulo 4 del documento del proyecto |
