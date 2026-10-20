# Guía de defensa — TGS (Taller Global de Servicios)

Proyecto de Pasaje de Grado · 3° ME - BT · Blue Tech Uruguay
Equipo: Santiago Figueroa (C.I. 57779741) · Alex Torres (C.I. 67479157) · Facundo Fernandez (C.I. 57173648) · Mariano Di Trani (C.I. 57434436) · Alejo Mussetti (C.I. 57504376)

> Esta guía contrasta **el enunciado oficial** (Proyecto PDF, 72 págs.) con **lo que la aplicación hace hoy**, y lista lo que falta para la defensa.

---

## 1. Estado de cobertura de los requisitos del enunciado

### 1.1 Usuario (Cliente) — el más importante, porque es el que se ve en la demo

| # | Requisito del enunciado | Estado | Dónde está resuelto |
|---|---|---|---|
| 1 | Registro (correo, contraseña, nombre completo, tipo de vehículo) y mostrar la información registrada | ✅ | `registro.php`; el tipo de vehículo se pide al registrar el vehículo |
| 2 | Login y acceso al panel principal | ✅ | `login.php` → panel según rol (`panel_cliente.php`, `dashboard_gestor.php`, `dashboard_admin.php`) |
| 3.1 | Agregar vehículo | ✅ | `reservas.php` (vehículo nuevo al agendar) |
| 3.2 | Consultar vehículos | ✅ | `panel_cliente.php` → *Mis vehículos* |
| 3.3 | Editar vehículo | ✅ | `reservas.php` → botón *Editar* |
| 3.4 | Eliminar vehículo | ✅ | `reservas.php` → botón *Eliminar* |
| 3.5 | **Historial de servicios del cliente** | ⚠️ parcial | El panel muestra los turnos, pero falta el historial por vehículo con taller, fecha, detalle y costo |
| 4.1 | Visualización de talleres en el mapa | ✅ | `index.php` (portada) y `administrar_talleres.php` (iframe de Google Maps) |
| 4.2 | **Filtrar talleres por marca, tipo de servicio, etc.** | ❌ falta | Hoy el filtro es por texto libre (nombre, dirección, correo, teléfono) |
| 4.3 | Solicitud de servicio mecánico | ✅ | `reservas.php` (turno con servicio y vehículo) |
| 4.4 | Seleccionar taller y reservar horario disponible | ✅ | Selector de sede + horarios libres en vivo (`disponibilidad.php`) |
| 5.1 | Lista de talleres con ubicación y **estado (lugares disponibles o no)** | ⚠️ parcial | Se muestran ubicación, horario y capacidad; falta el estado de ocupación actual |
| 5.2 | **Vista detallada de un taller** con opciones para reservar o cancelar | ❌ falta | No hay página de detalle de sede |
| 6 | **Historial de servicios**: taller, fechas, detalle y **costo** | ⚠️ parcial | Falta el historial con el costo de cada servicio |
| 7.1 | El usuario puede **pagar el servicio desde la aplicación** | ⚠️ interpretación | El cliente **reporta** el pago (`pagos_cliente.php`) y el taller lo valida; no hay cobro automático |
| 7.2 | Métodos de pago (tarjeta, cuenta prepaga, transferencia) | ⚠️ parcial | Hay efectivo, tarjeta y transferencia; falta *cuenta prepaga* |
| 7.3 | **Factura electrónica descargable** al concretar un pago | ❌ falta | No existe |
| 8.1 | Modificar datos personales, contraseña y contacto | ✅ | `perfil.php` |
| 8.2 | Dar de baja la cuenta | ✅ | `eliminar_cuenta.php` (pide contraseña + confirmación) |
| 8.3 | Consultar los servicios agendados | ✅ | `panel_cliente.php` y `reservas.php` |

### 1.2 Los 5 gaps que quedan (por impacto en la nota)

