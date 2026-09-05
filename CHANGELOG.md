# Changelog — Caracool Motion

Numeración: se sube 0.1 en 0.1 cuando una tanda de cambios queda cerrada y
confirmada. Los arreglos sobre algo aún sin confirmar no suben número.

## 0.5.0 (5 de septiembre de 2026)

Todos los colores que se eligen desde Elementor pasan al selector nativo, y la
entrada escalonada pasa a animar piezas en vez de bloques.

- **Con movimiento reducido, «la foto crece» ya no deja un hueco.** El efecto
  necesita una sección de varias pantallas para tener recorrido de scroll —en
  la home son 260 vh—, y esa altura la escribe Elementor. Con el movimiento
  reducido activado el JavaScript se retira entero, así que la sección se
  quedaba con su altura de recorrido y sin nada dentro: casi tres pantallas de
  vacío después de la foto. Ahora la hoja de estilos devuelve esa sección a la
  altura de su contenido, y la foto pasa a ocupar el ancho del bloque. El resto
  de efectos no se tocan: la sección solo se encoge si lleva «la foto crece».
- **Entrada escalonada, de nuevo**: ahora entran las piezas de verdad (cada
  texto, cada botón y, en una lista de precios o de iconos, cada línea), en
  orden de lectura, en vez de las columnas del contenedor. Antes se movía el
  bloque entero de una pieza.
- Cada pieza **se activa cuando le toca a ella**, y las que aparecen a la vez
  entran escalonadas en el mismo lote. Con el umbral anterior (el contenedor
  tenía que ocupar el 35 % de la pantalla) un bloque más alto que tres
  pantallas no llegaba a revelarse nunca: en móvil la carta se quedaba
  invisible. Ya no depende de la altura del contenedor.
- Nueva opción **Cuándo entra**: «Todo al llegar al bloque» (de fábrica), que
  lanza la coreografía entera en cuanto la sección aparece, y «Cada pieza al
  llegar a ella», que ata la entrada al scroll. La primera evita que ciertos
  elementos queden a medio camino mientras se baja; la segunda es la de antes.
  Lanzando todo de una vez, la cascada tiene techo de 1,2 s para que un bloque
  de treinta líneas no tarde una eternidad en acabar.
- **«La foto crece» llena su panel de verdad.** Crecía hasta el tamaño de la
  ventana y el panel mide `100svh`: en iOS no son lo mismo —`window.innerHeight`
  sube y baja con la barra de Safari— así que la foto se quedaba corta y dejaba
  un dedo de fondo arriba y abajo. Ahora crece hasta el tamaño real del panel.
- **La línea de avance, de pie en móvil.** Va pegada al borde izquierdo de la
  foto, de arriba abajo, y crece hacia abajo conforme avanzas. Abajo acababa
  peleándose con la barra del navegador, y en Safari se quedaba fuera de lo
  visible. En escritorio sigue abajo y horizontal. El JavaScript anima un
  número de avance de 0 a 1 y el CSS decide si eso es ancho o alto, así que
  girar el teléfono la recoloca sola sin recargar.
- **Se acabó el parpadeo del primer bloque.** El JavaScript del módulo va al
  final del `body`, así que el navegador pintaba el contenido, el efecto lo
  escondía y luego lo hacía entrar: en el hero se veía puesto, quitado y
  puesto. Ahora la cabecera imprime una regla que deja tapadas las piezas de
  los bloques con entrada **antes del primer pintado**, y el JS la retira en
  cuanto ha colocado cada una. Tapa las piezas, no el contenedor, para que la
  foto de fondo se vea desde el primer momento. Con movimiento reducido no se
  pone, y si el plugin no llegara a arrancar se quita sola a los dos segundos.
- **Pista para el primer lote.** Lo que ya está en pantalla al cargar quería
  entrar en el peor momento, con el navegador aún decodificando la foto grande
  del hero: los primeros fotogramas se perdían y la coreografía salía a
  tirones. Ahora ese primer lote espera a que la página termine de cargar, con
  tope de 800 ms. Lo que llega después, bajando, entra al momento.
- **La intro avisa cuando destapa de verdad.** La salida de la sábana va con
  `expo.inOut`, que a mitad de tiempo lleva recorrido un 3 %: avisando al
  empezar el tramo, la coreografía del hero corría a oscuras y, al destaparse,
  el título ya estaba puesto y solo quedaban entrando las últimas piezas,
  sueltas y lentas. El aviso `cm:intro:sale` sale ahora del **recorrido de la
  propia sábana** —cuando lleva un cuarto del camino—, no del reloj, así que
  vale igual para las dos salidas y para cualquier curva que se ponga después.
- **Lo pegado al final de la página también entra.** La línea de entrada está
  un 12 % por encima del borde inferior de la pantalla, y un contenedor que
  termina justo al final del documento nunca llega a cruzarla: con el scroll al
  tope se queda por debajo. La barra baja del pie de Por Herencia —los sellos,
  el aviso legal y el «hecho con ♥»— no aparecía nunca. Ahora, además de la
  línea, se mira si el elemento se ve entero: lo que ocurra primero lo hace
  entrar. Con esto entran también los pies de las páginas tan cortas que caben
  en una pantalla.

- La **cortina lateral** elige su color con el selector de color nativo de
  Elementor (globales del Kit o cualquier color). Vacío, como hasta ahora:
  el fondo de la sección anterior.
- Los valores guardados con versiones anteriores (nombres de colores globales
  en los antiguos desplegables) se siguen entendiendo: el JS los resuelve
  contra el Kit.
- La cortina admite **Contenido: entra después**: el contenido va por encima
  de la cortina y aparece cuando ya ha pasado (la opción B «máscara» del
  laboratorio). Con una foto de fondo y la superposición de Elementor, la
  tapa se retira y descubre la foto.
- «La foto crece»: **el contenedor del contenido manda**. Si el contenido va
  en un contenedor hermano de la imagen, el plugin lo coloca sobre la foto a
  pantalla completa y no toca nada más: el padding, la justificación, la
  alineación y el ancho de la columna se deciden en Elementor, como en
  cualquier sección. Los widgets sueltos siguen saliendo en una caja centrada.
- Cabecera: la **línea de separación** solo se ve con la cabecera a la vista y
  sólida (escondida asomaba un hilo por el borde de arriba) y su color se
  elige en el panel entre los globales del Kit.
- En las secciones con cortina o «la foto crece» el plugin quita la **carga
  perezosa de fondos de Elementor** y precarga la foto: antes, la cortina se
  retiraba antes de que la foto existiera y aparecía de golpe después.

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
- La cortina lateral pierde el filo de color Énfasis que llevaba en el borde:
  se veía como una línea fina en el lado derecho mientras la sección estaba
  tapada, antes de que la cortina empezara a moverse.
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
