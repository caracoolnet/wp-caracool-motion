/*!
 * Caracool Motion — Cabecera
 * ─────────────────────────────────────────────────────────────────────────
 * Busca la cabecera del Theme Builder de Elementor y la vuelve «inteligente»:
 *
 *   · Fija y transparente mientras el scroll está por debajo del umbral.
 *   · Por encima del umbral: se esconde al bajar, reaparece al subir, y
 *     mientras está a la vista lleva fondo sólido.
 *   · Con el menú desplegable abierto no se esconde.
 *
 * La dirección del scroll la da Lenis si Caracool Motion lo tiene activo;
 * si no, el scroll nativo. No lleva colores: el fondo es una variable del
 * Kit elegida en el panel.
 */
(function () {
	'use strict';

	var CONF = window.CaracoolMotionCabecera || {};
	var UMBRAL = Number(CONF.umbral) || 120;
	var FONDO = CONF.fondo || '--e-global-color-primary';
	var SOMBRA = !!CONF.sombra;

	// Cuánto hay que moverse en una dirección para que cuente. Evita que la
	// cabecera parpadee con el temblor de la rueda o del trackpad.
	var HISTERESIS = 8;

	function buscarCabecera() {
		return document.querySelector('[data-elementor-type="header"]')
			|| document.querySelector('header.elementor-location-header');
	}

	function arrancar() {
		var cab = buscarCabecera();
		if (!cab || cab.dataset.cmCabecera === '1') { return; }
		cab.dataset.cmCabecera = '1';

		cab.classList.add('cm-cabecera');
		if (SOMBRA) { cab.classList.add('cm-cabecera--linea'); }
		cab.style.setProperty('--cm-cabecera-fondo', 'var(' + FONDO + ')');

		var ultimoY = window.scrollY || 0;
		var ancla = ultimoY;      // punto desde el que medimos la histéresis
		var direccion = 0;        // 1 baja, -1 sube

		function menuAbierto() {
			return !!cab.querySelector('.elementor-menu-toggle.elementor-active, .elementor-nav-menu--dropdown.elementor-nav-menu__container:not([aria-hidden="true"])');
		}

		function pintar(y) {
			var abierto = menuAbierto();
			cab.classList.toggle('cm-cabecera--menu-abierto', abierto);

			if (y <= UMBRAL) {
				cab.classList.remove('cm-cabecera--oculta');
				cab.classList.remove('cm-cabecera--solida');
				return;
			}

			cab.classList.add('cm-cabecera--solida');
			if (abierto) { cab.classList.remove('cm-cabecera--oculta'); return; }

			if (direccion === 1) { cab.classList.add('cm-cabecera--oculta'); }
			else if (direccion === -1) { cab.classList.remove('cm-cabecera--oculta'); }
		}

		function tick(y) {
			y = Math.max(0, y);
			var delta = y - ancla;

			if (delta > HISTERESIS) { direccion = 1; ancla = y; }
			else if (delta < -HISTERESIS) { direccion = -1; ancla = y; }
			else if (Math.abs(y - ultimoY) > 0 && (y - ultimoY) * direccion < 0) {
				// ha cambiado de sentido: reiniciamos el ancla para medir desde aquí
				ancla = y;
			}

			ultimoY = y;
			pintar(y);
		}

		// Lenis (si Caracool Motion lo ha arrancado) o scroll nativo.
		var lenis = window.CaracoolMotion && window.CaracoolMotion.lenis;
		if (lenis && typeof lenis.on === 'function') {
			lenis.on('scroll', function (e) { tick(e.scroll != null ? e.scroll : window.scrollY); });
		} else {
			var pendiente = false;
			window.addEventListener('scroll', function () {
				if (pendiente) { return; }
				pendiente = true;
				window.requestAnimationFrame(function () { pendiente = false; tick(window.scrollY); });
			}, { passive: true });
		}

		// Al abrir o cerrar el menú móvil, repintamos.
		cab.addEventListener('click', function () { window.setTimeout(function () { pintar(ultimoY); }, 50); });

		pintar(ultimoY);
	}

	function listo() {
		// Lenis arranca en cm-scroll.js al cargar; esperamos un instante para engancharnos a él.
		window.setTimeout(arrancar, 60);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', listo);
	} else {
		listo();
	}
})();