| # | Gap | Requisito | Esfuerzo |
|---|---|---|---|
| **G1** | **Factura electrónica descargable** (HTML imprimible → PDF) | 7.3 | Bajo: 1 página + botón "Descargar factura" en el detalle del turno y en pagos del cliente |
| **G2** | **Historial de servicios por vehículo**: taller, fecha, servicio, costo y estado | 3.5 y 6 | Medio: bloque nuevo en `panel_cliente.php` (consulta de reservas finalizadas + pagos + presupuestos) |
| **G3** | **Vista detallada de un taller** con mapa, horario, lugares libres y botón de reservar/cancelar | 5.2 | Medio: página pública `taller.php?id=N` |
| **G4** | **Filtro de talleres por marca / tipo de servicio** + **estado de ocupación** | 4.2 y 5.1 | Medio: campos `taller.marca` / `taller.tipo` (o tabla de servicios por sede) + occupancy en vivo |
| **G5** | Método de pago **cuenta prepaga** y ajuste del flujo 7.1 | 7.2 | Bajo: un valor más en el método + texto |

**Sugerencia de orden:** G1 y G2 primero (son lo que más se nota en la demo del cliente), después G3+G4 (la parte de "talleres"), y G5 al final.

---

## 2. Lo que YA está resuelto y se puede defender con argumentos fuertes

| Tema del enunciado (2ª entrega) | Cómo está resuelto en el proyecto |
|---|---|
| **Arquitectura de software y stack** | PHP 8.4 + Apache (contenedor `app`) · MySQL 8.4 (contenedor `db`) · Docker Compose · código sin framework, con `require_once` yAutoload implícito por archivos |
| **Implementación de conexión segura (PDO)** | `conexion.php` + `config.php`: un solo punto de conexión, consultas preparadas en el 100 % de las operaciones con datos de usuario, `PDO::ATTR_ERRMODE = EXCEPTION` |
| **Controlador multidominio** | `auth.php` con la **matriz de permisos** (29 permisos × 3 roles) + `verificarPermiso()`; los tres dominios (cliente, gestor, admin) comparten código y se diferencian por permiso, no por Copies |
| **Configuración del servidor web** | `Dockerfile` (imagen oficial `php:8.4-apache`), `docker-compose.yml` con healthcheck de MySQL, red propia y volumen `db_data` |
| **Directivas de seguridad** | `.env` (global) y `.env.local` (local, **nunca versionado**); `APP_DEBUG=0` con `display_errors=Off` y `log_errors=On`; cookies `httponly` + `SameSite=Lax`; `session_regenerate_id(true)` al iniciar sesión |
| **Manejo de errores** | Mensajes genéricos al usuario (nunca se filtra `getMessage()`), detalle real en el log del contenedor, y una auditoría que recorre las 34 páginas con `display_errors=On` (0 warnings) |
| **Políticas de respaldo** | `scripts/`: `instalar.sh`, `respaldar.sh` (mysqldump), `restaurar.sh`, `caida.sh` (simula la caída borrando el volumen) y `estado.sh` |
| **Bilingüismo (glosario UI)** | Toda la interfaz en español e inglés, con el idioma en sesión; las claves de traducción están centralizadas |
| **Pruebas** | 28 suites end-to-end sobre la aplicación real en Docker (login, CSRF, permisos, pagos, presupuestos, disponibilidad, agenda, idiomas) + lint de los 37 PHP |

**Puntos fuertes para la defensa oral:**
1. **La app no es una maqueta**: funciona de punta a punta con Docker, con base de datos real, scripts de respaldo/restauración y una caída simulada.
2. **La seguridad no es decorativa**: hay token CSRF en todos los formularios, contraseñas con bcrypt, permisos editables en caliente y los errores no se le muestran al visitante.
3. **El modelo de datos está normalizado** y tiene un script de migración idempotente (`db/migracion.sql`) que permite evolucionar la base sin perder datos.
4. **La disponibilidad es real**: cada sede tiene horario semanal, capacidad y franjas de 60 minutos; el servidor es quien valida (el JavaScript solo informa).

