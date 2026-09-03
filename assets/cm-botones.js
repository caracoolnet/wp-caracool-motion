/*!
 * Caracool Motion — Botones
 * ─────────────────────────────────────────────────────────────────────────
 * Al pasar el cursor, una banda de color barre el botón. La banda lleva su
 * propia copia del contenido, así que el texto cambia de color letra a letra
 * conforme el borde lo cruza.
 *
 * NO LLEVA COLORES ESCRITOS. Cada botón se mira a sí mismo con
 * getComputedStyle y usa una inversión de sus dos colores:
 *
 *   · Botón de línea (fondo transparente)
 *       relleno = color del borde (o el del texto si no hay borde)
 *       tinta   = color de la sección que tiene detrás
 *
 *   · Botón con fondo
 *       relleno = su propio color de texto
 *       tinta   = su propio color de fondo
 *
 * De ese modo, cambiar el botón en el Kit de Elementor cambia el efecto solo.
 *
 * VARIANTES
 *   ida   — entra por la izquierda y al salir vuelve por donde vino.
 *   paso  — entra por la izquierda y al salir sigue y se va por la derecha.
 *   cursor— entra por el lado por el que llegas y sale por el que te vas.
 */
(function () {
	'use strict';

	var CONF = window.CaracoolMotionBotones || {};
	var VARIANTE = CONF.variante || 'paso';
	var VELOCIDAD = CONF.velocidad || 0.5;
	var SELECTOR = '.elementor-button, .elementor-button-link';

	if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		return;
	}

	// ── Utilidades de color ────────────────────────────────────────────

	function transparente(c) {
		if (!c) { return true; }
		c = String(c).trim();
		if (c === 'transparent' || c === 'none') { return true; }
		// rgba(x, y, z, 0) y variantes con alfa 0
		var m = c.match(/rgba?\(([^)]+)\)/);
		if (m) {
			var p = m[1].split(/[,\/\s]+/).filter(function (v) { return v !== ''; });
			if (p.length > 3 && parseFloat(p[3]) === 0) { return true; }
		}
		return false;
	}

	/** Color real que hay detrás: se sube por el árbol hasta encontrar uno opaco. */
	function fondoDe(el) {
		var n = el.parentElement;
		while (n && n !== document.documentElement) {
			var c = window.getComputedStyle(n).backgroundColor;
			if (!transparente(c)) { return c; }
			n = n.parentElement;
		}
		var body = window.getComputedStyle(document.body).backgroundColor;
		return transparente(body) ? '#ffffff' : body;
	}

	// ── Preparación de un botón ────────────────────────────────────────

	function preparar(btn) {
		if (btn.dataset.cmBotonListo === '1') { return; }
		// El ajuste "Sin animación" viaja en el envoltorio del widget, no en el <a>.
		if (btn.dataset.cmBoton === 'no') { return; }
		if (btn.closest && btn.closest('[data-cm-boton="no"]')) { return; }

		var cs = window.getComputedStyle(btn);
		var fondo = cs.backgroundColor;
		var texto = cs.color;
		var borde = cs.borderTopColor;
		var sinBorde = (parseFloat(cs.borderTopWidth) === 0);

		var deLinea = transparente(fondo);
		var relleno = deLinea ? ((sinBorde || transparente(borde)) ? texto : borde) : texto;
		var tinta = deLinea ? fondoDe(btn) : fondo;

		// Si los dos colores acaban siendo el mismo, el efecto no se vería.
		if (relleno === tinta) { return; }

		btn.style.setProperty('--cm-relleno', relleno);
		btn.style.setProperty('--cm-tinta', tinta);
		btn.style.setProperty('--cm-vel', VELOCIDAD + 's');

		// La capa cubre el borde entero y sobresale al menos 1px, para que el
		// canto redondeado del botón no asome como un halo alrededor de ella.
		var grosor = parseFloat(cs.borderTopWidth) || 0;
		btn.style.setProperty('--cm-sangrado', Math.max(grosor, 1) + 'px');

		var capa = document.createElement('span');
		capa.className = 'cm-boton__capa';
		capa.setAttribute('aria-hidden', 'true');

		// Se clona el envoltorio entero para no perder el icono del botón.
		var contenido = btn.querySelector('.elementor-button-content-wrapper');
		if (contenido) {
			var copia = contenido.cloneNode(true);
			limpiarIds(copia);
			capa.appendChild(copia);
		} else {
			capa.textContent = btn.textContent.trim();
		}

		btn.classList.add('cm-boton');
		btn.appendChild(capa);
		btn.dataset.cmBotonListo = '1';

		pon(capa, '0%', '100%');
		enlazar(btn, capa);
	}

	/** Un id duplicado en el DOM rompe etiquetas y anclas: fuera del clon. */
	function limpiarIds(nodo) {
		if (nodo.removeAttribute) { nodo.removeAttribute('id'); }
		var conId = nodo.querySelectorAll ? nodo.querySelectorAll('[id]') : [];
		for (var i = 0; i < conId.length; i++) { conId[i].removeAttribute('id'); }
	}

	function pon(capa, izq, der) {
		capa.style.setProperty('--cm-izq', izq);
		capa.style.setProperty('--cm-der', der);
	}

	function ladoDe(e, el) {
		var r = el.getBoundingClientRect();
		if (!r.width) { return 'izq'; }
		return (e.clientX - r.left) < r.width / 2 ? 'izq' : 'der';
	}

	function enlazar(btn, capa) {
		btn.addEventListener('mouseenter', function (e) {
			if (VARIANTE === 'cursor') {
				// Arranca pegada al lado por el que llega el cursor, sin transición.
				var l = ladoDe(e, btn);
				capa.style.transition = 'none';
				pon(capa, l === 'izq' ? '0%' : '100%', l === 'izq' ? '100%' : '0%');
				void capa.offsetWidth;
				capa.style.transition = '';
			}
			pon(capa, '0%', '0%');
		});

		btn.addEventListener('mouseleave', function (e) {
			if (VARIANTE === 'ida') { pon(capa, '0%', '100%'); return; }
			if (VARIANTE === 'paso') { pon(capa, '100%', '0%'); return; }
			if (ladoDe(e, btn) === 'izq') { pon(capa, '0%', '100%'); }
			else { pon(capa, '100%', '0%'); }
		});

		// Con teclado no hay lados: entra y sale por la izquierda.
		btn.addEventListener('focus', function () { pon(capa, '0%', '0%'); });
		btn.addEventListener('blur', function () { pon(capa, '0%', '100%'); });
	}

	// ── Arranque ───────────────────────────────────────────────────────

	function barrer(raiz) {
		var nodos = (raiz || document).querySelectorAll(SELECTOR);
		for (var i = 0; i < nodos.length; i++) { preparar(nodos[i]); }
	}

	function arrancar() {
		barrer(document);

		// Contenido que llega después: ventanas emergentes de Elementor,
		// carruseles, cargas por AJAX.
		if (window.jQuery && window.elementorFrontend) {
			jQuery(document).on('elementor/popup/show', function () { barrer(document); });
		}
		if (window.MutationObserver) {
			var espera = null;
			new MutationObserver(function () {
				clearTimeout(espera);
				espera = setTimeout(function () { barrer(document); }, 120);
			}).observe(document.body, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', arrancar);
	} else {
		arrancar();
	}
})();
