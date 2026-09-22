/*!
 * Caracool Motion — Pie · telón
 *  El pie espera quieto debajo (position: sticky) y la página sube como un
 *  telón. Aquí solo se decide si cabe: por debajo del ancho elegido o si el
 *  pie es más alto que la pantalla, se queda como un pie normal.
 */
(function () {
	'use strict';

	var raiz = document.documentElement;

	// ── Telón ────────────────────────────────────────────────────────────
	function telon(el) {
		var pie = el.closest('[data-elementor-type="footer"]') || el;
		var pagina = pie.previousElementSibling;
		while (pagina && /^(SCRIPT|STYLE|LINK|TEMPLATE)$/.test(pagina.tagName)) { pagina = pagina.previousElementSibling; }
		if (!pagina) { return; }
		var desde = parseFloat(el.getAttribute('data-cm-telon-desde')) || 0;
		var radio = parseFloat(el.getAttribute('data-cm-telon-radio'));
		pagina.style.setProperty('--cm-telon-radio', (isNaN(radio) ? 44 : radio) + 'px');

		function fondoDe(n) {
			while (n) {
				var c = getComputedStyle(n).backgroundColor;
				if (c && c !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(c)) { return c; }
				n = n.parentElement;
			}
			return '#fff';
		}

		function decidir() {
			var cabe = pie.offsetHeight <= window.innerHeight;
			var on = window.innerWidth >= desde && cabe;
			raiz.classList.toggle('cm-telon', on);
			pie.classList.toggle('cm-telon-pie', on);
			pagina.classList.toggle('cm-telon-pagina', on);
			if (on && !pagina.style.backgroundColor) { pagina.style.backgroundColor = fondoDe(document.body); }
		}
		var t;
		window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(decidir, 150); });
		if (window.ResizeObserver) { new ResizeObserver(decidir).observe(pie); }
		decidir();
	}

	function arrancar() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-cm-telon]'), telon);
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', arrancar); } else { arrancar(); }
})();