---

## 3. Checklist normativo (¡lo que puede costar la nota!)

> Leído del propio "Proyecto PDF" (Marco Regulatorio, págs. 4-5).

- [ ] **El grupo tiene 5 integrantes y el reglamento pide grupos de 3 a 4** → pedir **excepción por escrito** al cuerpo docente. Que quede registrado.
- [ ] **Acta de Integración Oficial**: debe ser **impresa y firmada por los 5**, e incluir: nombre de la empresa, C.I., nombre, teléfono y e-mail de cada integrante, y **quién es el Coordinador de Proyecto y quién su suplente**.
- [ ] Nombrar al **Coordinador de Proyecto** (y suplente) por escrito. Santiago figura como *Deputy Coordinator* en su CV → **falta definir quién es el titular**.
- [ ] **Todos los integrantes deben estar presentes en la defensa** (queda explícito en el reglamento).
- [ ] Entregar el **reglamento interno de funcionamiento** del grupo (motivos de exclusión,etc.).
- [ ] **Los cambios entre entregas se piden por escrito antes** de hacerlos y los analiza el docente.
- [ ] **No compartir código con otros grupos**: el proyecto es solo del grupo. Prepará la **explicación de cada parte del código** (quién hizo qué y por qué).
- [ ] Plazo de 1 semana desde la carta oficial para presentar el acta de integración.

---

## 4. Preguntas probables de la defensa (con respuesta)

**Sobre la base de datos**
- *¿Por qué PDO y no mysqli?* → PDOprepared statements por defecto, excepciones (`ERRMODE_EXCEPTION`), una única API para el proyecto y menos concatenación de SQL. De paso se corrigió el bug histórico: dos tablas de servicios (`servicio` y `servicios`) y la FK de `reserva` apuntaba a la vieja.
- *¿Cómo normalizaron el modelo?* → 1FN: sin grupos repetidos (los ítems de un presupuesto están en `presupuesto_detalle`, los horarios en `taller_horario`). 2FN: cada dato no depends de la clave parcial (los pagos dependen de `id_reserva`, no del cliente). 3FN: sin dependencias transitivas (`reserva.precio_total` congela el precio para no depender de `servicio.precio`, que puede cambiar).
- *¿Qué pasa si el volumen de Docker se borra?* → `scripts/caida.sh` lo simula; `respaldar.sh` + `restaurar.sh` lo recuperan. La base se regenera con `schema.sql` + `seed.sql` y se actualiza con `migracion.sql`.

**Sobre la seguridad**
- *¿Cómo evitan la inyección SQL?* → Todas las entradas de usuario van por consultas preparadas; los únicos valores concatenados son ids ya casteados con `intval()` o `FILTER_VALIDATE_INT`.
- *¿Y el CSRF?* → Token por sesión (`csrfToken()` / `validarCsrf()` con `hash_equals`) en **todos** los formularios POST; sin token la acción no se ejecuta.
- *¿Cómo guardan las contraseñas?* → `password_hash()` con bcrypt (`PASSWORD_DEFAULT`); nunca en texto plano; el login usa `password_verify()`.
- *¿Cómo controlan los roles?* → Matriz de permisos en `auth.php` + tabla `permiso_rol` que el administrador puede editar desde `permisos.php`; el chequeo es siempre por la cédula de la sesión.

**Sobre el negocio**
- *¿Cómo se calcula si un horario está disponible?* → Cada sede tiene horario semanal (`taller_horario`) y capacidad (`taller.capacidad`). Las franjas son de 60 minutos desde la hora de apertura; se cuentan las reservas vivas de esa franja (las canceladas no ocupan lugar) y se compara con la capacidad. **Siempre se valida en el servidor**.
- *¿Qué pasa si dos clientes agendan el mismo horario a la vez?* → La validación corre dentro del POST; además la capacidad limita cuántos entran por franja. (En la demo se puede mostrar el caso de 2 lugares ocupados y el tercero rechazado.)
- *¿Cómo se paga?* → El cliente ve el saldo, los datos bancarios con QR y **reporta** el pago; el taller lo valida y recién ahí el estado de cobro se recalcula (pendiente / parcial / pagado).

