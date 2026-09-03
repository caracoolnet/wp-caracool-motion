# Caracool Motion

Movimiento para webs hechas con Elementor: scroll con inercia y transiciones de sección que se eligen **desde el propio panel de Elementor**, sin escribir código, sin CSS suelto en los bloques y sin depender de servicios externos.

**Versión actual:** 0.4.0 · **Requiere:** WordPress 6.0+, Elementor 3.16+ (contenedores flexbox)

---

## Qué resuelve

Montar una web con movimiento suele acabar en fragmentos de JavaScript pegados en cada página, CSS repartido por las cajas y librerías cargadas desde CDN. Cuando otra persona toca la web, o cuando hay que cambiar un color, nada está donde debería.

Este plugin recoge todo eso en un sitio:

- **Las librerías van dentro del plugin**, servidas desde el propio dominio. Sin CDN, sin dependencias de terceros.
- **Los efectos se eligen en un desplegable** dentro de Elementor, contenedor por contenedor.
- **Los colores no se escriben**: se leen de las variables globales del Kit de Elementor, así que el mismo plugin funciona en cualquier cliente sin tocar una línea.
- **Nada se carga si no hace falta**: una página sin efectos no recibe ni un byte del plugin.

---

## Cómo se usa

1. Instalar y activar.
2. En **Caracool Motion** (menú lateral), una pestaña por módulo: Scroll, Botones, Cabecera, Intro. Un solo botón de guardar para todo.
3. En Elementor, seleccionar un contenedor → pestaña **Estilo** → sección **Caracool Motion** → elegir efecto.

Eso es todo. No hay clases que memorizar.

Los botones van por otro camino: se encienden una vez para toda la web desde el panel, y desde Elementor solo se puede dejar quieto un botón concreto.

### Efectos de sección

| Efecto | Qué hace | Opciones |
|---|---|---|
| **Cortina lateral** | La sección llega tapada por una capa del color de la sección anterior y se destapa al deslizarse. | Dirección, velocidad, color |
| **La foto crece** | La sección se queda fija mientras su primera imagen crece hasta ocupar la pantalla. El resto del contenido aparece encima al final, con una línea de progreso abajo. Opcionalmente, un **velo** entre la foto y el texto que entra con el texto. El velo y la línea usan el **selector de color nativo de Elementor** (globales del Kit o cualquier color); la línea se puede apagar y se le da grosor. | Velocidad, color del velo, opacidad del velo, línea sí/no, color de la línea, grosor |
| **Entrada escalonada** | Los elementos del contenedor entran de abajo arriba, uno detrás de otro. | Velocidad |
| **Parallax** | El contenido se desplaza más despacio que la página. | Velocidad |
| **La marca se planta y el disco crece** | Para un logotipo SVG con una forma grande y una marca fuera de ella: la marca entra girando y se planta, la forma grande crece desde su centro. La coreografía de la intro, para un logotipo enorme de fondo. | Velocidad |
| **Gira hasta plantarse** | El contenedor entra girando y creciendo un poco hasta quedarse quieto. Para una marca o un icono grande de fondo. | Velocidad |

### Botones

Al pasar el cursor, una banda de color barre el botón. **El plugin no decide ningún color**: lee con `getComputedStyle` los dos que el botón ya tiene puestos en Elementor y los invierte.

| Tipo de botón | Qué hace al pasar el cursor |
|---|---|
| **De línea** (fondo transparente) | Se rellena con el color de su borde; el texto pasa al color de la sección que tiene detrás. |
| **Con fondo** | Se rellena con su propio color de texto; el texto pasa al color que tenía de fondo. |

Un botón con fondo **no se vacía**. Se probó y no funciona: durante el barrido los dos botones se parecen y el principal parece perder peso justo cuando lo señalas. El hover debe confirmar la acción, no restarle.

La banda lleva su propia copia del contenido del botón, fondo y texto recortados con el mismo `clip-path`. Por eso cada letra cambia de color justo cuando el borde la cruza; sin ese detalle hay medio segundo con media palabra ilegible.

