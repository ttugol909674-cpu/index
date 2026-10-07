# =====================================================================
# TGS - Taller Global de Servicios
# Imagen de la aplicacion: Apache + PHP dentro de un contenedor
# =====================================================================
FROM php:8.4-apache

# Extensiones necesarias para hablar con MySQL desde PHP.
# pdo_mysql lo usan conexion.php, reservas.php, login.php y el resto.
# mysqli lo usan las paginas que aun no migraron a PDO.
RUN docker-php-ext-install pdo_mysql mysqli

# Zona horaria del contenedor
ENV TZ=America/Montevideo

# Publica el proyecto en el directorio que Apache ya tiene configurado
COPY . /var/www/html

# Apache corre como www-data: le damos escritura al arbol del proyecto
# para que no falle si alguna pagina intenta crear archivos.
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
