# Registro de riesgos — TGS

**Proyecto de Pasaje de Grado · 3° ME – BT · UTU DGETP – Escuela Técnica de Pando**

Identificación y tratamiento de los riesgos del proyecto, como pide la cátedra
de Tutoría de Proyecto Utulab. La última columna indica si el riesgo sigue
abierto o si se cerró durante el proyecto.

---

| # | Riesgo | Origen | Prob. | Impacto | Nivel | Tratamiento | Responsable | Estado |
|---|---|---|---|---|---|---|---|---|
| R1 | **Grupo con 5 integrantes cuando el reglamento pide 3 o 4** | Normativo | Alta | Alto | Crítico | Solicitar excepción por escrito al cuerpo docente y dejar constancia de la respuesta | Coordinador | **Abierto** |
| R2 | **Desviación del alcance: el enunciado exige 40 funcionalidades** | Técnico | Alta | Alto | Crítico | Traza de requerimientos (matriz del capítulo 4) y revisión de brechas; las cinco detectadas (G1–G5) se implementaron y verificaron | Todo el equipo | Cerrado |
| R3 | **Pérdida o corrupción de la base de datos** | Técnico | Baja | Crítico | Alto | Respaldo diario automático, rotación de los cinco más recientes, restauración probada y script de caída simulada | Alejo Mussetti | Cerrado |
| R4 | **Caída del servidor durante la defensa** | Operativo | Media | Crítico | Alto | Sitio publicado con túnel y servicio systemd de reinicio automático; script de operador con restauración | Alejo Mussetti | Cerrado |
| R5 | **Fuga de información sensible al publicar el sitio** | Seguridad | Media | Crítico | Alto | Se detectó que Apache servía `.env.local` y `.git/`; se agregó `.htaccess` que los bloquea y se verificaron las respuestas por internet | Santiago Figueroa | Cerrado |
| R6 | **Acceso de terceros con las cuentas de demostración** | Seguridad | Alta | Alto | Crítico | Claves rotadas y no versionadas; registro de cuentas nuevas cerrado o con código | Santiago Figueroa | Cerrado |
| R7 | **Agotamiento del tiempo antes de la defensa** | Gestión | Alta | Medio | Alto | Trabajo por sprints con criterio de "hecho" definido; las tareas nuevas solo se aceptan si cierran un requisito del enunciado | Coordinador | Mitigado |
| R8 | **Diferencias de comportamiento entre máquinas del equipo** | Técnico | Media | Medio | Medio | Configuración externalizada y despliegue en Docker: `instalar.sh` deja todo listo con un comando | Alejo Mussetti | Cerrado |
| R9 | **Errores de seguridad no detectados** | Calidad | Media | Crítico | Alto | Suite automatizada que corre antes de cada entrega y auditoría de sintaxis y de fugas | Mariano Di Trani | Cerrado |
| R10 | **Errores que rompen pantallas sin que nadie lo note** | Calidad | Media | Medio | Medio | Capturas automáticas de las 15 pantallas principales, en escritorio y en celular, y revisión visual | Mariano Di Trani | Cerrado |
| R11 | **Interfaz inutilizable desde el celular** | Usabilidad | Alta | Medio | Alto | Capa de estilos propia para pantallas angostas, con área táctil de 44 px y tablas deslizables; verificado a 390 px | Alex Torres | Cerrado |
| R12 | **La OVA no entra en el repositorio (7 GB)** | Entrega | Alta | Medio | Medio | Se versiona troceada en cuatro volúmenes de 7-Zip con Git LFS, con instrucciones de reconstrucción | Alejo Mussetti | Cerrado |
| R13 | **Falta de actas de reunión firmadas** | Normativo | Media | Alto | Alto | Se entrega la estructura de acta y el registro de avance; las actas con asistencia y firma las completa el equipo | Todo el equipo | **Abierto** |
| R14 | **La URL pública cambia en cada reinicio del túnel** | Operativo | Media | Bajo | Bajo | Se documenta la URL vigente en `url-publica.txt` y el vigilante la actualiza; para un nombre fijo hace falta un dominio | Alejo Mussetti | Mitigado |

---

## Criterios de seguimiento

1. **Revisión en cada reunión**: la lista se recorre al inicio de cada encuentro y se
   actualizan probabilidad, impacto y estado.
2. **Riesgo abierto = acción asignada**: todo riesgo abierto o mitigado tiene un
   responsable con una tarea concreta.
3. **Los riesgos que seuildaron se documentan**: cada riesgo cerrado tiene la
   evidencia (commit, prueba o archivo) que demuestra que se trató.
