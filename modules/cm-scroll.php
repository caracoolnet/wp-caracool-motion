<?php
/**
 * Caracool Motion — Módulo Scroll
 * ─────────────────────────────────────────────────────────────────────
 * Archivo aparte y autorregistrado: se puede desactivar borrándolo de la
 * lista de módulos del archivo principal sin tocar nada más.
 *
 * QUÉ HACE
 *  1. Añade una sección "Caracool Motion" en la pestaña ESTILO de los
 *     CONTENEDORES de Elementor, con un desplegable de efectos y sus
 *     opciones. Quien monta la página elige ahí, sin escribir clases.
 *  2. Inyecta los atributos data-cm-* en el contenedor al renderizar.
 *  3. Imprime el CSS/JS solo si esa página concreta usa algún efecto.
 *  4. Añade su pestaña de ajustes generales (scroll con inercia, puntos).
 *
 * CÓMO SE AMPLÍA CON UN EFECTO NUEVO
 *  No hay que tocar este archivo. En otro plugin o en un archivo propio:
 *
 *      add_filter( 'caracool_motion_efectos_seccion', function ( $fx ) {
 *          $fx['zoom'] = array(
 *              'etiqueta' => 'Zoom de fondo',
 *              'opciones' => array( 'velocidad' ),
 *          );
 *          return $fx;
 *      } );
 *
 *  y en JavaScript:
 *
 *      CaracoolMotion.registrar('zoom', function (el, op, ctx) { ... });
 *
 *  El desplegable de Elementor se construye a partir del filtro, así que la
 *  opción nueva aparece sola. Un efecto sin comportamiento registrado en JS
 *  no hace nada y no rompe los demás.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Motion_Scroll {

	const OPTION_KEY = 'caracool_motion_scroll';

	/** true en cuanto se inyecta de verdad un efecto en la página servida. */
	private static $necesita_assets = false;

	public function __construct() {
		add_action( 'elementor/element/container/section_border/after_section_end', array( $this, 'controles_contenedor' ), 10, 2 );
		add_action( 'elementor/frontend/before_render', array( $this, 'inyectar_atributos' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_assets' ) );
		add_action( 'wp_head', array( $this, 'imprimir_guarda' ), 1 );
		add_action( 'wp_footer', array( $this, 'imprimir_assets' ), 5 );

		add_action( 'caracool_motion_settings_panels', array( $this, 'panel' ) );
		add_action( 'admin_post_caracool_motion_save', array( $this, 'guardar' ), 5 );
	}

	// ── Catálogo de efectos (ampliable por filtro) ──────────────────────

	public static function efectos() {
		return apply_filters(
			'caracool_motion_efectos_seccion',
			array(
				'cortina'  => array(
					'etiqueta' => 'Cortina lateral',
					'ayuda'    => 'La sección llega tapada por una capa del color de la sección anterior y se destapa al deslizarse. Con este efecto el contenido no se anima: la cortina ya es la animación.',
					'opciones' => array( 'direccion', 'velocidad', 'color', 'contenido' ),
				),
				'crece'    => array(
					'etiqueta' => 'La foto crece',
					'ayuda'    => 'La sección se queda fija mientras la primera imagen que contenga crece hasta ocupar la pantalla. El resto del contenido aparece encima al final, y con él, si se quiere, un velo entre la foto y el texto para que se lea. Da al contenedor una altura mínima de 240vh o más. Estructura: dentro, un contenedor con la imagen y UN contenedor hermano con el contenido; ese contenedor pasa a ocupar toda la pantalla sobre la foto y su padding, justificación y alineación mandan (como en cualquier sección). Si hay widgets sueltos en vez de un contenedor, salen centrados.',
					'opciones' => array( 'velocidad', 'velo', 'velo_opacidad', 'barra', 'barra_color', 'barra_grosor' ),
				),
				'entrada'  => array(
					'etiqueta' => 'Entrada escalonada',
					'ayuda'    => 'Las piezas del contenedor (cada texto, cada botón y, en una lista de precios, cada línea) suben y aparecen una detrás de otra, en orden de lectura. Se elige si la coreografía se lanza entera al llegar al bloque o si cada pieza espera a que llegues a ella.',
					'opciones' => array( 'disparo', 'velocidad' ),
				),
				'parallax' => array(
					'etiqueta' => 'Parallax',
					'ayuda'    => 'El contenido del contenedor se desplaza más despacio que la página.',
					'opciones' => array( 'velocidad' ),
				),
				'marca'    => array(
					'etiqueta' => 'La marca se planta y el disco crece',
					'ayuda'    => 'Para un logotipo en SVG con una forma grande y una marca fuera de ella (un aspa, un punto): la marca entra girando y se planta, y la forma grande crece desde su centro. Es la misma coreografía que la intro, pensada para un logotipo enorme de fondo que se sale del contenedor.',
					'opciones' => array( 'velocidad' ),
				),
				'gira'     => array(
					'etiqueta' => 'Gira hasta plantarse',
					'ayuda'    => 'El contenedor entra girando y creciendo un poco hasta quedarse quieto, la primera vez que aparece. Pensado para una marca o un icono grande de fondo.',
					'opciones' => array( 'velocidad' ),
				),
			)
		);
	}

	/** Opciones que puede pedir un efecto, con sus valores admitidos. */
	public static function opciones() {
		return array(
			'direccion' => array(
				'etiqueta' => 'Dirección',
				'valores'  => array(
					'derecha'  => 'Hacia la derecha',
					'izquierda' => 'Hacia la izquierda',
				),
				'defecto'  => 'derecha',
			),
			'contenido' => array(
				'etiqueta' => 'Contenido',
				'valores'  => array(
					'destapa' => 'Se destapa con la cortina',
					'despues' => 'Entra después, por encima de la cortina',
				),
				'defecto'  => 'destapa',
			),
			'disparo'   => array(
				'etiqueta' => 'Cuándo entra',
				'valores'  => array(
					'bloque' => 'Todo al llegar al bloque',
					'piezas' => 'Cada pieza al llegar a ella',
				),
				'defecto'  => 'bloque',
				'ayuda'    => 'Al llegar al bloque: la coreografía entera se lanza cuando la sección aparece, así que el bloque se ve completo aunque sigas bajando. Cada pieza: la entrada va atada al scroll y cada elemento espera a que llegues a él.',
			),
			'velocidad' => array(
				'etiqueta' => 'Velocidad',
				'valores'  => array(
					'lenta'  => 'Lenta',
					'media'  => 'Media',
					'rapida' => 'Rápida',
				),
				'defecto'  => 'media',
			),
			'velo'      => array(
				'etiqueta' => 'Color del velo entre la foto y el texto',
				'tipo'     => 'color',
				'variable' => '--cm-velo-color',
				'ayuda'    => 'Vacío: sin velo. Admite un color global del Kit o uno cualquiera.',
			),
			'velo_opacidad' => array(
				'etiqueta' => 'Opacidad final del velo (%)',
				'tipo'     => 'numero',
				'min'      => 0,
				'max'      => 100,
				'paso'     => 5,
				'defecto'  => 45,
			),
			'barra'     => array(
				'etiqueta' => 'Línea de progreso',
				'tipo'     => 'interruptor',
				'defecto'  => 'si',
			),
			'barra_color' => array(
				'etiqueta' => 'Color de la línea',
				'tipo'     => 'color',
				'variable' => '--cm-barra-color',
				'ayuda'    => 'Vacío: el color Énfasis del Kit.',
			),
			'barra_grosor' => array(
				'etiqueta' => 'Grosor de la línea (px)',
				'tipo'     => 'numero',
				'min'      => 1,
				'max'      => 8,
				'paso'     => 1,
				'defecto'  => 2,
			),
			'color'     => array(
				'etiqueta' => 'Color de la cortina',
				'tipo'     => 'color',
				'variable' => '--cm-cortina-color',
				'ayuda'    => 'Vacío: el color de fondo de la sección anterior. Admite un color global del Kit o uno cualquiera.',
			),
		);
	}

	// ── 1) Controles en el panel de Elementor ───────────────────────────

	public function controles_contenedor( $element, $args ) {
		if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
			return;
		}

		$efectos  = self::efectos();
		$opciones = self::opciones();

		$lista = array( '' => '— Sin efecto —' );
		foreach ( $efectos as $clave => $fx ) {
			$lista[ $clave ] = $fx['etiqueta'];
		}

		$element->start_controls_section(
			'cm_seccion',
			array(
				'label' => 'Caracool Motion',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$element->add_control(
			'cm_efecto',
			array(
				'label'       => 'Efecto de sección',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $lista,
				'default'     => '',
				'description' => 'Se aplica a este contenedor. Los efectos se desactivan solos si el visitante tiene reducido el movimiento en su sistema.',
			)
		);

		// Un control por opción, visible solo para los efectos que la usan.
		foreach ( $opciones as $clave => $op ) {
			$condicion = array();
			foreach ( $efectos as $fx_clave => $fx ) {
				if ( in_array( $clave, $fx['opciones'], true ) ) {
					$condicion[] = $fx_clave;
				}
			}
			if ( ! $condicion ) {
				continue;
			}

			if ( isset( $op['tipo'] ) && 'color' === $op['tipo'] ) {
				// Control de color nativo de Elementor: admite los globales del Kit o
				// cualquier color. Elementor escribe él mismo la variable CSS en el
				// contenedor; el plugin solo la lee.
				$element->add_control(
					'cm_' . $clave,
					array(
						'label'       => $op['etiqueta'],
						'type'        => \Elementor\Controls_Manager::COLOR,
						'global'      => array( 'active' => true ),
						'selectors'   => array( '{{WRAPPER}}' => $op['variable'] . ': {{VALUE}};' ),
						'description' => isset( $op['ayuda'] ) ? $op['ayuda'] : '',
						'condition'   => array( 'cm_efecto' => $condicion ),
					)
				);
				continue;
			}
			if ( isset( $op['tipo'] ) && 'interruptor' === $op['tipo'] ) {
				$element->add_control(
					'cm_' . $clave,
					array(
						'label'        => $op['etiqueta'],
						'type'         => \Elementor\Controls_Manager::SWITCHER,
						'label_on'     => 'Sí',
						'label_off'    => 'No',
						'return_value' => 'si',
						'default'      => $op['defecto'],
						'condition'    => array( 'cm_efecto' => $condicion ),
					)
				);
				continue;
			}
			if ( isset( $op['tipo'] ) && 'numero' === $op['tipo'] ) {
				$element->add_control(
					'cm_' . $clave,
					array(
						'label'     => $op['etiqueta'],
						'type'      => \Elementor\Controls_Manager::NUMBER,
						'min'       => $op['min'],
						'max'       => $op['max'],
						'step'      => isset( $op['paso'] ) ? $op['paso'] : 1,
						'default'   => $op['defecto'],
						'condition' => array( 'cm_efecto' => $condicion ),
					)
				);
				continue;
			}
			$element->add_control(
				'cm_' . $clave,
				array(
					'label'       => $op['etiqueta'],
					'type'        => \Elementor\Controls_Manager::SELECT,
					'options'     => $op['valores'],
					'default'     => $op['defecto'],
					'description' => isset( $op['ayuda'] ) ? $op['ayuda'] : '',
					'condition'   => array( 'cm_efecto' => $condicion ),
				)
			);
		}

		// Ayuda contextual: se ve la del efecto elegido y solo esa.
		foreach ( $efectos as $clave => $fx ) {
			if ( empty( $fx['ayuda'] ) ) {
				continue;
			}
			$element->add_control(
				'cm_ayuda_' . $clave,
				array(
					'type'            => \Elementor\Controls_Manager::RAW_HTML,
					'raw'             => esc_html( $fx['ayuda'] ),
					'content_classes' => 'elementor-descriptor',
					'condition'       => array( 'cm_efecto' => $clave ),
				)
			);
		}

		$element->end_controls_section();
	}

	// ── 2) Atributos en el HTML renderizado ─────────────────────────────

	public function inyectar_atributos( $element ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings_for_display' ) ) {
			return;
		}
		if ( ! method_exists( $element, 'get_type' ) || 'container' !== $element->get_type() ) {
			return;
		}

		$ajustes = $element->get_settings_for_display();
		$efecto  = isset( $ajustes['cm_efecto'] ) ? sanitize_key( $ajustes['cm_efecto'] ) : '';
		if ( '' === $efecto ) {
			return;
		}

		$efectos = self::efectos();
		if ( ! isset( $efectos[ $efecto ] ) ) {
			return;
		}

		$element->add_render_attribute( '_wrapper', 'data-cm-efecto', $efecto );

		$opciones = self::opciones();
		foreach ( $efectos[ $efecto ]['opciones'] as $clave ) {
			if ( ! isset( $opciones[ $clave ] ) ) {
				continue;
			}
			$op = $opciones[ $clave ];
			if ( isset( $op['tipo'] ) && 'color' === $op['tipo'] ) {
				continue; // lo escribe Elementor como variable CSS del contenedor
			}
			if ( isset( $op['tipo'] ) && 'interruptor' === $op['tipo'] ) {
				$guardado = isset( $ajustes[ 'cm_' . $clave ] ) ? (string) $ajustes[ 'cm_' . $clave ] : $op['defecto'];
				// Valores de cuando esto era un desplegable de colores: cualquier color cuenta como sí.
				$valor = ( '' === $guardado || 'no' === $guardado || 'ninguno' === $guardado ) ? 'no' : 'si';
				$element->add_render_attribute( '_wrapper', 'data-cm-' . $clave, $valor );
				continue;
			}
			if ( isset( $op['tipo'] ) && 'numero' === $op['tipo'] ) {
				$valor = ( isset( $ajustes[ 'cm_' . $clave ] ) && is_numeric( $ajustes[ 'cm_' . $clave ] ) ) ? (float) $ajustes[ 'cm_' . $clave ] : $op['defecto'];
				if ( $valor < $op['min'] || $valor > $op['max'] ) {
					$valor = $op['defecto'];
				}
				$element->add_render_attribute( '_wrapper', 'data-cm-' . $clave, (string) $valor );
				continue;
			}
			$valor = isset( $ajustes[ 'cm_' . $clave ] ) ? sanitize_key( $ajustes[ 'cm_' . $clave ] ) : '';
			if ( ! isset( $op['valores'][ $valor ] ) ) {
				$valor = $op['defecto'];
			}
			$element->add_render_attribute( '_wrapper', 'data-cm-' . $clave, $valor );
		}

		self::$necesita_assets = true;
	}

	// ── 3) Assets, solo si hacen falta ──────────────────────────────────

	public function registrar_assets() {
		$base = CARACOOL_MOTION_URL . 'assets/';
		wp_register_style( 'cm-scroll', $base . 'cm-scroll.css', array(), caracool_motion_ver( 'cm-scroll.css' ) );
		wp_register_script( 'cm-gsap', $base . 'gsap.min.js', array(), '3.12.5', true );
		wp_register_script( 'cm-scrolltrigger', $base . 'ScrollTrigger.min.js', array( 'cm-gsap' ), '3.12.5', true );
		wp_register_script( 'cm-lenis', $base . 'lenis.min.js', array(), '1.1.20', true );
		wp_register_script( 'cm-scroll', $base . 'cm-scroll.js', array( 'cm-gsap', 'cm-scrolltrigger', 'cm-lenis' ), caracool_motion_ver( 'cm-scroll.js' ), true );
	}

	/**
	 * Tapa las piezas de los bloques con entrada antes del primer pintado.
	 *
	 * El JavaScript del módulo va al final del body, así que sin esto el
	 * navegador pinta el contenido, el efecto lo esconde y luego lo hace
	 * entrar: se ve un parpadeo, sobre todo en el primer bloque de la página.
	 * Se tapa solo lo que se va a animar, no el contenedor, para que la foto
	 * de fondo del hero se vea desde el primer momento.
	 *
	 * Va sin condición de página: son 200 bytes y en el `wp_head` todavía no
	 * se ha renderizado ningún contenedor, así que aún no se sabe si esta
	 * página lleva efectos. Sin bloques con entrada, la regla no pinta nada.
	 * La quita el JS en cuanto ha colocado cada pieza; si no llegara a
	 * arrancar, se quita sola a los dos segundos.
	 */
	public function imprimir_guarda() {
		if ( self::editor_elementor() ) {
			return;
		}
		?>
<style id="cm-guarda">html.cm-entrando [data-cm-efecto="entrada"] .elementor-widget{visibility:hidden}</style>
<script id="cm-guarda-js">(function(h){if(window.matchMedia&&window.matchMedia("(prefers-reduced-motion: reduce)").matches){return;}h.className+=" cm-entrando";setTimeout(function(){h.classList.remove("cm-entrando");},2000);}(document.documentElement));</script>
		<?php
	}

	public function imprimir_assets() {
		if ( self::editor_elementor() ) {
			return;
		}

		$conf = self::get_settings();

		// El motor de inercia y los puntos son ajustes de sitio: pueden hacer
		// falta aunque esta página no tenga ningún efecto de sección.
		$hay_motor = ( 'si' === $conf['inercia'] || 'si' === $conf['puntos'] );

		if ( ! self::$necesita_assets && ! $hay_motor ) {
			return;
		}

		wp_enqueue_style( 'cm-scroll' );
		wp_enqueue_script( 'cm-scroll' );

		wp_add_inline_script(
			'cm-scroll',
			'window.CaracoolMotionConf = ' . wp_json_encode(
				array(
					'inercia'   => ( 'si' === $conf['inercia'] ),
					'duracion'  => (float) $conf['duracion'],
					'enTactil'  => ( 'si' === $conf['tactil'] ),
					'puntos'    => ( 'si' === $conf['puntos'] ),
					'puntosColor' => '--e-global-color-' . $conf['puntos_color'],
				)
			) . ';',
			'before'
		);
	}

	private static function editor_elementor() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}
		$e = \Elementor\Plugin::$instance;
		if ( isset( $e->preview ) && method_exists( $e->preview, 'is_preview_mode' ) && $e->preview->is_preview_mode() ) {
			return true;
		}
		if ( isset( $e->editor ) && method_exists( $e->editor, 'is_edit_mode' ) && $e->editor->is_edit_mode() ) {
			return true;
		}
		return false;
	}

	// ── 4) Ajustes generales del módulo ─────────────────────────────────

	public static function get_settings() {
		$guardado = get_option( self::OPTION_KEY, array() );
		return array(
			'inercia'  => isset( $guardado['inercia'] ) ? $guardado['inercia'] : 'si',
			'duracion' => isset( $guardado['duracion'] ) ? self::acotar( $guardado['duracion'], 0.6, 2.5, 1.25 ) : 1.25,
			'tactil'   => isset( $guardado['tactil'] ) ? $guardado['tactil'] : 'no',
			'puntos'   => isset( $guardado['puntos'] ) ? $guardado['puntos'] : 'no',
			'puntos_color' => ( isset( $guardado['puntos_color'] ) && '' !== sanitize_key( $guardado['puntos_color'] ) ) ? sanitize_key( $guardado['puntos_color'] ) : 'accent',
		);
	}

	/** El valor por defecto es SIEMPRE una constante, nunca lo último guardado:
	 *  así un valor corrupto cae en un sitio conocido y no en uno impredecible. */
	private static function acotar( $valor, $min, $max, $defecto ) {
		if ( ! is_numeric( $valor ) ) {
			return $defecto;
		}
		$valor = (float) $valor;
		if ( $valor < $min || $valor > $max ) {
			return $defecto;
		}
		return $valor;
	}

	public function panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c = self::get_settings();
		?>
		<section id="cm-scroll" class="cm-modulo" data-titulo="Scroll">

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16"/><path d="m7 9 5-5 5 5"/><path d="m7 15 5 5 5-5"/></svg></div>
					<h2>Motor de scroll</h2>
				</div>
				<p class="cm-card-desc">Sustituye el desplazamiento del navegador por uno con inercia. Es lo que hace que la página se sienta suave y que las transiciones no vayan a saltos.</p>

				<div class="cm-campo">
					<label for="cm_inercia">Scroll con inercia</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_scroll[inercia]" id="cm_inercia" value="si" <?php checked( 'si', $c['inercia'] ); ?>><span></span></label>
						<span class="cm-hint">Al desactivarlo, la web usa el desplazamiento normal del navegador. Los efectos de sección siguen funcionando.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_duracion">Suavidad</label>
					<div>
						<input type="number" name="cm_scroll[duracion]" id="cm_duracion" step="0.05" min="0.6" max="2.5" value="<?php echo esc_attr( $c['duracion'] ); ?>">
						<span class="cm-hint">Segundos que tarda en frenar. Entre 0,6 y 2,5. Por debajo de 1 se siente seco; por encima de 1,6, pesado. Recomendado: 1,25.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_tactil">Activar también en móvil y tableta</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_scroll[tactil]" id="cm_tactil" value="si" <?php checked( 'si', $c['tactil'] ); ?>><span></span></label>
						<span class="cm-hint">Desaconsejado: en pantallas táctiles el sistema ya aplica su propia inercia y las dos se estorban.</span>
					</div>
				</div>
			</div>

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="6" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="18" r="1.6"/></svg></div>
					<h2>Navegación por puntos</h2>
				</div>
				<p class="cm-card-desc">Columna de puntos y flechas fija a la derecha que indica en qué sección está el visitante y permite saltar de una a otra. Se oculta sola en móvil.</p>
				<div class="cm-campo">
					<label for="cm_puntos">Mostrar los puntos</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_scroll[puntos]" id="cm_puntos" value="si" <?php checked( 'si', $c['puntos'] ); ?>><span></span></label>
						<span class="cm-hint">Los nombres se leen del primer título de cada sección, así que se actualizan solos si cambian los textos.</span>
					</div>
				</div>
				<div class="cm-campo">
					<label for="cm_puntos_color">Color de los puntos</label>
					<div>
						<select name="cm_scroll[puntos_color]" id="cm_puntos_color">
							<?php
							$colores = class_exists( 'Caracool_Motion_Cabecera' ) ? Caracool_Motion_Cabecera::colores_globales() : array( 'primary' => 'Principal', 'secondary' => 'Secundario', 'text' => 'Texto', 'accent' => 'Énfasis' );
							foreach ( $colores as $id => $etiqueta ) :
								?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $c['puntos_color'] ); ?>><?php echo esc_html( $etiqueta ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="cm-hint">Un color global del Kit, para el punto activo, el borde de los demás y las flechas.</span>
					</div>
				</div>
			</div>

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h10M4 18h7"/></svg></div>
					<h2>Efectos disponibles</h2>
				</div>
				<p class="cm-card-desc">Se eligen contenedor por contenedor, en Elementor: pestaña <strong>Estilo → Caracool Motion</strong>. No hace falta escribir clases ni CSS.</p>
				<table class="cm-tabla">
					<thead><tr><th style="width:26%">Efecto</th><th>Qué hace</th></tr></thead>
					<tbody>
					<?php foreach ( self::efectos() as $clave => $fx ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $fx['etiqueta'] ); ?></strong><br><code><?php echo esc_html( $clave ); ?></code></td>
							<td><?php echo esc_html( isset( $fx['ayuda'] ) ? $fx['ayuda'] : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

		</section>
		<?php
	}

	public function guardar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'caracool_motion_save' );

		$post = ( isset( $_POST['cm_scroll'] ) && is_array( $_POST['cm_scroll'] ) ) ? wp_unslash( $_POST['cm_scroll'] ) : array();

		update_option(
			self::OPTION_KEY,
			array(
				'inercia'  => ( isset( $post['inercia'] ) && 'si' === $post['inercia'] ) ? 'si' : 'no',
				'duracion' => self::acotar( isset( $post['duracion'] ) ? $post['duracion'] : null, 0.6, 2.5, 1.25 ),
				'tactil'   => ( isset( $post['tactil'] ) && 'si' === $post['tactil'] ) ? 'si' : 'no',
				'puntos'   => ( isset( $post['puntos'] ) && 'si' === $post['puntos'] ) ? 'si' : 'no',
				'puntos_color' => ( isset( $post['puntos_color'] ) && '' !== sanitize_key( $post['puntos_color'] ) ) ? sanitize_key( $post['puntos_color'] ) : 'accent',
			)
		);
	}
}

new Caracool_Motion_Scroll();