| Variante | Qué hace |
|---|---|
| **De paso** *(recomendada)* | Entra por la izquierda y al salir sigue y se va por la derecha. No rebobina, así que aguanta el ratón rápido sobre una fila. |
| **Ida y vuelta** | Entra por la izquierda y vuelve por donde vino. La más fácil de leer, algo nerviosa en grupo. |
| **Sigue al cursor** | Entra por donde llegas y sale por donde te vas. Para un botón grande y solo. |

Cuesta una capa por botón y una transición de `clip-path`, que va en la GPU. Cero JavaScript por fotograma.

### Cabecera

La cabecera se maqueta como siempre en **Plantillas → Maquetador de temas**. El módulo no la dibuja: la mueve y la pinta.

Con el interruptor encendido, la cabecera se queda **fija y transparente** sobre la primera pantalla; pasado el umbral, **se esconde al bajar y reaparece al subir**, ya con fondo sólido. Las composiciones a pantalla completa quedan limpias y el botón de reservar sigue a un gesto de distancia. Con el menú desplegable abierto no se esconde.

| Ajuste | Qué hace |
|---|---|
| **Se esconde a partir de** | Píxeles de scroll a partir de los cuales entra en juego. Recomendado: 120. |
| **Fondo al reaparecer** | Un color global del Kit, de sistema o personalizado. Cambia con el Kit. |
| **Línea de separación** | Un hilo suave bajo la cabecera sólida, para páginas claras. |

Al activarla, la cabecera deja de ocupar sitio arriba: **las páginas sin foto a pantalla completa necesitan su propio margen superior**.

### Intro de entrada

Una sábana del color de la casa cubre la página, el logotipo se anima en el centro y la sábana sube dejando ver el resto. **Solo la primera vez** que alguien entra; se recuerda los días que se elijan. Con el movimiento reducido activado no se muestra. Para verla otra vez: `?intro=1` en la dirección; `?intro=0` la salta.

| Ajuste | Qué hace |
|---|---|
| **En la página de inicio** | Interruptor para la portada. |
| **En otras páginas** | Lista de páginas publicadas: se marcan las que también llevan intro. Las demás no. |
| **Logotipo** | Un SVG de la biblioteca. Se incrusta en la página para animar cada trazo. |
| **Tamaño del logotipo** | Ancho en píxeles, en escritorio y en móvil. |
| **Color de la sábana** | Un color global del Kit. |
| **Animación** | Del aspa al disco (freno largo, con asiento, respira), se derrama, sello. |
| **Salida** | Sube como una sábana, o se recorta de abajo arriba. |
| **Tempo** | Multiplica la duración. Al 100 % dura unos 3,5 s. |
| **Recordar durante** | Días hasta volver a mostrarla. Con 0 se ve siempre (solo para ajustar). |

El módulo **no conoce el logotipo**: deduce que la forma con más área es el disco, que las formas cuyo centro cae fuera de él son la marca, y que el resto son letras (las de menos de un 60 % de la altura, letras pequeñas). Un logotipo sin marca fuera del disco cae en «sello».

La sábana y el logotipo se imprimen **con el HTML**, en `wp_body_open`, con su CSS crítico en `wp_head`: si se pintaran desde JavaScript, el hero se vería un instante antes. Un script en línea la quita antes del primer pintado cuando ya se ha visto. Al terminar emite `cm:intro:fin`, y los efectos de sección esperan a ese momento para no animarse a oscuras.

### Reglas de uso aprendidas en producción