**Sobre el equipo y el proceso**
- *¿Cómo se organizaron?* → Metodología ágil con Scrum, roles, actas de reunión y control de versiones con Git/GitHub (el repositorio tiene historial de commits descriptivos).
- *¿Qué pasa con la caída del servidor?* → Se demuestra en vivo con `caida.sh` y `restaurar.sh` (tienen el guion en `docs/GUION-VIDEO.md`).

---

## 5. Materiales que tienen y cómo usarlos

| Archivo (en `C:\Users\ttugo\Downloads`) | Estado | Para qué sirve en la defensa |
|---|---|---|
| `Proyecto PDF.docx` / `Proyecto PDF.pdf` | ✅ completo (72 págs.) | El enunciado: requisitos, normativa, cronograma |
| `Documentación Técnica.docx` | ✅ tiene contenido | Despliegue en VirtualBox + Docker: **revisar que las credenciales de la VM no queden expuestas en un documento impreso** |
| `First Conditional.docx` | ✅ | Inglés (1ª entrega) |
| `Filosofia Proyecto.docx` | ✅ | Filosofía (1ª entrega) |
| `TGS-Filosofia-Canva.pdf` | ✅ 15 slides | Presentación de filosofía (importable a Canva) |
| `Scripst Video.pdf` / `Script.pdf` | ✅ | Guion del video de instalación/caída |
| `CiberSeguridad 1.pdf` / `2.pdf` | ✅ | Cybersecurity (1ª entrega) |
| `practico1.pdf`, `Practico_2_Firma_digital.pdf` | ✅ | Prácticos |

**Lo que NO encontré y hay que producir:**

| Entregable | Quién lo hace |
|---|---|
| **Glosario bilingüe de la interfaz (UI)** | Se puede generar automáticamente desde el código (ya está en `lang/es.php` y `lang/en.php` + los `$texto` de cada pantalla) |
| **Diccionario de datos** de las 12 tablas | Se puede generar desde la base real (ya está en `db/schema.sql`) |
| **MER + normalización + modelo lógico** | Se puede generar desde el esquema |
| **Diagramas**: clases, actividades, secuencia, navegabilidad, componentes, despliegue | Se pueden generar en notación Mermaid/PlantUML desde el código |
| **FODA, estudio de mercado, forma jurídica, plan de marketing (4P)** | Redacción del equipo (contenido, no código) |
| **Brecha digital, resistencias sociales, marco sociológico (Bourdieu / Van Dijk)** | Redacción del equipo (contenido) |
| **Cronograma, métricas, riesgos, actas de reuniones** | Redacción del equipo +Coordinator |
| **CVs de los 5 integrantes** y **carta de presentación** | Ya está el de Santiago; faltan los otros 4 |
| **Acta de Integración Oficial** (impresa y firmada) y **reglamento interno** | Coordinadía del grupo |

---

## 6. Demo en vivo (guion corto, 5 minutos)

1. `http://<ip-vm>:8080` — portada con el mapa de talleres.
2. Login **cliente** (`carlos.gomez@gmail.com` / `Cliente123!`): panel, turnos, vehículos, **pago pendiente** → *Mis pagos* (datos bancarios + QR + reportar pago).
3. Login **gestor** (`santiiuyy12@gmail.com` / `Gestor123!`): agenda del día, detalle de un turno, **aprobar el pago reportado**.
4. Login **admin** (`admin@bluetech.com` / `Admin123!`): matriz de permisos editable, datos bancarios, agenda.
5. Terminal: `bash scripts/estado.sh` → `bash scripts/caida.sh` → `bash scripts/restaurar.sh` (el sitio vuelve).

Credenciales de la demo (están en el `seed.sql` y en `db/seed.sql`; recordá cambiarlas si el documento queda a la vista).
