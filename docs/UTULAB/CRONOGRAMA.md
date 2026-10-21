# Cronograma del proyecto — TGS

**Proyecto de Pasaje de Grado · 3° ME – BT · UTU DGETP – Escuela Técnica de Pando**

Este documento es la actualización del cronograma que pide la cátedra de Tutoría
de Proyecto Utulab. Las fechas del calendario oficial son las del enunciado del
proyecto; las etapas internas se marcan como *realizadas* o *en curso* según el
estado real del repositorio.

---

## Hitos del calendario oficial

| Hito | Fecha oficial | Estado | Evidencia |
|---|---|---|---|
| Entrega de letra | 15 de mayo de 2026 | Realizada | Documento de requisitos firmado por el equipo |
| Conformación de grupos | 22 de mayo de 2026 | Realizada | Acta de integración del grupo (firmada) |
| Primera entrega | 6 de julio de 2026 | Realizada | Versión funcional de reservas y presentation oral |
| Segunda entrega | 14 de setiembre de 2026 | Realizada | Documento técnico, entregables de UML, base de datos y despliegue documentado |
| Tercera entrega (defensa) | Octubre de 2026 | En curso | Software completo, entrega de la `.OVA` y este documento |

## Etapas internas del proyecto

| # | Etapa | Período | Resultado | Responsable |
|---|---|---|---|---|
| 1 | Análisis y requerimientos | Mayo – junio 2026 | Documento de Requisitos del enunciado leído y desglosado en 40 requerimientos por rol | Todo el equipo |
| 2 | Modelado de datos | Junio 2026 | MER, 16 tablas, diccionario de datos y normalización hasta 3FN | Facundo Fernandez |
| 3 | Núcleo de la aplicación | Junio – julio 2026 | Sesión, permisos por matriz, registro, login, panel del cliente, turnos y vehículos | Santiago Figueroa |
| 4 | Módulos de gestión | Julio – agosto 2026 | Pagos, presupuestos, facturación, agenda del gestor, permisos editables, datos bancarios | Todo el equipo |
| 5 | Infraestructura | Agosto 2026 | Docker Compose, scripts de operación, respaldo, restauración y simulación de caída | Alejo Mussetti |
| 6 | Interfaz y bilingüismo | Setiembre 2026 | Unificación del diseño de los tres paneles y auditoría de los dos idiomas | Alex Torres |
| 7 | Cierre de brechas | Setiembre – octubre 2026 | G1 a G5 del enunciado implementados y verificados (factura, historial, vista de sede, filtros, prepago) | Todo el equipo |
| 8 | Calidad y pruebas | Octubre 2026 | 28 suites + 20 casos de brechas, auditoría de sintaxis y de seguridad | Mariano Di Trani |
| 9 | Publicación y endurecimiento | Octubre 2026 | Sitio publicado con túnel, firewall con iptables, registro controlado y claves rotadas | Santiago Figueroa / Alejo Mussetti |
| 10 | Documentación y entrega | Octubre 2026 | Documento del proyecto (67 págs), anexo de código por materia (359 págs), manual de instalación y carpeta de Tutoría | Todo el equipo |

## Hitos de la tercera entrega

| Hito | Estado | Evidencia |
|---|---|---|
| Software completo y publicado | Hecho | Sitio accesible por internet con HTTPS |
| Pruebas automáticas en verde | Hecho | 28/28 suites y 20/20 casos |
| Seguridad del servidor | Hecho | `iptables` con entrada en DROP, servicio de firewall, actualizaciones automáticas |
| Máquina virtual entregable | Hecho | `basedatos.ova` exportada, con el sistema completo y los scripts dentro |
| Documento del proyecto | Hecho | `TGS-Proyecto-Pasaje-Grado.pdf` |
| Anexo de código por materia | Hecho | `TGS-Anexo-Codigo-Fuente.pdf` |
| Carpeta de Tutoría Utulab | Hecho | Esta carpeta (`docs/UTULAB/`) |
| Acta de integración y actas de reunión firmadas | **Pendiente de firma del equipo** | Ver `ACTAS.md` |
