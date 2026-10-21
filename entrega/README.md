# Entrega de la máquina virtual

La imagen de la máquina virtual del proyecto (`basedatos.ova`, 7 GB) está
versionada en esta carpeta, troceada en **cuatro volúmenes de 7-Zip** de 1,9 GB
porque GitHub no admite archivos de ese tamaño en un repositorio (el límite
de Git es 100 MB por objeto; con Git LFS sube a 2 GB por archivo).

**Para reconstruirla**: descargá los cuatro volúmenes a la misma carpeta y
ejecutá `7z x basedatos.ova.7z.001`.

**Para importarla en VirtualBox**: Archivo → Importar appliance virtual, con
la red en *adaptador puente*, e iniciar sesión con el usuario `taller`.

Las instrucciones completas están en
[`COMO-RECONSTRUIR.md`](COMO-RECONSTRUIR.md) y en la sección 6 del
[manual de instalación](../docs/MANUAL-INSTALACION.md).
