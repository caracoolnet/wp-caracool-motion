/*!
 * Caracool Motion — Fondo vivo
 * ─────────────────────────────────────────────────────────────────────────
 * Busca los contenedores con data-cm-fondo y les mete una capa detrás del
 * contenido. No depende de GSAP ni de nada: va suelto.
 *
 *   olas    — lienzo de 128 px de ancho donde se suman cinco manchas
 *             gaussianas con el campo ondulado por dos senos lentos. El
 *             navegador lo estira al tamaño del contenedor y queda suave.
 *   manchas — cinco <i> con degradado radial que solo cambian de transform
 *             (animación CSS en cm-fondo.css).
 *
 * Colores: --cm-fondo-base, --cm-fondo-c1 … c4 en el contenedor (los escribe
 * Elementor desde los controles). Los vacíos se sacan del Principal del Kit.
 * La quinta mancha es siempre una sombra de la intensa.
 *
 * Cuidados: arranca cuando el navegador está libre, se para fuera de
 * pantalla y con la pestaña oculta, y con movimiento reducido pinta un
 * fotograma y se queda quieto.
 */
(function () {
	'use strict';

	var REDUCIDO = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

	// ── Color ────────────────────────────────────────────────────────────

	var lector = document.createElement('canvas').getContext('2d');

	/** Cualquier color CSS (hex, rgb, hsl, nombres…) a [r, g, b], o null. */
	function aRgb(css) {
		css = (css || '').trim();
		if (!css) { return null; }
		lector.fillStyle = '#000';
		lector.fillStyle = css;
		var v = lector.fillStyle; // «#rrggbb» o «rgba(r, g, b, a)»
		if (v.charAt(0) === '#') {
			return [parseInt(v.substr(1, 2), 16), parseInt(v.substr(3, 2), 16), parseInt(v.substr(5, 2), 16)];
		}
		var m = v.match(/[\d.]+/g);
		return m ? [+m[0], +m[1], +m[2]] : null;
	}

	function mezcla(a, b, t) {
		return [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t];
	}

	function paleta(el) {
		// Los globales del Kit viven en <body class="elementor-kit-N">, no en
		// :root; el contenedor los hereda, así que se leen desde él.
		var cs = getComputedStyle(el);
		var principal = aRgb(cs.getPropertyValue('--e-global-color-primary')) || [120, 120, 140];
		var blanco = [255, 255, 255], negro = [0, 0, 0];
		function lee(k, porDefecto) { return aRgb(cs.getPropertyValue('--cm-fondo-' + k)) || porDefecto; }
		var c3 = lee('c3', principal);
		return {
			base: lee('base', mezcla(principal, blanco, 0.78)),
			manchas: [
				lee('c1', mezcla(principal, blanco, 0.92)),
				lee('c2', mezcla(principal, blanco, 0.45)),
				c3,
				lee('c4', mezcla(principal, blanco, 0.65)),
				mezcla(c3, negro, 0.18)
			]
		};
	}

	function css(c, a) {
		return 'rgba(' + Math.round(c[0]) + ',' + Math.round(c[1]) + ',' + Math.round(c[2]) + ',' + (a === undefined ? 1 : a) + ')';
	}

	/* Dónde vive cada mancha y cómo se mueve. Posiciones en fracción del
	   contenedor; la fuerza es cuánto tapa en su centro. */
	var RECORRIDOS = [
		{ x: 0.15, y: 0.10, r: 0.55, a: 0.90, ax: 0.10, ay: 0.08, fx: 0.050, fy: 0.037, p: 0.0 },
		{ x: 0.80, y: 0.22, r: 0.48, a: 0.85, ax: 0.12, ay: 0.10, fx: 0.041, fy: 0.053, p: 1.7 },
		{ x: 0.72, y: 0.82, r: 0.50, a: 0.90, ax: 0.14, ay: 0.09, fx: 0.033, fy: 0.047, p: 3.1 },
		{ x: 0.28, y: 0.95, r: 0.40, a: 0.80, ax: 0.10, ay: 0.06, fx: 0.045, fy: 0.029, p: 4.4 },
		{ x: 0.97, y: 0.55, r: 0.32, a: 0.55, ax: 0.06, ay: 0.14, fx: 0.038, fy: 0.044, p: 5.2 }
	];

	// ── Variante «manchas» (CSS) ─────────────────────────────────────────

	function manchas(el, capa, pal) {
		capa.className += ' cm-fondo--manchas';
		capa.style.backgroundColor = css(pal.base);
		capa.style.setProperty('--cm-vel', parseFloat(el.getAttribute('data-cm-fondo-vel')) || 1);
		var alfas = [1, 1, 1, 1, 0.75];
		for (var i = 0; i < 5; i++) {
			var m = document.createElement('i');
			m.style.setProperty('--cm-m', css(pal.manchas[i], Math.min(1, alfas[i] * (parseFloat(el.getAttribute('data-cm-fondo-int')) || 1))));
			capa.appendChild(m);
		}
		return {
			play: function () { capa.classList.remove('cm-fondo--parado'); },
			stop: function () { capa.classList.add('cm-fondo--parado'); }
		};
	}

	// ── Variante «olas» (canvas) ─────────────────────────────────────────

	function olas(el, capa, pal) {
		capa.className += ' cm-fondo--olas';
		var lienzo = document.createElement('canvas');
		capa.appendChild(lienzo);
		var ctx = lienzo.getContext('2d', { alpha: false });
		var vel = parseFloat(el.getAttribute('data-cm-fondo-vel')) || 1;
		var fuerza = parseFloat(el.getAttribute('data-cm-fondo-int')) || 1;
		var W = 128, H = 80, img, t0 = performance.now(), raf = 0, ultimo = 0, corriendo = false;
		var B = pal.base, M = pal.manchas;

		function medir() {
			var r = el.getBoundingClientRect();
			var h = Math.max(48, Math.min(256, Math.round(W * r.height / Math.max(1, r.width))));
			if (h === H && img) { return; }
			H = h; lienzo.width = W; lienzo.height = H;
			img = ctx.createImageData(W, H);
		}

		function pintar(t) {
			var s = t * 0.001 * vel, d = img.data, asp = H / W;
			var mx = [], my = [], mr = [], k;
			for (k = 0; k < 5; k++) {
				var R = RECORRIDOS[k];
				mx[k] = R.x + R.ax * Math.sin(s * R.fx * 6.283 + R.p);
				my[k] = R.y + R.ay * Math.cos(s * R.fy * 6.283 + R.p * 1.3);
				mr[k] = R.r * (1 + 0.12 * Math.sin(s * 0.21 + R.p));
				mr[k] *= mr[k];
			}
			var i = 0;
			for (var y = 0; y < H; y++) {
				var v = y / H;
				var ondaU = 0.045 * Math.sin(v * 5.5 + s * 0.55) + 0.02 * Math.sin(v * 11 - s * 0.3);
				for (var x = 0; x < W; x++) {
					var u = x / W;
					var uu = u + ondaU, vv = v + 0.035 * Math.sin(u * 4.2 - s * 0.45);
					var r = B[0], g = B[1], b = B[2];
					for (k = 0; k < 5; k++) {
						var dx = uu - mx[k], dy = (vv - my[k]) * asp * 1.6;
						var w = Math.exp(-(dx * dx + dy * dy) / mr[k]) * RECORRIDOS[k].a * fuerza;
						if (w > 1) { w = 1; }
						var c = M[k];
						r += (c[0] - r) * w; g += (c[1] - g) * w; b += (c[2] - b) * w;
					}
					d[i++] = r; d[i++] = g; d[i++] = b; d[i++] = 255;
				}
			}
			ctx.putImageData(img, 0, 0);
		}

		function bucle(t) {
			if (!corriendo) { return; }
			raf = requestAnimationFrame(bucle);
			if (t - ultimo < 33) { return; } // ~30 fps: sobra para algo tan lento
			ultimo = t;
			pintar(t - t0);
		}

		medir();
		pintar(REDUCIDO ? 12000 : 0);

		if (window.ResizeObserver) {
			new ResizeObserver(function () { medir(); pintar(performance.now() - t0); }).observe(el);
		}

		return {
			play: function () { if (!corriendo && !REDUCIDO) { corriendo = true; raf = requestAnimationFrame(bucle); } },
			stop: function () { corriendo = false; cancelAnimationFrame(raf); }
		};
	}

	// ── Arranque ─────────────────────────────────────────────────────────

	var vivos = [];

	function iniciar(el) {
		if (el.__cmFondo) { return; }
		var tipo = el.getAttribute('data-cm-fondo');
		var capa = document.createElement('div');
		capa.className = 'cm-fondo';
		capa.setAttribute('aria-hidden', 'true');
		if (el.getAttribute('data-cm-fondo-grano') === 'no') { capa.className += ' cm-fondo--sin-grano'; }
		el.insertBefore(capa, el.firstChild);

		var pal = paleta(el);
		var anim = (tipo === 'manchas' ? manchas : olas)(el, capa, pal);
		var registro = { anim: anim, visible: true };
		el.__cmFondo = registro;
		vivos.push(registro);

		// La capa entra con un fundido: debajo sigue la imagen del contenedor.
		requestAnimationFrame(function () { capa.classList.add('cm-fondo--visible'); });

		if (window.IntersectionObserver) {
			new IntersectionObserver(function (e) {
				registro.visible = e[0].isIntersecting;
				decidir(registro);
			}).observe(el);
		}
		decidir(registro);
	}

	function decidir(r) {
		if (r.visible && !document.hidden) { r.anim.play(); } else { r.anim.stop(); }
	}

	document.addEventListener('visibilitychange', function () { vivos.forEach(decidir); });

	function arrancar() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-cm-fondo]'), iniciar);
	}

	// No compite con la carga: espera a que termine y a que el navegador esté libre.
	function cuandoLibre() {
		(window.requestIdleCallback || function (f) { setTimeout(f, 200); })(arrancar, { timeout: 1500 });
	}
	if (document.readyState === 'complete') { cuandoLibre(); } else { window.addEventListener('load', cuandoLibre); }

	window.CaracoolMotionFondo = { iniciar: iniciar };
})();
