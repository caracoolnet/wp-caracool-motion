/*!
 * Caracool Motion — Intro
 * ─────────────────────────────────────────────────────────────────────────
 * La sábana y el logotipo ya están en el HTML (los imprime PHP en
 * wp_body_open). Este archivo solo los mueve:
 *
 *   1. Deduce las piezas del logotipo: la forma con más área es el disco,
 *      las que quedan fuera de él son la marca (el aspa), el resto letras.
 *   2. Anima el logotipo con la variante elegida en el panel.
 *   3. Saca la sábana (sube, o se recorta de abajo arriba).
 *   4. Recuerda que se ha visto, libera el scroll y avisa al resto de
 *      módulos con el evento «cm:intro:fin» para que los efectos del hero
 *      arranquen entonces y no a oscuras.
 */
(function () {
	'use strict';

	var CONF = window.CaracoolMotionIntro || {};
	var ANIMACION = CONF.animacion || 'freno';
	var SALIDA = CONF.salida || 'sabana';
	var TEMPO = Number(CONF.tempo) || 100;
	var DIAS = Number(CONF.dias);
	if (isNaN(DIAS)) { DIAS = 7; }

	var capa = document.getElementById('cm-intro');
	var raiz = document.documentElement;

	function terminar(recordar) {
		if (recordar && DIAS > 0) {
			try { localStorage.setItem('cmIntroHasta', String(Date.now() + DIAS * 86400000)); } catch (e) {}
		} else if (DIAS === 0) {
			// Con 0 días no se recuerda nada, y se olvida lo que hubiera de antes.
			try { localStorage.removeItem('cmIntroHasta'); } catch (e) {}
		}
		if (capa && capa.parentNode) { capa.parentNode.removeChild(capa); }
		raiz.classList.remove('cm-intro-activa');
		var lenis = window.CaracoolMotion && window.CaracoolMotion.lenis;
		if (lenis && typeof lenis.start === 'function') { lenis.start(); }
		try { document.dispatchEvent(new CustomEvent('cm:intro:sale')); } catch (e) {}
		try { document.dispatchEvent(new CustomEvent('cm:intro:fin')); } catch (e) {}
	}

	// Sin sábana en el HTML, sin GSAP, o ya descartada por el script en línea: no hay nada que hacer.
	if (!capa || !raiz.classList.contains('cm-intro-activa') || typeof window.gsap === 'undefined') {
		if (capa) { terminar(false); }
		return;
	}

	var svg = capa.querySelector('svg');
	if (!svg) { terminar(false); return; }

	// ── Piezas del logotipo ────────────────────────────────────────────

	function piezas() {
		var todas = Array.prototype.slice.call(svg.querySelectorAll('path, polygon, circle, ellipse, rect'));
		if (!todas.length) { return null; }
		var cajas = todas.map(function (el) { var b = el.getBBox(); return { el: el, b: b, area: b.width * b.height, cx: b.x + b.width / 2, cy: b.y + b.height / 2 }; });
		var disco = cajas.reduce(function (m, c) { return c.area > m.area ? c : m; }, cajas[0]);
		var r = Math.max(disco.b.width, disco.b.height) / 2;
		var marca = [], letras = [];
		cajas.forEach(function (c) {
			if (c === disco) { return; }
			var d = Math.hypot(c.cx - disco.cx, c.cy - disco.cy);
			if (d > r * 0.98) { marca.push(c.el); } else { letras.push(c.el); }
		});
		// Las letras pequeñas (menos de un 20 % del alto de las grandes) van en un segundo grupo.
		var alto = letras.reduce(function (m, el) { return Math.max(m, el.getBBox().height); }, 0);
		var grandes = [], pequenas = [];
		letras.forEach(function (el) { (el.getBBox().height < alto * 0.6 ? pequenas : grandes).push(el); });
		return { disco: disco.el, centro: { x: disco.cx, y: disco.cy }, marca: marca, letras: grandes, subs: pequenas, todas: todas };
	}

	function centroDe(els) {
		if (!els.length) { return null; }
		var sx = 0, sy = 0;
		els.forEach(function (el) { var b = el.getBBox(); sx += b.x + b.width / 2; sy += b.y + b.height / 2; });
		return { x: sx / els.length, y: sy / els.length };
	}

	function letrasEntran(tl, p, t0, conY) {
		if (conY) { gsap.set(p.letras, { opacity: 0, y: 6 }); gsap.set(p.subs, { opacity: 0, y: 3 }); }
		else { gsap.set(p.letras.concat(p.subs), { opacity: 0, transformOrigin: '50% 50%', scale: 0.96 }); }
		tl.to(p.letras, { opacity: 1, y: 0, scale: 1, duration: 0.55, stagger: 0.035, ease: 'power2.out' }, t0);
		tl.to(p.subs, { opacity: 1, y: 0, scale: 1, duration: 0.45, stagger: 0.015, ease: 'power2.out' }, t0 + 0.25);
	}

	/** Desplazamiento de la marca hasta el centro del disco. */
	function haciaCentro(p) {
		var cm = centroDe(p.marca);
		if (!cm) { return { dx: 0, dy: 0 }; }
		return { dx: p.centro.x - cm.x, dy: p.centro.y - cm.y };
	}

	// ── Variantes ──────────────────────────────────────────────────────

	var V = {};

	V.freno = function (p) {
		var tl = gsap.timeline(), g = haciaCentro(p);
		gsap.set(p.marca, { transformOrigin: '50% 50%', x: g.dx, y: g.dy, scale: 0, rotation: -90, opacity: 0 });
		gsap.set(p.disco, { transformOrigin: '50% 50%', scale: 0 });
		tl.to(p.marca, { scale: 1, rotation: 0, opacity: 1, duration: 0.55, ease: 'back.out(1.8)' });
		tl.to(p.disco, { scale: 1, duration: 1.5, ease: 'expo.out' }, 0.5);
		tl.to(p.marca, { x: 0, y: 0, duration: 1.5, ease: 'expo.out' }, 0.5);
		letrasEntran(tl, p, 1.0, false);
		return tl;
	};

	V.asiento = function (p) {
		var tl = gsap.timeline(), g = haciaCentro(p);
		gsap.set(p.marca, { transformOrigin: '50% 50%', x: g.dx, y: g.dy, scale: 0, rotation: -90, opacity: 0 });
		gsap.set(p.disco, { transformOrigin: '50% 50%', scale: 0 });
		tl.to(p.marca, { scale: 1, rotation: 0, opacity: 1, duration: 0.55, ease: 'back.out(1.8)' });
		tl.to(p.disco, { scale: 1, duration: 1.3, ease: 'back.out(1.15)' }, 0.5);
		tl.to(p.marca, { x: 0, y: 0, duration: 1.3, ease: 'back.out(1.3)' }, 0.5);
		letrasEntran(tl, p, 1.05, false);
		return tl;
	};

	V.respira = function (p) {
		var tl = gsap.timeline(), g = haciaCentro(p);
		gsap.set(p.marca, { transformOrigin: '50% 50%', x: g.dx, y: g.dy, scale: 0, rotation: -90, opacity: 0 });
		gsap.set(p.disco, { transformOrigin: '50% 50%', scaleX: 0, scaleY: 0 });
		tl.to(p.marca, { scale: 1, rotation: 0, opacity: 1, duration: 0.55, ease: 'back.out(1.8)' });
		tl.to(p.disco, { scaleX: 1.03, scaleY: 0.97, duration: 0.9, ease: 'power3.out' }, 0.5);
		tl.to(p.disco, { scaleX: 1, scaleY: 1, duration: 0.6, ease: 'sine.inOut' }, 1.3);
		tl.to(p.disco, { scaleX: 1.012, scaleY: 1.012, duration: 0.35, ease: 'sine.inOut' }, 1.9);
		tl.to(p.disco, { scaleX: 1, scaleY: 1, duration: 0.45, ease: 'sine.inOut' }, 2.25);
		tl.to(p.marca, { x: 0, y: 0, duration: 1.3, ease: 'power3.out' }, 0.5);
		letrasEntran(tl, p, 1.15, true);
		return tl;
	};

	V.gota = function (p) {
		var tl = gsap.timeline(), g = haciaCentro(p);
		gsap.set(p.marca, { transformOrigin: '50% 50%', scale: 0, rotation: -90, opacity: 0 });
		gsap.set(p.disco, { transformOrigin: '50% 50%', scale: 0, x: -g.dx, y: -g.dy });
		tl.to(p.marca, { scale: 1, rotation: 0, opacity: 1, duration: 0.55, ease: 'back.out(1.8)' });
		tl.to(p.disco, { scale: 1, x: 0, y: 0, duration: 1.5, ease: 'power3.inOut' }, 0.45);
		letrasEntran(tl, p, 1.55, true);
		return tl;
	};

	V.sello = function (p) {
		var tl = gsap.timeline();
		gsap.set(svg, { transformOrigin: '50% 50%', scale: 1.35, filter: 'blur(8px)' });
		tl.to(svg, { scale: 1, filter: 'blur(0px)', duration: 0.55, ease: 'expo.out' });
		tl.to(svg, { scale: 0.985, duration: 0.12, ease: 'power1.in' }, 0.45);
		tl.to(svg, { scale: 1, duration: 0.25, ease: 'power2.out' }, 0.57);
		return tl;
	};

	// ── Arranque ───────────────────────────────────────────────────────

	function arrancar() {
		var p = piezas();
		if (!p) { terminar(false); return; }

		var lenis = window.CaracoolMotion && window.CaracoolMotion.lenis;
		if (lenis && typeof lenis.stop === 'function') { lenis.stop(); }
		window.scrollTo(0, 0);

		var fn = V[ANIMACION] || V.freno;
		if (!p.marca.length && (ANIMACION !== 'sello')) {
			// Sin marca fuera del disco no hay «aspa»: la variante se degrada al sello.
			fn = V.sello;
		}

		gsap.set(svg, { opacity: 1 });
		var tl = gsap.timeline({ onComplete: function () { terminar(true); } });
		tl.add(fn(p));
		tl.to({}, { duration: 0.45 });

		// Aviso de que la sábana empieza a irse: las entradas del contenido
		// (módulo Scroll) arrancan aquí, a la vista, no cuando ya se ha ido.
		var avisarSale = function () { try { document.dispatchEvent(new CustomEvent('cm:intro:sale')); } catch (e) {} };
		if (SALIDA === 'recorte') {
			tl.to(svg, { opacity: 0, duration: 0.3, ease: 'power1.in' });
			tl.call(avisarSale, null, '-=0.05');
			tl.fromTo(capa, { clipPath: 'inset(0 0 0% 0)' }, { clipPath: 'inset(0 0 100% 0)', duration: 0.95, ease: 'expo.inOut' }, '-=0.05');
		} else {
			tl.call(avisarSale);
			tl.to(capa, { yPercent: -100, duration: 1.0, ease: 'expo.inOut' });
		}
		tl.timeScale(100 / TEMPO);

		// Red de seguridad: pase lo que pase, la sábana se va.
		window.setTimeout(function () { if (capa && capa.parentNode) { tl.kill(); terminar(true); } }, 9000);
	}

	// Arranca en cuanto hay DOM: la intro tapa la carga, no espera a que termine.
	// Pero solo cuando la pestaña se ve: en segundo plano el navegador congela
	// las animaciones, y la intro esperaría a oscuras y se la perdería quien
	// abrió la web en otra pestaña.
	function cuandoSeVea() {
		if (document.visibilityState === 'hidden') {
			document.addEventListener('visibilitychange', function alVer() {
				if (document.visibilityState !== 'hidden') { document.removeEventListener('visibilitychange', alVer); arrancar(); }
			});
			return;
		}
		arrancar();
	}
	if (document.readyState !== 'loading') { cuandoSeVea(); }
	else { document.addEventListener('DOMContentLoaded', cuandoSeVea, { once: true }); }
})();
