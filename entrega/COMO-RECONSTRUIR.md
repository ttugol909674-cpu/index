# Entrega de la máquina virtual (.OVA)

Esta carpeta contiene la **imagen de la máquina virtual del proyecto**, que es
el entregable de la tercera entrega de la cátedra de Administración de
Sistemas Operativos.

## Archivos

| Archivo | Tamaño | Qué es |
|---|---|---|
| `basedatos.ova.7z.001` | 1,9 GB | Volumen 1 de 4 |
| `basedatos.ova.7z.002` | 1,9 GB | Volumen 2 de 4 |
| `basedatos.ova.7z.003` | 1,9 GB | Volumen 3 de 4 |
| `basedatos.ova.7z.004` | 1,5 GB | Volumen 4 de 4 |

## Por qué va troceada

GitHub no permite versionar un archivo de 7 GB en un repositorio: el límite de
Git es 100 MB por objeto. Con **Git LFS** el límite sube a 2 GB por archivo, así
que la imagen se entrega en cuatro volúmenes de 7-Zip de 1,9 GB cada uno.

## Cómo reconstruir la imagen

1. Descargar **los cuatro volúmenes** a la misma carpeta.
2. Extraer con 7-Zip (o con la línea de comandos):

   ```
   7z x basedatos.ova.7z.001
   ```

   7-Zip reconoce solo el primer volumen: los demás se toman del mismo
   directorio y se recombinan solos. Aparece el archivo `basedatos.ova` de
   7,04 GB.

   Desde la terminal de Linux: `7z x basedatos.ova.7z.001 -so > basedatos.ova`

## Cómo importar la máquina virtual

1. VirtualBox → **Archivo → Importar appliance virtual** → `basedatos.ova`.
2. **Red → Adaptador puente**, para que la VM sea alcanzable desde la red local.
3. Iniciar la VM e iniciar sesión con el usuario `taller`.
4. El sitio queda disponible en `http://<ip-de-la-vm>:8080`.

Dentro de la imagen ya están: Docker con los dos contenedores, la base de
datos con los datos de demostración, los scripts de operación, el firewall
configurado y el servicio del túnel de publicación.

## Comprobar que la imagen quedó bien

```bash
cd ~/Desktop/tgs
bash scripts/estado.sh          # contenedores, base y sitio
bash scripts/operador.sh estado # resumen del servidor
docker compose ps
```

## Nota sobre el tamaño

La imagen ocupa 7 GB en el repositorio y **cada descarga consume cuota de LFS
de la cuenta** (10 GB mensuales en el plan gratuito). Si solo necesitás
inspeccionar el proyecto, alcanzá con el código y la documentación; bajá la
imagen solo cuando la necesites (por ejemplo, para la entrega o para levantar
el servidor desde cero).

Los pasos completos de instalación están en
[`../docs/MANUAL-INSTALACION.md`](../docs/MANUAL-INSTALACION.md), sección 6.