- **Con cortina, el contenido no se anima.** La cortina ya es la animación. Si además entra el texto, se ve colocado, luego tapado y luego moviéndose otra vez. El plugin no aplica entradas dentro de un contenedor con cortina.
- **La cortina llega puesta, no entra.** Si apareciera desde fuera cuando la sección ya se ve a medias, taparía algo que el visitante ya había visto.
- **Una cortina del mismo color que su propia sección no se ve.** Por eso el color por defecto es el de la sección anterior: así la transición se percibe continua.
- **"La foto crece" solo una vez por página.** Es el momento fuerte; repetido, se convierte en un truco.
- Para «La foto crece», el contenedor necesita una **altura mínima de 240vh o más** y contener un widget Imagen.
- **Los botones se encienden para toda la web, no uno a uno.** Un sitio donde unos botones se animan y otros no se lee como un fallo, no como una decisión. La excepción existe por si acaso, no para usarla a diario.
- **Si los dos colores de un botón salen iguales, se queda quieto.** Un barrido invisible es peor que nada.

---

## Accesibilidad y rendimiento

- Todo el movimiento se desactiva si el visitante tiene **movimiento reducido** activado en su sistema.
- El scroll con inercia **no se activa en pantallas táctiles** por defecto: el sistema ya aplica la suya y las dos se estorban.
- Los recursos **no se cargan dentro del editor de Elementor**, para que maquetar no sea incómodo.
- La navegación por puntos se oculta en móvil y es navegable con teclado.

---

## Ampliar con un efecto nuevo

No hace falta tocar ningún archivo del plugin. Desde otro plugin, un tema hijo o un snippet:

Las variantes de los botones tienen su propio filtro, `caracool_motion_variantes_boton`, con la misma idea: declarada en PHP y sin comportamiento en JavaScript, cae en la de serie y no rompe nada.

```php
add_filter( 'caracool_motion_efectos_seccion', function ( $efectos ) {
    $efectos['zoom'] = array(
        'etiqueta' => 'Zoom de fondo',
        'ayuda'    => 'La imagen de fondo se acerca despacio al hacer scroll.',
        'opciones' => array( 'velocidad' ),
    );
    return $efectos;
} );
```

Y el comportamiento en JavaScript, en un script cargado después del plugin:

```js
CaracoolMotion.registrar('zoom', function (el, op, ctx) {
    // el  = el contenedor
    // op  = { velocidad: 'media' }
    // ctx = { gsap, ScrollTrigger, lenis, irA, alEntrar, fondoDe, colorGlobal, dur }
});
```

El desplegable de Elementor se construye a partir del filtro, así que la opción aparece sola. Un efecto declarado en PHP pero sin comportamiento en JavaScript simplemente no hace nada: **no rompe la página ni los demás efectos**.

---

## Estructura

```
caracool-motion/
├── caracool-motion.php      Archivo principal: ajustes, pestañas, actualizaciones
├── modules/
│   ├── cm-scroll.php        Módulo de scroll y transiciones de sección
│   ├── cm-botones.php       Módulo de botones
│   ├── cm-cabecera.php      Módulo de cabecera
│   └── cm-intro.php         Módulo de intro
└── assets/
    ├── cm-scroll.css        Solo estructura, sin colores de marca
    ├── cm-scroll.js         Registro de efectos y motor
    ├── cm-botones.css       Solo estructura
    ├── cm-botones.js        Lee los colores del botón y barre
    ├── cm-cabecera.css      Estados de la cabecera
    ├── cm-cabecera.js       Dirección del scroll y estados
    ├── cm-intro.js          Piezas del logotipo, animación y salida
    ├── gsap.min.js          3.12.5
    ├── ScrollTrigger.min.js 3.12.5
    └── lenis.min.js         1.1.20
```

Cada módulo vive en su archivo y se autorregistra. El archivo principal no sabe nada de la lógica interna de ninguno: solo expone `caracool_motion_settings_panels`. Cada módulo pinta una `<section class="cm-modulo" data-titulo="…">` y el archivo principal construye las pestañas a partir de ellas.

---

## Actualizaciones

El plugin comprueba las *releases* del repositorio y avisa en Plugins → Actualizaciones cuando hay una versión nueva, igual que cualquier plugin del directorio oficial.

---

## Módulos previstos

- **Animaciones de texto** — efectos de entrada y de hover en titulares y CTAs, elegidos widget a widget.
- **Cursor personalizado** — cursor propio con física de muelle e imán en los enlaces.
