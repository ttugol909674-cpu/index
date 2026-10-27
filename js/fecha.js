/* =====================================================================
   TGS - Campo de fecha con calendario propio

   El input nativo del navegador (type="date") no cambia de formato con
   el idioma de la pagina: Chrome y Edge usan el idioma del navegador, no
   el de la pagina, asi que una pantalla en ingles seguia mostrando
   dd/mm/aaaa. Este componente si lo hace:

     espanol  -> dd/mm/aaaa, la semana empieza el lunes
     ingles   -> mm/dd/yyyy (formato estadounidense), la semana empieza
                 el domingo

   El valor enviado al servidor sigue siendo el ISO (aaaa-mm-dd), que es
   como esta guardado en la base de datos.
   ===================================================================== */
(function () {
    'use strict';

    var MESES = {
        es: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
             'Julio', 'Agosto', 'Setiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        en: ['January', 'February', 'March', 'April', 'May', 'June',
             'July', 'August', 'September', 'October', 'November', 'December']
    };

    var DIAS = {
        es: ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'],
        en: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
    };

    // 1 = lunes (español), 0 = domingo (inglés).
    var PRIMER_DIA = { es: 1, en: 0 };

    function dos(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function partes(iso) {
        var p = (iso || '').split('-');
        return { y: parseInt(p[0], 10), m: parseInt(p[1], 10), d: parseInt(p[2], 10) };
    }

    function iso(y, m, d) {
        return y + '-' + dos(m) + '-' + dos(d);
    }

    function texto(idioma, y, m, d) {
        if (idioma === 'en') {
            return m + '/' + d + '/' + y;
        }
        return dos(d) + '/' + dos(m) + '/' + y;
    }

    // Convierte lo que el usuario escribe (en el formato del idioma) a ISO.
    function aIso(idioma, escrito) {
        var limpio = (escrito || '').trim().replace(/[.\-\s]/g, '/');
        var p = limpio.split('/').filter(function (x) { return x !== ''; });
        if (p.length !== 3) {
            return null;
        }
        var y, m, d;
        if (idioma === 'en') {
            m = parseInt(p[0], 10);
            d = parseInt(p[1], 10);
            y = parseInt(p[2], 10);
        } else {
            d = parseInt(p[0], 10);
            m = parseInt(p[1], 10);
            y = parseInt(p[2], 10);
        }
        if (!y || !m || !d || m < 1 || m > 12 || d < 1 || d > 31) {
            return null;
        }
        return iso(y, m, d);
    }

    function crearCampo(campo) {
        var idioma = campo.getAttribute('data-formato') === 'en' ? 'en' : 'es';
        var oculto = campo.querySelector('input[type="hidden"]');
        var textoInput = campo.querySelector('.tgs-fecha-texto');
        var boton = campo.querySelector('.tgs-fecha-boton');
        var calendario = campo.querySelector('.tgs-calendario');
        if (!oculto || !textoInput || !boton || !calendario) {
            return;
        }

        var actual = partes(oculto.value);
        var vista = { y: actual.y || new Date().getFullYear(), m: actual.m || new Date().getMonth() + 1 };

        function pintar() {
            var primero = new Date(vista.y, vista.m - 1, 1);
            var diaSemana = primero.getDay();                 // 0 = domingo
            var desplazamiento = (diaSemana - PRIMER_DIA[idioma] + 7) % 7;
            var dias = new Date(vista.y, vista.m, 0).getDate();
            var hoy = new Date();
            var elegido = oculto.value;

            var html = '<div class="tgs-calendario-cabecera">'
                + '<button type="button" class="tgs-calendario-nav" data-nav="-1" aria-label="Anterior">&lsaquo;</button>'
                + '<span class="tgs-calendario-titulo">' + MESES[idioma][vista.m - 1] + ' ' + vista.y + '</span>'
                + '<button type="button" class="tgs-calendario-nav" data-nav="1" aria-label="Siguiente">&rsaquo;</button>'
                + '</div><div class="tgs-calendario-dias">';

            var i;
            for (i = 0; i < 7; i++) {
                html += '<span class="tgs-calendario-dia-semana">' + DIAS[idioma][i] + '</span>';
            }
            for (i = 0; i < desplazamiento; i++) {
                html += '<span class="tgs-calendario-dia vacio"></span>';
            }
            for (i = 1; i <= dias; i++) {
                var valor = iso(vista.y, vista.m, i);
                var clases = 'tgs-calendario-dia';
                if (valor === elegido) { clases += ' elegido'; }
                if (i === hoy.getDate() && vista.m === hoy.getMonth() + 1 && vista.y === hoy.getFullYear()) { clases += ' hoy'; }
                html += '<button type="button" class="' + clases + '" data-fecha="' + valor + '">' + i + '</button>';
            }
            html += '</div>';
            calendario.innerHTML = html;
        }

        function aplicar(nuevoIso) {
            oculto.value = nuevoIso;
            var p = partes(nuevoIso);
            textoInput.value = texto(idioma, p.y, p.m, p.d);
            // Se avisa a los formularios que escuchan el cambio (por
            // ejemplo, la disponibilidad de horarios).
            var evento;
            if (typeof Event === 'function') {
                evento = new Event('change', { bubbles: true });
            } else {
                evento = document.createEvent('HTMLEvents');
                evento.initEvent('change', true, false);
            }
            oculto.dispatchEvent(evento);
        }

        boton.addEventListener('click', function (evento) {
            evento.preventDefault();
            evento.stopPropagation();
            var abierto = !calendario.hasAttribute('hidden');
            cerrarTodos();
            if (!abierto) {
                pintar();
                calendario.removeAttribute('hidden');
                campo.classList.add('abierto');
            }
        });

        textoInput.addEventListener('change', function () {
            var convertido = aIso(idioma, textoInput.value);
            if (convertido === null) {
                textoInput.value = textoInput.value.trim();
                textoInput.classList.add('error');
                return;
            }
            textoInput.classList.remove('error');
            aplicar(convertido);
        });

        textoInput.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                textoInput.blur();
            }
        });

        calendario.addEventListener('click', function (evento) {
            var destino = evento.target;
            if (!destino) { return; }
            if (destino.hasAttribute('data-fecha')) {
                aplicar(destino.getAttribute('data-fecha'));
                cerrarTodos();
                return;
            }
            if (destino.hasAttribute('data-nav')) {
                var paso = parseInt(destino.getAttribute('data-nav'), 10);
                vista.m += paso;
                while (vista.m > 12) { vista.m -= 12; vista.y += 1; }
                while (vista.m < 1) { vista.m += 12; vista.y -= 1; }
                pintar();
            }
        });

        campo.addEventListener('tgs:abrir', function () {
            pintar();
            calendario.removeAttribute('hidden');
            campo.classList.add('abierto');
        });
    }

    function cerrarTodos() {
        var abiertos = document.querySelectorAll('.tgs-fecha.abierto');
        for (var i = 0; i < abiertos.length; i++) {
            abiertos[i].classList.remove('abierto');
            var cal = abiertos[i].querySelector('.tgs-calendario');
            if (cal) { cal.setAttribute('hidden', 'hidden'); }
        }
    }

    document.addEventListener('click', function (evento) {
        if (!evento.target.closest || !evento.target.closest('.tgs-fecha')) {
            cerrarTodos();
        }
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key === 'Escape') {
            cerrarTodos();
        }
    });

    function iniciar() {
        var campos = document.querySelectorAll('[data-tgs-fecha]');
        for (var i = 0; i < campos.length; i++) {
            crearCampo(campos[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
