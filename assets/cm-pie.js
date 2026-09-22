/*!
 * Caracool Motion — Pie y mirada
 *  Telón: el pie espera quieto debajo (position: sticky) y la página sube
 *  como un telón. Aquí solo se decide si cabe: por debajo del ancho elegido
 *  o si el pie es más alto que la pantalla, se queda como un pie normal.
 *  Mirada: la .cm-pupila de un SVG en línea sigue al cursor (al scroll en
 *  táctil) y el grupo .cm-parpado parpadea. Quieta con movimiento reducido.
 */
(function () {
	'use strict';

	var REDUCIDO = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
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

	// ── Mirada ───────────────────────────────────────────────────────────
	var miradas = [];
	function mirada(el) {
		var svg = el.querySelector('svg');
		if (!svg) { return; }
		var pupila = svg.querySelector('.cm-pupila');
		var parpado = svg.querySelector('.cm-parpado');
		if (!parpado) { svg.classList.add('cm-parpado-auto'); }
		var m = {
			el: el, svg: svg, pupila: pupila, visible: false,
			recorrido: (parseFloat(el.getAttribute('data-cm-mirada-recorrido')) || 5) / 100,
			parpadeo: el.getAttribute('data-cm-mirada-parpadeo') !== 'no'
		};
		miradas.push(m);
		if (window.IntersectionObserver) {
			new IntersectionObserver(function (e) { m.visible = e[0].isIntersecting; }).observe(el);
		} else { m.visible = true; }
	}

	function mirar(x, y) {
		miradas.forEach(function (m) {
			if (!m.visible || !m.pupila) { return; }
			var r = m.svg.getBoundingClientRect(), p = m.pupila.getBoundingClientRect();
			if (!r.width) { return; }
			var cx = p.left + p.width / 2, cy = p.top + p.height / 2;
			var dx = x - cx, dy = y - cy, d = Math.sqrt(dx * dx + dy * dy) || 1;
			var k = Math.min(1, d / 420), max = r.width * m.recorrido;
			var vb = m.svg.viewBox && m.svg.viewBox.baseVal, esc = (vb && vb.width) ? vb.width / r.width : 1;
			m.pupila.style.transform = 'translate(' + (dx / d * k * max * esc).toFixed(2) + 'px,' + (dy / d * k * max * 0.7 * esc).toFixed(2) + 'px)';
		});
	}

	function parpadear() {
		setTimeout(function () {
			if (!document.hidden) {
				miradas.forEach(function (m) {
					if (!m.visible || !m.parpadeo) { return; }
					m.el.classList.add('cm-mirada--cierra');
					setTimeout(function () { m.el.classList.remove('cm-mirada--cierra'); }, 140);
				});
			}
			parpadear();
		}, 2600 + Math.random() * 3400);
	}

	function arrancar() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-cm-telon]'), telon);
		if (REDUCIDO) { return; }
		Array.prototype.forEach.call(document.querySelectorAll('[data-cm-mirada]'), mirada);
		if (!miradas.length) { return; }
		if (window.matchMedia('(hover: hover)').matches) {
			window.addEventListener('pointermove', function (e) { mirar(e.clientX, e.clientY); }, { passive: true });
		} else {
			window.addEventListener('scroll', function () {
				var p = (window.scrollY % 900) / 900;
				mirar(window.innerWidth * p, window.innerHeight * 0.2);
			}, { passive: true });
		}
		parpadear();
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', arrancar); } else { arrancar(); }
})();
