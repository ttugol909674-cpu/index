/* =====================================================================
   TGS - Taller Global de Servicios
   Ajustes de la interfaz en pantallas pequenas

   Este archivo solo hace una cosa: cuando una tabla no entra en el ancho
   del celular, la envuelve en un contenedor que se puede deslizar con
   el dedo. Sin esto, las tablas con muchas columnas (turnos, permisos,
   agenda) se salen de la pantalla.

   Es una mejora progresiva: si el navegador no ejecuta JavaScript, la
   tabla se muestra igual que en la computadora.

   Se carga despues de css/movil.css, que es el que da el resto del
   formato para telefonos.
   ===================================================================== */

(function () {
    'use strict';

    function prepararTablas() {
        var tablas = document.querySelectorAll('table');

        for (var i = 0; i < tablas.length; i++) {
            var tabla = tablas[i];

            // Si la tabla ya esta dentro de un contenedor deslizable
            // (algunas pantallas lo traen asi), no se toca.
            if (tabla.parentNode && tabla.parentNode.classList &&
                tabla.parentNode.classList.contains('tgs-tabla-scroll')) {
                continue;
            }

            // La tabla se envuelve en un div que se desliza en horizontal.
            var contenedor = document.createElement('div');
            contenedor.className = 'tgs-tabla-scroll';
            tabla.parentNode.insertBefore(contenedor, tabla);
            contenedor.appendChild(tabla);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', prepararTablas);
    } else {
        prepararTablas();
    }
})();
