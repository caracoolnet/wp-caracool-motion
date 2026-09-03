# Changelog — Caracool Motion

Numeración: se sube 0.1 en 0.1 cuando una tanda de cambios queda cerrada y
confirmada. Los arreglos sobre algo aún sin confirmar no suben número.

## 0.4.0 — 2 de septiembre de 2026

Módulo de intro.

- **Intro de entrada**: una sábana del color de la casa cubre la página, el
  logotipo se anima en el centro y la sábana sube (o se recorta de abajo
  arriba) dejando ver el resto. Solo la primera vez, recordado N días.
- Cinco animaciones del logotipo, ampliables por filtro
  (`caracool_motion_animaciones_intro`): del aspa al disco en tres tempos
  (freno largo, con asiento, respira), se derrama, y sello.
- **El logotipo se elige de la biblioteca** (SVG) y se incrusta en la página.
  Las piezas se deducen: la forma más grande es el disco, las que quedan fuera
  son la marca, el resto letras. Sirve para cualquier logotipo parecido.
- Color de la sábana entre los colores globales del Kit.
- Dónde: solo inicio o todas las páginas, y **por página** desde los Ajustes
  de página de Elementor (según el sitio / mostrar / no mostrar).
- La sábana se imprime con el HTML, no desde JavaScript, para que el hero no
  se vea antes de tiempo; un script en línea la quita antes del primer
  pintado si ya se vio. `?intro=1` la fuerza, `?intro=0` la salta.
- Los efectos de sección esperan al evento `cm:intro:fin` para no animarse a
  oscuras bajo la sábana.
- Tras la primera revisión de Ángel: **dónde** pasa a ser un interruptor para
  la portada más una lista de páginas concretas (se quita el control por
  página de Elementor, que sobraba); **tamaño del logotipo** en escritorio y
  móvil; y el panel del plugin pasa a tener **una pestaña por módulo**.
- «La foto crece» admite un **velo** entre la foto y el texto, que entra y sale
  con el texto: color global del Kit y opacidad final, contenedor a
  contenedor. Las opciones de efecto admiten ahora campos numéricos.
- La línea de progreso de «la foto crece» se configura en el contenedor:
  interruptor, color y grosor. El velo y la línea usan el **selector de color
  nativo de Elementor** (globales del Kit o cualquier color): Elementor escribe
  la variable CSS en el contenedor y el plugin solo la lee.
- Efecto de sección **la marca se planta y el disco crece**: la coreografía de la
  intro aplicada a un logotipo enorme de fondo (el aspa y el disco del pie).
- Efecto de sección **gira hasta plantarse**, para una marca o icono grande de fondo (nació con el pie de página).
- Color de los puntos de navegación elegible entre los globales del Kit.
- Los recursos del plugin llevan en la URL la fecha del archivo además de la
  versión: cada build nuevo invalida la caché del navegador aunque el número
  no cambie.
- Los puntos buscan el documento de la página, no el primer `.elementor` (con
  cabecera del Maquetador de temas dejaban de salir).
- Intro: con 0 días se ignora y se borra el recuerdo; los efectos de sección
  se preparan bajo la sábana y las entradas arrancan en `cm:intro:sale`; el
  hueco de la barra de scroll se reserva para que la página no salte.

## 0.3.0 — 2 de septiembre de 2026

Módulo de cabecera y cambio de sitio de los controles en Elementor.

- **Cabecera inteligente**: la cabecera del Maquetador de temas se queda fija
  y transparente sobre la primera pantalla, se esconde al bajar y reaparece
  al subir con fondo sólido. Con el menú desplegable abierto no se esconde.
  Interruptor de sitio, umbral en píxeles y fondo elegido entre los colores
  globales del Kit (de sistema y personalizados), leídos del Kit activo.
- **Los controles de Caracool Motion pasan de la pestaña Avanzado a la
  pestaña Estilo** en contenedores y botones (petición de Ángel).
- Se anota en el panel que, con la cabecera inteligente activa, las páginas
  sin foto a pantalla completa necesitan su propio margen superior.

## 0.2.0 — 2 de septiembre de 2026

Módulo de botones.

- Animación al pasar el cursor por los botones: una banda de color barre el
  botón y el texto cambia de color letra a letra conforme el borde lo cruza.
- **Sin colores propios.** El efecto invierte los dos colores que el botón ya
  tiene puestos en Elementor, leídos con `getComputedStyle`. Un botón de línea
  se rellena con el color de su borde y pone el texto del color de la sección
  que tiene detrás; uno con fondo se rellena con su propio color de texto. El
  mismo módulo vale para cualquier cliente sin tocar una línea.
- Tres variantes: **de paso** (entra por la izquierda, sale por la derecha, no
  rebobina), **ida y vuelta** y **sigue al cursor**. Ampliables por filtro
  (`caracool_motion_variantes_boton`).
- Interruptor único de sitio, con variante y velocidad, en el panel de
  Caracool Motion. En cada botón de Elementor, un desplegable para dejarlo
  quieto: Avanzado → Caracool Motion.
- Se salta los botones cuyos dos colores salen iguales, en vez de mostrar un
  barrido invisible.
- Funciona también con teclado y con contenido que llega después (ventanas
  emergentes, carruseles, AJAX).

## 0.1.0 — 2 de septiembre de 2026

Primera versión. Módulo de scroll.

- Motor de scroll con inercia (Lenis), configurable: activar/desactivar,
  suavidad entre 0,6 y 2,5 segundos, y opción de activarlo también en táctil
  (desaconsejada).
- Cuatro efectos de sección elegibles desde el panel de Elementor, en
  Avanzado → Caracool Motion: cortina lateral, la foto crece, entrada
  escalonada y parallax.
- Catálogo de efectos ampliable por filtro (`caracool_motion_efectos_seccion`)
  y registro de comportamientos en JavaScript (`CaracoolMotion.registrar`),
  sin tocar los archivos del plugin.
- Navegación por puntos y flechas, opcional, con los nombres leídos del primer
  título de cada sección.
- Librerías (GSAP, ScrollTrigger, Lenis) servidas desde el propio dominio.
- Los recursos solo se imprimen si la página los usa, y nunca dentro del
  editor de Elementor.
- Todo el movimiento respeta `prefers-reduced-motion`.
- Comprobador de actualizaciones contra las releases del repositorio.
