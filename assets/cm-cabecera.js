/*!
 * Caracool Motion — Cabecera
 * ─────────────────────────────────────────────────────────────────────────
 * Busca la cabecera del Theme Builder de Elementor y la vuelve «inteligente»:
 *
 *   · Fija y transparente mientras el scroll está por debajo del umbral.
 *   · Por encima del umbral: se esconde al bajar, reaparece al subir, y
 *     mientras está a la vista lleva fondo sólido o de cristal (translúcido
 *     con desenfoque detrás).
 *   · Con el menú desplegable abierto no se esconde.
 *   · Opcional: una píldora de cristal detrás de los enlaces del menú que va
 *     de uno a otro siguiendo el cursor y descansa en la página actual.
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
	var LINEA = CONF.linea || '--e-global-color-text';
	var CRISTAL = !!CONF.cristal;
	var PILDORA = !!CONF.pildora;

	// Cuánto hay que moverse en una dirección para que cuente. Evita que la
	// cabecera parpadee con el temblor de la rueda o del trackpad.
	var HISTERESIS = 8;

	function buscarCabecera() {
		return document.querySelector('[data-elementor-type="header"]')
			|| document.querySelector('header.elementor-location-header');
	}

	// ── Píldora de cristal en el menú ───────────────────────────────────
	// Una sola pieza por menú, que se mueve con transform y width. Sigue al
	// cursor y al foco del teclado; al salir del menú vuelve a la página
	// actual, y si no hay página actual en el menú se desvanece.
	function pildora(cab) {
		var menus = cab.querySelectorAll('.elementor-nav-menu--main > .elementor-nav-menu');
		Array.prototype.forEach.call(menus, function (ul) {
			if (ul.dataset.cmPildora === '1') { return; }
			ul.dataset.cmPildora = '1';

			var enlaces = Array.prototype.filter.call(ul.children, function (li) { return li.tagName === 'LI'; })
				.map(function (li) { return li.querySelector(':scope > a'); })
				.filter(Boolean);
			if (!enlaces.length) { return; }

			var p = document.createElement('span');
			p.className = 'cm-pildora';
			p.setAttribute('aria-hidden', 'true');
			ul.insertBefore(p, ul.firstChild);

			var actual = enlaces.filter(function (a) {
				return a.classList.contains('elementor-item-active') || a.getAttribute('aria-current') === 'page';
			})[0] || null;

			function ir(a) {
				if (!a) { p.classList.remove('cm-pildora--visible'); return; }
				// Medido contra el menú: los <li> van posicionados y offsetLeft daría 0.
				var r = a.getBoundingClientRect(), b = ul.getBoundingClientRect();
				p.style.width = r.width + 'px';
				p.style.height = r.height + 'px';
				p.style.transform = 'translate(' + (r.left - b.left - ul.clientLeft) + 'px,' + (r.top - b.top - ul.clientTop) + 'px)';
				p.classList.add('cm-pildora--visible');
			}
			function colocar() {
				p.classList.add('cm-pildora--quieta');
				ir(actual);
				void p.offsetWidth;
				p.classList.remove('cm-pildora--quieta');
			}

			enlaces.forEach(function (a) {
				a.addEventListener('mouseenter', function () { ir(a); });
				a.addEventListener('focus', function () { ir(a); });
			});
			ul.addEventListener('mouseleave', function () { ir(actual); });
			ul.addEventListener('focusout', function (e) { if (!ul.contains(e.relatedTarget)) { ir(actual); } });

			ul.classList.add('cm-menu-pildora');
			colocar();
			if (document.fonts && document.fonts.ready) { document.fonts.ready.then(colocar); }
			window.addEventListener('resize', colocar);
		});
	}

	function arrancar() {
		var cab = buscarCabecera();
		if (!cab || cab.dataset.cmCabecera === '1') { return; }
		cab.dataset.cmCabecera = '1';

		cab.classList.add('cm-cabecera');
		if (SOMBRA && !CRISTAL) { cab.classList.add('cm-cabecera--linea'); }
		if (CRISTAL) { cab.classList.add('cm-cabecera--cristal'); }
		cab.style.setProperty('--cm-cabecera-fondo', 'var(' + FONDO + ')');
		if (PILDORA) { pildora(cab); }
		cab.style.setProperty('--cm-cabecera-linea', 'var(' + LINEA + ')');

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
