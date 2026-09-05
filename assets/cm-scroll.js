/**
 * Caracool Motion — Scroll
 *
 * Registro de efectos: para añadir uno nuevo no hay que tocar este archivo.
 * Basta con cargar otro script después de este y llamar a:
 *
 *     CaracoolMotion.registrar('mi-efecto', function (el, op, ctx) {
 *         // el  = el contenedor de Elementor
 *         // op  = { velocidad: 'media', direccion: 'derecha', ... }
 *         // ctx = { gsap, ScrollTrigger, lenis, irA, alEntrar, fondoDe, dur }
 *     });
 *
 * Un efecto declarado en PHP pero no registrado aquí simplemente no hace
 * nada: no lanza error ni afecta al resto.
 */
(function () {
	'use strict';

	var conf = window.CaracoolMotionConf || {};
	var reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var tactil = window.matchMedia('(pointer:coarse)').matches;

	// ── Registro público ────────────────────────────────────────────────
	var CM = window.CaracoolMotion = window.CaracoolMotion || {
		efectos: {},
		registrar: function (nombre, fn) {
			this.efectos[nombre] = fn;
			if (this._listo) { this.aplicar(nombre); }
		}
	};

	/* La cabecera imprime una regla que deja las piezas de los bloques con
	   entrada en «visibility:hidden» antes del primer pintado, para que no se
	   vean puestas, quitadas y puestas otra vez. Aquí se retira en cuanto los
	   efectos han dejado cada pieza donde toca. Si el plugin no llega a
	   arrancar, la propia cabecera la retira sola a los dos segundos. */
	function destapar() {
		document.documentElement.classList.remove('cm-entrando');
	}

	if (reducido || typeof window.gsap === 'undefined') { destapar(); return; }
	gsap.registerPlugin(ScrollTrigger);

	// ── Utilidades compartidas que reciben todos los efectos ────────────

	var DURACIONES = { lenta: 1.6, media: 1, rapida: 0.6 };

	function dur(op) {
		var v = DURACIONES[op && op.velocidad];
		return v || DURACIONES.media;
	}

	function fondoDe(el) {
		while (el && el !== document.body) {
			var c = getComputedStyle(el).backgroundColor;
			if (c && c !== 'rgba(0, 0, 0, 0)' && c !== 'transparent') { return c; }
			el = el.parentElement;
		}
		return getComputedStyle(document.body).backgroundColor;
	}

	/* Color que Elementor ha escrito en el contenedor como variable CSS desde
	   el selector de color nativo. Vacío si no hay nada. Si lo que hay es el
	   nombre de un color global sin más (valores guardados con versiones
	   anteriores, cuando esto era un desplegable), se resuelve contra el Kit. */
	function colorDeVariable(el, nombre) {
		var v = (getComputedStyle(el).getPropertyValue(nombre) || '').trim();
		if (!v || v === 'auto' || v === 'ninguno') { return ''; }
		if (/^[a-z0-9_-]+$/i.test(v) && !/^(transparent|currentcolor)$/i.test(v)) {
			var g = colorGlobal(v);
			return g || '';
		}
		return v;
	}

	/* Elementor carga perezosamente las imágenes de fondo de los contenedores
	   a partir del tercero (background-image: none hasta que el contenedor se
	   acerca a la pantalla). En una sección con efecto la foto tiene que estar
	   antes de que se mueva nada: se le quita la espera y se precarga. */
	function fondoListo(el) {
		el.classList.add('e-no-lazyload');
		var urls = {};
		var apunta = function (texto) {
			var re = /url\((['"]?)(.*?)\1\)/g, m;
			while ((m = re.exec(texto || ''))) { if (m[2] && m[2].indexOf('data:') !== 0) { urls[m[2]] = 1; } }
		};
		// Lo que ya está pintado…
		var nodos = [el].concat(Array.prototype.slice.call(el.querySelectorAll('.e-con, .elementor-widget-container, .elementor-motion-effects-layer')));
		nodos.forEach(function (n) { apunta(getComputedStyle(n).backgroundImage); });
		// …y lo que Elementor tiene en el CSS de la página para este contenedor
		// aunque todavía no lo haya aplicado (efectos de movimiento del fondo,
		// que crean su capa más tarde).
		var id = el.getAttribute('data-id');
		if (id) {
			Array.prototype.forEach.call(document.styleSheets, function (hoja) {
				var reglas;
				try { reglas = hoja.cssRules; } catch (e) { return; }
				if (!reglas) { return; }
				Array.prototype.forEach.call(reglas, function (r) {
					if (r.selectorText && r.selectorText.indexOf('elementor-element-' + id) !== -1 && r.style) {
						apunta(r.style.backgroundImage);
					}
				});
			});
		}
		Object.keys(urls).forEach(function (u) { var img = new Image(); img.src = u; });
		Array.prototype.forEach.call(el.querySelectorAll('img[loading="lazy"]'), function (img) { img.loading = 'eager'; });
	}

	function colorGlobal(nombre) {
		var v = getComputedStyle(document.documentElement).getPropertyValue('--e-global-color-' + nombre);
		if (!v) {
			var kit = document.querySelector('[class*="elementor-kit-"]') || document.body;
			v = getComputedStyle(kit).getPropertyValue('--e-global-color-' + nombre);
		}
		return (v || '').trim();
	}

	/** Las piezas de verdad de un contenedor: los widgets que hay dentro y, en
	 *  una lista de precios o de iconos, cada línea suelta. Nunca los
	 *  contenedores: animando la columna entera el bloque entra de golpe, que
	 *  es justo lo que no queremos. El orden es el del documento, o sea el de
	 *  lectura: primero la columna de texto, después la lista. */
	function piezasDe(raiz) {
		var LISTAS = 'ul.elementor-price-list, ul.elementor-icon-list-items';
		var piezas = [];
		Array.prototype.forEach.call(raiz.querySelectorAll('.elementor-widget'), function (w) {
			// Un widget dentro de otro widget no cuenta dos veces.
			if (w.parentElement && w.parentElement.closest('.elementor-widget')) { return; }
			var lista = w.querySelector(LISTAS);
			var lineas = lista ? Array.prototype.slice.call(lista.children) : [];
			piezas = piezas.concat(lineas.length > 1 ? lineas : [w]);
		});
		if (piezas.length) { return piezas; }
		// Un contenedor sin widgets (solo cajas): entran sus hijos directos.
		var interior = raiz.querySelector(':scope > .e-con-inner') || raiz;
		return Array.prototype.slice.call(interior.children);
	}

	/* Línea de entrada: algo entra cuando le queda un 12 % de pantalla por
	   debajo, no cuando asoma el primer píxel. Sin umbral de proporción, así
	   que da igual que el elemento sea más alto que la pantalla. */
	var MARGEN = { rootMargin: '0px 0px -12% 0px', threshold: 0 };

	/* Red de seguridad. Lo que está pegado al final del documento —la barra
	   baja del pie, sin ir más lejos— nunca cruza esa línea: con el scroll al
	   tope se queda por debajo y no entra jamás. Igual pasa en una página tan
	   corta que cabe entera en una pantalla. Para eso vale con verlo entero. */
	var ENTERO = { threshold: 1 };

	/* Lo que ya está en pantalla al cargar quiere entrar en el peor momento:
	   con el navegador todavía descargando y decodificando la foto grande del
	   hero. Los primeros fotogramas se pierden y la coreografía sale a
	   tirones. Se le da pista: el primer lote espera a que la página termine
	   de cargar, con tope de 800 ms por si algo se atasca. Lo que llega
	   después, bajando, entra al momento. */
	var asentada = false;
	var enEspera = [];

	function asentar() {
		if (asentada) { return; }
		asentada = true;
		var cola = enEspera.slice();
		enEspera.length = 0;
		cola.forEach(function (fn) { fn(); });
	}

	if (document.readyState === 'complete') {
		asentar();
	} else {
		window.addEventListener('load', asentar, { once: true });
		window.setTimeout(asentar, 800);
	}

	function cuandoAsiente(fn) {
		if (asentada) { fn(); return; }
		enEspera.push(fn);
	}

	/* Con la intro tapando la página, lo que entra ya está en pantalla pero
	   nadie lo ve: se espera a que la sábana destape de verdad. La sábana ya
	   hace de pista, así que ahí no hace falta esperar a la carga. */
	function cuandoSeVea(fn) {
		if (document.documentElement.classList.contains('cm-intro-activa')) {
			document.addEventListener('cm:intro:sale', function () { requestAnimationFrame(fn); }, { once: true });
			return;
		}
		cuandoAsiente(function () { requestAnimationFrame(fn); });
	}

	/** Vigila un grupo de elementos y avisa de cada uno cuando le toca: al
	 *  cruzar la línea de entrada o al verse entero, lo que ocurra primero.
	 *  Cada elemento avisa una sola vez. */
	function vigilar(elementos, aviso) {
		var pendientes = elementos.slice();
		var ojos = [];

		function apuntar(el) {
			var i = pendientes.indexOf(el);
			if (i === -1) { return false; }
			pendientes.splice(i, 1);
			ojos.forEach(function (io) { io.unobserve(el); });
			return true;
		}

		[MARGEN, ENTERO].forEach(function (ajustes) {
			var entero = ajustes === ENTERO;
			var io = new IntersectionObserver(function (entradas) {
				var llegan = [];
				entradas.forEach(function (e) {
					if (!e.isIntersecting) { return; }
					// El 1 exacto se escapa por los decimales del navegador.
					if (entero && e.intersectionRatio < 0.99) { return; }
					if (apuntar(e.target)) { llegan.push(e.target); }
				});
				if (!llegan.length) { return; }
				if (!pendientes.length) { ojos.forEach(function (o) { o.disconnect(); }); }
				aviso(llegan);
			}, ajustes);
			elementos.forEach(function (el) { io.observe(el); });
			ojos.push(io);
		});
	}

	/** Va soltando las piezas según le toca a cada una, agrupando en un mismo
	 *  lote las que aparecen a la vez para que entren escalonadas. */
	function enLote(piezas, fn) {
		var lote = [];
		var esperando = false;

		function soltar() {
			esperando = false;
			var grupo = lote.slice();
			lote.length = 0;
			if (grupo.length) { fn(grupo); }
		}

		vigilar(piezas, function (llegan) {
			lote.push.apply(lote, llegan);
			if (esperando) { return; }
			esperando = true;
			cuandoSeVea(soltar);
		});
	}

	/** Dispara una sola vez, cuando el contenedor llega a la vista. No mira qué
	 *  proporción ocupa: un bloque más alto que la pantalla también cuenta. */
	function alLlegar(el, fn) {
		vigilar([el], function () { cuandoSeVea(fn); });
	}

	/** Dispara una vez cuando el elemento ocupa de verdad la pantalla.
	 *  Más fiable que ScrollTrigger para entradas: no depende de medidas
	 *  tomadas antes de que carguen tipografías e imágenes. */
	function alEntrar(el, umbral, fn) {
		var hecho = false;
		var io = new IntersectionObserver(function (entradas) {
			entradas.forEach(function (e) {
				if (!hecho && e.isIntersecting && e.intersectionRatio >= umbral) {
					hecho = true;
					io.disconnect();
					// Con la intro tapando la página, el elemento ya está en pantalla
					// pero nadie lo ve: la entrada espera a que la sábana empiece a
					// subir, y así ocurre a la vista y no a oscuras.
					if (document.documentElement.classList.contains('cm-intro-activa')) {
						document.addEventListener('cm:intro:sale', fn, { once: true });
						return;
					}
					fn();
				}
			});
		}, { threshold: [umbral] });
		io.observe(el);
	}

	// ── Motor de inercia ────────────────────────────────────────────────

	var lenis = null;
	if (conf.inercia !== false && typeof window.Lenis !== 'undefined' && (!tactil || conf.enTactil)) {
		lenis = new Lenis({
			duration: conf.duracion || 1.25,
			easing: function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); },
			smoothWheel: true,
			syncTouch: false
		});
		lenis.on('scroll', ScrollTrigger.update);
		gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
		gsap.ticker.lagSmoothing(0);
		// Con Lenis activo, window.scrollTo deja de mover la página. Se expone
		// la instancia para que cualquier otro script del sitio pueda usar
		// CaracoolMotion.lenis.scrollTo(...) en vez de pelearse con ella.
		CM.lenis = lenis;
	}

	function irA(destino) {
		if (lenis) { lenis.scrollTo(destino, { duration: 1.2 }); return; }
		var y = typeof destino === 'number' ? destino : destino.getBoundingClientRect().top + window.scrollY;
		window.scrollTo({ top: y, behavior: 'smooth' });
	}

	var ctx = {
		gsap: window.gsap,
		ScrollTrigger: window.ScrollTrigger,
		lenis: lenis,
		irA: irA,
		alEntrar: alEntrar,
		piezasDe: piezasDe,
		enLote: enLote,
		alLlegar: alLlegar,
		fondoDe: fondoDe,
		colorGlobal: colorGlobal,
		colorDeVariable: colorDeVariable,
		fondoListo: fondoListo,
		dur: dur
	};

	// ── Efectos de serie ────────────────────────────────────────────────

	/* Cortina: la sección llega tapada por una capa del color de la sección
	   anterior y se destapa al deslizarse. Con este efecto el contenido no se
	   anima: si además entrara el texto, se vería colocado, luego tapado y
	   luego moviéndose otra vez. */
	CM.registrar('cortina', function (el, op, c) {
		// Color elegido en Elementor (selector nativo) o, si está vacío, el
		// fondo de la sección anterior, que es lo que hace que parezca que la
		// sección de arriba se desliza para dejar ver esta.
		var color = c.colorDeVariable(el, '--cm-cortina-color')
			|| c.fondoDe(el.previousElementSibling || document.body);

		c.fondoListo(el);

		var capa = document.createElement('div');
		capa.className = 'cm-capa';
		capa.setAttribute('aria-hidden', 'true');
		capa.style.background = color;
		el.classList.add('cm-recorta');
		el.prepend(capa);

		// «Entra después»: el contenido va por encima de la cortina y aparece
		// cuando ya ha pasado, así nunca se ve colocado, tapado y moviéndose.
		if (op.contenido === 'despues') {
			var interior = el.querySelector(':scope > .e-con-inner') || el;
			var bloques = Array.prototype.filter.call(el.children, function (h) { return !h.classList.contains('cm-capa') && !h.classList.contains('elementor-motion-effects-container'); });
			bloques.forEach(function (b) { b.classList.add('cm-encima'); });
			// Las piezas que entran escalonadas: los widgets de dentro, no el bloque entero.
			var piezas = [];
			(interior === el ? bloques : [interior]).forEach(function (b) {
				var dentro = b.querySelector(':scope > .e-con-inner') || b;
				var hijos = Array.prototype.slice.call(dentro.children);
				piezas = piezas.concat(hijos.length ? hijos : [b]);
			});
			c.gsap.set(piezas, { opacity: 0, y: 30 });
			c.alEntrar(el, 0.45, function () {
				c.gsap.to(piezas, {
					opacity: 1, y: 0, duration: 0.55 * c.dur(op) + 0.5,
					ease: 'power3.out', stagger: 0.09, clearProps: 'opacity,transform'
				});
			});
		}

		var salida = (op.direccion === 'izquierda') ? -101 : 101;
		var velocidad = c.dur(op);

		c.gsap.set(capa, { xPercent: 0 });
		c.gsap.to(capa, {
			xPercent: salida,
			ease: 'power2.inOut',
			scrollTrigger: {
				trigger: el,
				start: 'top 60%',
				end: 'top ' + Math.round(-10 * velocidad) + '%',
				scrub: velocidad
			}
		});
	});

	/* La foto crece: la sección se queda fija y su primera imagen crece hasta
	   ocupar la pantalla. El resto del contenido aparece encima al final. */
	CM.registrar('crece', function (el, op, c) {
		// El panel que se queda fijo es el contenido, nunca el contenedor alto:
		// en un contenedor a ancho completo Elementor no genera .e-con-inner,
		// así que hay que caer al primer contenedor hijo.
		var interior = el.querySelector(':scope > .e-con-inner')
			|| el.querySelector(':scope > .e-con')
			|| el;
		var img = el.querySelector('.elementor-widget-image');
		if (!img || interior === el) { return; }

		c.fondoListo(el);
		interior.classList.add('cm-fijo');
		img.classList.add('cm-marco');

		// Todo lo que no sea la imagen se convierte en el rótulo de encima.
		// El rótulo que aparece sobre la foto. Si el contenido va en UN
		// contenedor hermano de la imagen, ese contenedor es el rótulo: se
		// coloca encima de la foto a pantalla completa y su padding, su
		// justificación y su alineación mandan, como en cualquier sección de
		// Elementor. Si hay widgets sueltos, se envuelven en una caja centrada.
		var sueltos = Array.prototype.filter.call(interior.children, function (h) {
			return h !== img && !h.classList.contains('cm-rotulo') && !h.classList.contains('cm-velo') && !h.classList.contains('cm-progreso');
		});
		var rotulo;
		if (sueltos.length === 1 && sueltos[0].classList.contains('e-con')) {
			rotulo = sueltos[0];
			rotulo.classList.add('cm-rotulo', 'cm-rotulo-propio');
		} else {
			rotulo = document.createElement('div');
			rotulo.className = 'cm-rotulo cm-rotulo-caja';
			sueltos.forEach(function (hijo) { rotulo.appendChild(hijo); });
			interior.appendChild(rotulo);
		}

		// Velo opcional entre la foto y el rótulo: entra con el texto y se va con él.
		// El color lo escribe Elementor en el contenedor como --cm-velo-color
		// (selector de color nativo: global del Kit o uno cualquiera). Vacío: sin velo.
		var velo = null;
		var veloColor = c.colorDeVariable(el, '--cm-velo-color');
		var veloOpacidad = Math.max(0, Math.min(100, parseFloat(op.velo_opacidad))) || 0;
		if (veloColor && veloOpacidad > 0) {
			velo = document.createElement('div');
			velo.className = 'cm-velo';
			velo.setAttribute('aria-hidden', 'true');
			velo.style.setProperty('--cm-velo-color', veloColor);
			interior.appendChild(velo);
		}

		// Línea de progreso: interruptor y grosor en px. El color llega como
		// --cm-barra-color desde el selector de color nativo (vacío: Énfasis del Kit).
		var barra = null;
		if (op.barra !== 'no') {
			barra = document.createElement('div');
			barra.className = 'cm-progreso';
			barra.setAttribute('aria-hidden', 'true');
			barra.innerHTML = '<i></i>';
			var barraColor = c.colorDeVariable(el, '--cm-barra-color');
			if (barraColor) { barra.style.setProperty('--cm-barra-color', barraColor); }
			var grosor = Math.max(1, Math.min(8, parseFloat(op.barra_grosor))) || 2;
			barra.style.setProperty('--cm-barra-grosor', grosor + 'px');
			interior.appendChild(barra);
		}

		var ancho = function () { return window.innerWidth > 860 ? 320 : 220; };
		var alto = function () { return window.innerWidth > 860 ? 440 : 300; };

		var tl = c.gsap.timeline({
			scrollTrigger: { trigger: el, start: 'top top', end: 'bottom bottom', scrub: c.dur(op), invalidateOnRefresh: true }
		});
		// Crece hasta el tamaño real del panel, no hasta el de la ventana. En
		// iOS no son lo mismo: `window.innerHeight` sube y baja con la barra
		// de Safari mientras el panel mide `100svh` y no se mueve, así que la
		// foto se quedaba corta y dejaba un dedo de fondo arriba y abajo.
		tl.fromTo(img,
			{ width: ancho, height: alto },
			{
				width: function () { return interior.clientWidth; },
				height: function () { return interior.clientHeight; },
				ease: 'power2.inOut', duration: 1
			});
		// pointerEvents al final: mientras el rótulo es invisible no debe
		// interceptar clics sobre la fotografía.
		tl.addLabel('texto', '>-0.15');
		tl.to(rotulo, { opacity: 1, pointerEvents: 'auto', duration: 0.35 }, 'texto');
		if (velo) { tl.to(velo, { opacity: veloOpacidad / 100, duration: 0.35 }, 'texto'); }

		// La barra va DENTRO de la misma línea de tiempo, no en un
		// ScrollTrigger aparte: con dos disparadores distintos la vuelta
		// atrás no queda sincronizada y la barra se quedaba llena al subir.
		// Se anima un número de avance, no un ancho: el CSS decide si eso es
		// ancho (escritorio, línea abajo) o alto (móvil, línea de pie a la
		// izquierda), y girar el teléfono no necesita recargar.
		if (barra) {
			tl.fromTo(barra,
				{ '--cm-avance': 0 },
				{ '--cm-avance': 1, ease: 'none', duration: tl.duration() || 1 }, 0);
		}
	});

	/* Entrada escalonada: las piezas suben y aparecen, en orden de lectura.
	   Dos disparos: «al llegar al bloque» lanza toda la coreografía de una vez
	   (el visitante ve el bloque completo aunque siga bajando) y «pieza a
	   pieza» revela cada una cuando llega a ella, atada al scroll. */
	CM.registrar('entrada', function (el, op, c) {
		var piezas = c.piezasDe(el);
		if (!piezas.length) { return; }

		var v = c.dur(op);
		c.gsap.set(piezas, { opacity: 0, y: 22 });

		function entrar(grupo, seguidas) {
			// Pieza a pieza, 70 ms de separación. Todas de una vez, el mismo
			// ritmo pero con techo: un bloque de treinta líneas no puede tardar
			// dos segundos y medio en acabar de salir.
			var salto = seguidas
				? { amount: Math.min(0.07 * (grupo.length - 1), 1.2) }
				: 0.07;
			c.gsap.to(grupo, {
				opacity: 1, y: 0,
				duration: 0.6 * v + 0.3, ease: 'power3.out', stagger: salto,
				clearProps: 'opacity,transform'
			});
		}

		if (op.disparo === 'piezas') {
			c.enLote(piezas, function (grupo) { entrar(grupo, false); });
			return;
		}
		c.alLlegar(el, function () { entrar(piezas, true); });
	});

	/* Gira hasta plantarse: para una marca o un icono grande de fondo. */
	CM.registrar('gira', function (el, op, c) {
		var interior = el.querySelector(':scope > .e-con-inner') || el;
		c.gsap.set(interior, { transformOrigin: '50% 50%', rotation: -60, scale: 0.6, opacity: 0 });
		c.alEntrar(el, 0.15, function () {
			c.gsap.to(interior, { rotation: 0, scale: 1, opacity: 1, duration: 1.1 * c.dur(op) + 0.5, ease: 'expo.out', clearProps: 'opacity,transform' });
		});
	});

	/* La marca se planta y el disco crece: la coreografía de la intro, para un
	   logotipo enorme de fondo. Deduce las piezas igual que el módulo Intro:
	   la forma con más área es el disco; las que caen fuera de él, la marca. */
	CM.registrar('marca', function (el, op, c) {
		var svg = el.querySelector('svg');
		if (!svg) { return; }
		var formas = Array.prototype.slice.call(svg.querySelectorAll('path, polygon, circle, ellipse, rect'));
		if (formas.length < 2) { return; }
		var cajas = formas.map(function (f) { var b = f.getBBox(); return { el: f, b: b, area: b.width * b.height, cx: b.x + b.width / 2, cy: b.y + b.height / 2 }; });
		var disco = cajas.reduce(function (m, x) { return x.area > m.area ? x : m; }, cajas[0]);
		var r = Math.max(disco.b.width, disco.b.height) / 2;
		var marca = cajas.filter(function (x) { return x !== disco && Math.hypot(x.cx - disco.cx, x.cy - disco.cy) > r * 0.98; }).map(function (x) { return x.el; });
		var resto = cajas.filter(function (x) { return x !== disco && marca.indexOf(x.el) < 0; }).map(function (x) { return x.el; });

		c.gsap.set(marca, { transformOrigin: '50% 50%', rotation: -90, scale: 0, opacity: 0 });
		c.gsap.set(disco.el, { transformOrigin: '50% 50%', scale: 0 });
		if (resto.length) { c.gsap.set(resto, { opacity: 0 }); }

		// El contenedor puede ser enorme y estar casi todo fuera: basta con que asome.
		c.alEntrar(el, 0.01, function () {
			var tl = c.gsap.timeline();
			tl.to(marca, { rotation: 0, scale: 1, opacity: 1, duration: 0.6, ease: 'back.out(1.8)' });
			tl.to(disco.el, { scale: 1, duration: 1.1 * c.dur(op) + 0.6, ease: 'expo.out' }, 0.35);
			if (resto.length) { tl.to(resto, { opacity: 1, duration: 0.6, stagger: 0.03, ease: 'power2.out' }, 0.9); }
		});
	});

	/* Parallax del contenido. */
	CM.registrar('parallax', function (el, op, c) {
		if (window.innerWidth < 861) { return; }
		var interior = el.querySelector(':scope > .e-con-inner') || el;
		var v = { lenta: 4, media: 8, rapida: 14 }[op.velocidad] || 8;
		c.gsap.fromTo(interior, { yPercent: -v }, {
			yPercent: v, ease: 'none',
			scrollTrigger: { trigger: el, start: 'top bottom', end: 'bottom top', scrub: true }
		});
	});

	// ── Aplicación ──────────────────────────────────────────────────────

	function opcionesDe(el) {
		var op = {};
		Array.prototype.forEach.call(el.attributes, function (a) {
			if (a.name.indexOf('data-cm-') === 0 && a.name !== 'data-cm-efecto') {
				op[a.name.slice(8)] = a.value;
			}
		});
		return op;
	}

	CM.aplicar = function (soloEste) {
		document.querySelectorAll('[data-cm-efecto]').forEach(function (el) {
			var nombre = el.getAttribute('data-cm-efecto');
			if (soloEste && nombre !== soloEste) { return; }
			if (el.dataset.cmHecho === '1') { return; }
			var fn = CM.efectos[nombre];
			if (typeof fn !== 'function') { return; }
			el.dataset.cmHecho = '1';
			try {
				fn(el, opcionesDe(el), ctx);
			} catch (e) {
				if (window.console) { console.warn('Caracool Motion · fallo en el efecto "' + nombre + '"', e); }
			}
		});
	};

	// Los efectos se preparan ya, también bajo la intro: así el contenido que
	// tiene que entrar está oculto mientras sube la sábana y no se ve «puesto,
	// quitado y puesto». Las entradas esperan a cm:intro:sale (ver alEntrar).
	CM.aplicar();
	CM._listo = true;
	// Cada pieza ya está donde tiene que estar: se puede quitar la tapa.
	destapar();
	document.addEventListener('cm:intro:fin', function () { ScrollTrigger.refresh(); }, { once: true });

	// ── Navegación por puntos ───────────────────────────────────────────

	if (conf.puntos && window.innerWidth > 860) {
		// El documento de la página, no la cabecera ni el pie del Maquetador de
		// temas: esos también son «.elementor» y salen antes en el HTML.
		var contenedorPagina = document.querySelector('[data-elementor-type="wp-page"], [data-elementor-type="wp-post"], [data-elementor-type="page"], [data-elementor-type="single"], [data-elementor-type="single-page"], [data-elementor-type="single-post"]');
		if (!contenedorPagina) {
			var candidatos = Array.prototype.slice.call(document.querySelectorAll('.elementor')).filter(function (e) {
				return !e.classList.contains('elementor-location-header') && !e.classList.contains('elementor-location-footer') && e.querySelector(':scope > .e-con');
			});
			contenedorPagina = candidatos[0] || null;
		}

		if (contenedorPagina) {
			var secciones = Array.prototype.slice.call(contenedorPagina.children).filter(function (s) {
				return s.classList.contains('e-con') && s.offsetHeight > window.innerHeight * 0.4;
			});

			if (secciones.length >= 3) {
				var nav = document.createElement('nav');
				nav.className = 'cm-puntos';
				nav.setAttribute('aria-label', 'Secciones de la página');
				if (conf.puntosColor) { nav.style.setProperty('--cm-puntos-color', 'var(' + conf.puntosColor + ')'); }

				var puntos = [];

				function flecha(dir) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'cm-flecha';
					b.setAttribute('aria-label', dir < 0 ? 'Sección anterior' : 'Sección siguiente');
					b.innerHTML = '<svg width="16" height="9" viewBox="0 0 16 9" fill="none" aria-hidden="true" style="transform:rotate(' + (dir < 0 ? 180 : 0) + 'deg)"><path d="M1 1l7 7 7-7" stroke="currentColor" stroke-width="1.4"/></svg>';
					b.addEventListener('click', function () {
						var y = window.scrollY, idx = 0, dist = 1e9;
						secciones.forEach(function (s, i) {
							var d = Math.abs(s.getBoundingClientRect().top + window.scrollY - y);
							if (d < dist) { dist = d; idx = i; }
						});
						var destino = secciones[Math.min(secciones.length - 1, Math.max(0, idx + dir))];
						if (destino) { irA(destino); }
					});
					return b;
				}

				nav.appendChild(flecha(-1));

				secciones.forEach(function (sec, i) {
					var t = sec.querySelector('h6, h5');
					var t2 = sec.querySelector('h1, h2');
					var nombre = (t && t.textContent.trim()) || (t2 && t2.textContent.trim()) || ('Sección ' + (i + 1));
					if (nombre.length > 26) { nombre = nombre.slice(0, 24) + '…'; }

					var env = document.createElement('div');
					env.className = 'cm-punto-env';

					var etiqueta = document.createElement('span');
					etiqueta.className = 'cm-etiqueta';
					etiqueta.textContent = nombre;
					etiqueta.setAttribute('aria-hidden', 'true');

					var p = document.createElement('button');
					p.type = 'button';
					p.setAttribute('aria-label', 'Ir a ' + nombre);
					p.addEventListener('click', function () { irA(sec); });

					env.appendChild(etiqueta);
					env.appendChild(p);
					nav.appendChild(env);
					puntos.push(p);

					new IntersectionObserver(function (e) {
						e.forEach(function (x) {
							if (x.isIntersecting) {
								puntos.forEach(function (q) { q.classList.remove('cm-activo'); });
								p.classList.add('cm-activo');
							}
						});
					}, { rootMargin: '-45% 0px -45% 0px', threshold: 0 }).observe(sec);
				});

				nav.appendChild(flecha(1));
				document.body.appendChild(nav);
			}
		}
	}

	// ── Anclas suaves ───────────────────────────────────────────────────

	document.querySelectorAll('a[href^="#"]').forEach(function (a) {
		var destino = a.getAttribute('href');
		if (!destino || destino === '#') { return; }
		a.addEventListener('click', function (e) {
			var el = document.querySelector(destino);
			if (el) { e.preventDefault(); irA(el); }
		});
	});

	// ── Recalcular posiciones ───────────────────────────────────────────
	// ScrollTrigger mide al cargar. Si la tipografía llega después, la página
	// cambia de alto y todos los disparos quedan corridos.

	function refrescar() { ScrollTrigger.refresh(); }

	if (document.fonts && document.fonts.ready) {
		document.fonts.ready.then(function () { setTimeout(refrescar, 60); });
	}
	window.addEventListener('load', function () { setTimeout(refrescar, 150); });

	var temporizador;
	window.addEventListener('resize', function () {
		clearTimeout(temporizador);
		temporizador = setTimeout(refrescar, 250);
	});
})();
