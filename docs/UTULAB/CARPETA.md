# Carpeta del proyecto — Tutoría de Proyecto Utulab

**TGS — Taller Global de Servicios (Blue Tech Uruguay)**
Proyecto de Pasaje de Grado · 3° ME – BT · UTU DGETP – Escuela Técnica de Pando

Esta es la carpeta que la cátedra de **Tutoría de Proyecto Utulab** pide para la
tercera entrega: *"carpeta completa del proyecto que documente: establecimiento
de criterios de organización, desarrollo y control de avance"*.

---

## 1. Criterios de organización

| Criterio | Cómo se aplica en este proyecto |
|---|---|
| **Un solo repositorio** | Todo el código, la base de datos, los scripts, las pruebas y la documentación viven en `github.com/ttugol909674-cpu/index`, rama `main`. No hay código fuera del repositorio. |
| **Un solo punto de verdad para la configuración** | Las variables de entorno están en `.env` (plantilla versionada) y `.env.local` (valores locales, ignorado por Git). Ningún archivo del código tiene claves escritas a mano. |
| **Separación de responsabilidades** | Cada archivo tiene una función clara: `auth.php` (sesión y permisos), `conexion.php` (base), `taller_disponibilidad.php` (disponibilidad), `funciones_pago.php` (cobros), `correo.php` (correo). Los scripts de operación están en `scripts/` y las pruebas en `tests/`. |
| **Documentación junto al código** | La documentación vive en `docs/`, no en otro lugar: manual de instalación, entregables técnicos, guía de defensa, el documento del proyecto y el anexo de código. |
| **Entorno reproducible** | Docker Compose con dos contenedores y un volumen; `scripts/instalar.sh` deja el sistema funcionando con un solo comando. |
| **Sin datos sensibles en el repositorio** | Las claves de las cuentas de demostración no se versionan: solo los *hash* bcrypt. Las claves reales se entregan por separado. |

## 2. Criterios de desarrollo

| Criterio | Aplicación |
|---|---|
| **Una funcionalidad = un commit** | Cada commit del historial corresponde a una funcionalidad o a una corrección concreta, con un mensaje que explica el *qué* y el *por qué*. |
| **Ningún commit sin probar** | Antes de subir, se corre la suite completa de pruebas (28 suites) y la auditoría de sintaxis. |
| **Security by default** | Consultas preparadas, CSRF, bcrypt, cookies protegidas y control de acceso por permiso se aplican desde el primer módulo, no como parche posterior. |
| **Interfaz en los dos idiomas desde el comienzo** | Cada texto nuevo se escribe en `lang/es.php` y `lang/en.php`; la auditoría de idiomas lo verifica. |
| **Documentación al día** | Cuando cambia el comportamiento, se actualiza el documento correspondiente en el mismo commit. |
| **Un solo camino de conexión a la base** | Todo el proyecto usa PDO; la API antigua `mysqli` se retiró. |

## 3. Criterios de control de avance

| Criterio | Mecanismo |
|---|---|
| **Control de avance por historial de versiones** | El historial de commits es el registro objective del avance: 35 commits con fecha y descripción. |
| **Control de calidad automático** | La suite de pruebas (28 suites + 20 casos de brechas) y la auditoría de sintaxis se ejecutan antes de cada entrega. |
| **Control de alcance** | La matriz de trazabilidad (documento del proyecto, capítulo 4) contrasta los 40 requisitos del enunciado con su implementación. |
| **Control de riesgos** | Registro de riesgos con tratamiento y seguimiento (ver `RIESGOS.md`). |
| **Control de la entrega** | Cronograma con hitos y responsables (ver `CRONOGRAMA.md`) y métricas del proyecto (ver `METRICAS.md`). |
| **Actas de reunión** | Estructura de acta y registro de avance (ver `ACTAS.md`). |

## 4. Contenido de la carpeta

| Documento | Qué es |
|---|---|
| [`CRONOGRAMA.md`](CRONOGRAMA.md) | Cronograma del proyecto: hitos, fechas, entregables y responsables |
| [`RIESGOS.md`](RIESGOS.md) | Registro de riesgos: identificación, probabilidad, impacto y tratamiento |
| [`METRICAS.md`](METRICAS.md) | Métricas del proyecto: tamaño, calidad, cobertura funcional y avance |
| [`ACTAS.md`](ACTAS.md) | Estructura de las actas de reunión y registro de avance del equipo |
| [`../MANUAL-INSTALACION.md`](../MANUAL-INSTALACION.md) | Manual de instalación y operación del servidor |
| [`../ENTREGABLES-TECNICOS.md`](../ENTREGABLES-TECNICOS.md) | Entregables técnicos de la segunda entrega (MER, diccionario, UML, vistas, roles) |
| [`../GUIA-DEFENSA.md`](../GUIA-DEFENSA.md) | Cobertura de requisitos, preguntas de defensa y guion de demostración |
| [`../TGS-Proyecto-Pasaje-Grado.pdf`](../TGS-Proyecto-Pasaje-Grado.pdf) | Documento del proyecto con carátula, índice, diagramas y capturas |
| [`../TGS-Anexo-Codigo-Fuente.pdf`](../TGS-Anexo-Codigo-Fuente.pdf) | Anexo con todo el código fuente, agrupado por materia |
| [`../../tests/`](../../tests/) | Suite automatizada de pruebas (35 scripts) |

## 5. Entregables de la Tutoría (qué exige el enunciado)

| Requisito del enunciado | Dónde se cumple |
|---|---|
| **Segunda entrega:** actualización del cronograma | `CRONOGRAMA.md`, capítulo 10.3 del documento del proyecto |
| **Segunda entrega:** revisión de riesgos | `RIESGOS.md`, capítulo 10.4 del documento del proyecto |
| **Segunda entrega:** métricas de funciones | `METRICAS.md`, capítulo 10.5 del documento del proyecto |
| **Segunda entrega:** identificación y tratamiento de riesgos | `RIESGOS.md` (columnas de tratamiento y responsable) |
| **Segunda entrega:** actas de reuniones realizadas hasta el momento | `ACTAS.md` (estructura de acta y registro de avance) |
| **Tercera entrega:** carpeta completa del proyecto con criterios de organización, desarrollo y control de avance | Este documento, secciones 1, 2 y 3 |
