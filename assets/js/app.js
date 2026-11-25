/**
 * assets/js/app.js — Comportamiento común de todas las páginas (frontend y admin).
 * 1) Validación en el navegador con los estilos de Bootstrap (formularios .needs-validation).
 * 2) Estado de carga: al enviar un formulario, el botón se desactiva y muestra un spinner
 *    (evita que se envíe dos veces). La validación final siempre la hace PHP en el servidor.
 */
document.addEventListener('DOMContentLoaded', function () {

    // Campos que deben ser iguales a otro (ej. "repite la contraseña": data-igual-a="password")
    document.querySelectorAll('[data-igual-a]').forEach(function (campo) {
        var otro = document.getElementById(campo.dataset.igualA);
        if (!otro) return;
        var revisar = function () {
            campo.setCustomValidity(campo.value === otro.value ? '' : 'No coincide');
        };
        campo.addEventListener('input', revisar);
        otro.addEventListener('input', revisar);
    });

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            // 1) Validación con estilos de Bootstrap
            if (form.classList.contains('needs-validation') && !form.checkValidity()) {
                evento.preventDefault();
                evento.stopPropagation();
                form.classList.add('was-validated');
                var primero = form.querySelector(':invalid');
                if (primero) primero.focus();
                return;
            }
            // Si un confirm() (onsubmit) fue cancelado, no se muestra el spinner
            if (evento.defaultPrevented) return;

            // 2) Estado de carga en el botón que envió el formulario
            var boton = evento.submitter || form.querySelector('button[type="submit"], button:not([type])');
            if (!boton) return;
            // Si el botón tiene name/value (ej. accion=aprobar), se conserva en un campo oculto
            if (boton.name) {
                var oculto = document.createElement('input');
                oculto.type = 'hidden';
                oculto.name = boton.name;
                oculto.value = boton.value;
                form.appendChild(oculto);
            }
            var soloIcono = boton.textContent.trim() === '';
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm' + (soloIcono ? '' : ' me-1') + '" aria-hidden="true"></span>'
                + (soloIcono ? '<span class="visually-hidden">Procesando…</span>'
                             : '<span role="status">' + (boton.dataset.cargando || 'Procesando…') + '</span>');
        });
    });
});
