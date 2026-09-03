<?php
/**
 * Caracool Motion — Módulo Intro
 * ─────────────────────────────────────────────────────────────────────
 * Archivo aparte y autorregistrado, como el resto de módulos.
 *
 * QUÉ HACE
 *  Una intro de entrada: una sábana del color de la casa cubre la página,
 *  el logotipo se anima en el centro y la sábana sube dejando ver el resto.
 *  Solo la primera vez que alguien entra (se recuerda unos días).
 *
 *  1. Ajustes en el panel: interruptor, si va en la página de inicio, en
 *     qué otras páginas concretas, logotipo de la biblioteca y su tamaño,
 *     color de la sábana entre los globales del Kit, animación del logotipo,
 *     salida, tempo y días.
 *  2. Imprime la sábana y el logotipo CON EL HTML, en wp_body_open, con su
 *     CSS crítico en wp_head. Si se pintara desde JavaScript, el hero se
 *     vería un instante antes de la intro. Un script en línea justo debajo
 *     la oculta antes del primer pintado si el visitante ya la vio.
 *
 * CÓMO SABE QUÉ ES CADA PIEZA DEL LOGOTIPO
 *  No lo sabe: lo deduce. La forma con más área es el «disco»; las formas
 *  cuyo centro cae fuera de ese disco son la «marca» (el aspa); el resto son
 *  las letras. Vale para cualquier logotipo con una forma grande y detalles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Caracool_Motion_Intro {

	const OPTION_KEY = 'caracool_motion_intro';

	/** true cuando esta petición imprime la intro (para cargar el JS solo entonces). */
	private static $impresa = false;

	public function __construct() {
		add_action( 'wp_head', array( $this, 'css_critico' ), 5 );
		add_action( 'wp_body_open', array( $this, 'imprimir_sabana' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_assets' ) );
		add_action( 'wp_footer', array( $this, 'imprimir_assets' ), 6 );

		add_action( 'caracool_motion_settings_panels', array( $this, 'panel' ) );
		add_action( 'admin_post_caracool_motion_save', array( $this, 'guardar' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}

	// ── Catálogo de animaciones del logotipo (ampliable por filtro) ─────

	public static function animaciones() {
		return apply_filters(
			'caracool_motion_animaciones_intro',
			array(
				'freno'   => array( 'etiqueta' => 'Del aspa al disco · freno largo', 'ayuda' => 'La marca se planta en el centro; el disco crece de golpe y se pasa el resto del tiempo frenando. La elegida en Por Herencia.' ),
				'asiento' => array( 'etiqueta' => 'Del aspa al disco · con asiento', 'ayuda' => 'Igual, pero el disco se pasa un 3 % y vuelve, como si pesara.' ),
				'respira' => array( 'etiqueta' => 'Del aspa al disco · respira', 'ayuda' => 'El disco nace estirándose un poco y al final hace una respiración lenta.' ),
				'gota'    => array( 'etiqueta' => 'Se derrama desde la marca', 'ayuda' => 'La marca aparece en su sitio y el disco sale de ella hacia el centro.' ),
				'sello'   => array( 'etiqueta' => 'Sello', 'ayuda' => 'Todo el logotipo entra de una vez y se asienta. La más corta.' ),
			)
		);
	}

	public static function salidas() {
		return array(
			'sabana'  => 'Sube como una sábana',
			'recorte' => 'Se recorta de abajo arriba',
		);
	}

	// ── Ajustes ─────────────────────────────────────────────────────────

	public static function get_settings() {
		$g   = get_option( self::OPTION_KEY, array() );
		$ani = isset( $g['animacion'] ) ? sanitize_key( $g['animacion'] ) : 'freno';
		if ( ! isset( self::animaciones()[ $ani ] ) ) {
			$ani = 'freno';
		}
		$sal = isset( $g['salida'] ) ? sanitize_key( $g['salida'] ) : 'sabana';
		if ( ! isset( self::salidas()[ $sal ] ) ) {
			$sal = 'sabana';
		}
		$fondo = isset( $g['fondo'] ) ? sanitize_key( $g['fondo'] ) : 'primary';
		if ( '' === $fondo ) {
			$fondo = 'primary';
		}
		$paginas = array();
		if ( isset( $g['paginas'] ) && is_array( $g['paginas'] ) ) {
			foreach ( $g['paginas'] as $id ) {
				$id = absint( $id );
				if ( $id ) {
					$paginas[] = $id;
				}
			}
		}
		return array(
			'activo'    => ( isset( $g['activo'] ) && 'si' === $g['activo'] ) ? 'si' : 'no',
			'inicio'    => ( isset( $g['inicio'] ) && 'no' === $g['inicio'] ) ? 'no' : 'si',
			'paginas'   => array_values( array_unique( $paginas ) ),
			'logo'      => isset( $g['logo'] ) ? absint( $g['logo'] ) : 0,
			'tamano'    => isset( $g['tamano'] ) ? self::acotar( $g['tamano'], 80, 600, 220 ) : 220,
			'tamano_movil' => isset( $g['tamano_movil'] ) ? self::acotar( $g['tamano_movil'], 60, 400, 150 ) : 150,
			'fondo'     => $fondo,
			'animacion' => $ani,
			'salida'    => $sal,
			'tempo'     => isset( $g['tempo'] ) ? self::acotar( $g['tempo'], 60, 160, 100 ) : 100,
			'dias'      => isset( $g['dias'] ) ? self::acotar( $g['dias'], 0, 90, 7 ) : 7,
		);
	}

	/** El valor por defecto es SIEMPRE una constante, nunca lo último guardado. */
	private static function acotar( $valor, $min, $max, $defecto ) {
		if ( ! is_numeric( $valor ) ) {
			return $defecto;
		}
		$valor = (int) $valor;
		if ( $valor < $min || $valor > $max ) {
			return $defecto;
		}
		return $valor;
	}

	/** [ id => etiqueta ] con los colores de sistema y los personalizados del Kit activo. */
	public static function colores_globales() {
		if ( class_exists( 'Caracool_Motion_Cabecera' ) ) {
			return Caracool_Motion_Cabecera::colores_globales();
		}
		return array( 'primary' => 'Principal', 'secondary' => 'Secundario', 'text' => 'Texto', 'accent' => 'Énfasis' );
	}

	/** Valor hex de un color global del Kit, como respaldo por si el CSS del Kit aún no ha cargado. */
	private static function hex_global( $id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}
		$kits = \Elementor\Plugin::$instance->kits_manager;
		$kit  = ( $kits && method_exists( $kits, 'get_active_kit' ) ) ? $kits->get_active_kit() : null;
		if ( ! $kit || ! method_exists( $kit, 'get_settings' ) ) {
			return '';
		}
		foreach ( array( 'system_colors', 'custom_colors' ) as $grupo ) {
			$colores = $kit->get_settings( $grupo );
			if ( ! is_array( $colores ) ) {
				continue;
			}
			foreach ( $colores as $c ) {
				if ( isset( $c['_id'], $c['color'] ) && sanitize_key( $c['_id'] ) === $id ) {
					return sanitize_hex_color( $c['color'] ) ? sanitize_hex_color( $c['color'] ) : '';
				}
			}
		}
		return '';
	}

	/** ¿Toca imprimir la intro en esta petición? */
	public static function toca() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
			return false;
		}
		if ( self::editor_elementor() ) {
			return false;
		}
		$c = self::get_settings();
		if ( 'si' !== $c['activo'] || ! $c['logo'] ) {
			return false;
		}
		if ( is_front_page() ) {
			return 'si' === $c['inicio'];
		}
		if ( is_singular() && $c['paginas'] ) {
			return in_array( (int) get_queried_object_id(), $c['paginas'], true );
		}
		return false;
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

	// ── Logotipo: SVG en línea, saneado ─────────────────────────────────

	private static function svg_logo( $id ) {
		$ruta = get_attached_file( $id );
		if ( ! $ruta || ! file_exists( $ruta ) || 'image/svg+xml' !== get_post_mime_type( $id ) ) {
			return '';
		}
		$svg = file_get_contents( $ruta ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $svg || false === stripos( $svg, '<svg' ) ) {
			return '';
		}
		// Fuera todo lo que no sea dibujo: scripts, manejadores, enlaces externos, XML previo.
		$svg = preg_replace( '/<\?xml[^>]*\?>/i', '', $svg );
		$svg = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $svg );
		$svg = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $svg );
		$svg = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg );
		$svg = preg_replace( '/\s(xlink:)?href\s*=\s*("(?!#)[^"]*"|\'(?!#)[^\']*\')/i', '', $svg );
		$svg = preg_replace( '/<(foreignObject|iframe|object|embed)\b.*?<\/\1>/is', '', $svg );
		// Sin ids fijos ni clip-paths de Illustrator, que chocan con los del resto de la página.
		$svg = preg_replace( '/\sid\s*=\s*"[^"]*"/i', '', $svg );
		$svg = preg_replace( '/\sclip-path\s*=\s*"[^"]*"/i', '', $svg );
		$svg = preg_replace( '/<defs\b.*?<\/defs>/is', '', $svg );
		$svg = preg_replace( '/\s(width|height)\s*=\s*"[^"]*"(?=[^>]*>)/i', '', $svg, 2 );
		return trim( $svg );
	}

	// ── Impresión ───────────────────────────────────────────────────────

	public function css_critico() {
		if ( ! self::toca() ) {
			return;
		}
		$c   = self::get_settings();
		$hex = self::hex_global( $c['fondo'] );
		$var = 'var(--e-global-color-' . esc_attr( $c['fondo'] ) . ( $hex ? ', ' . $hex : '' ) . ')';
		echo '<style id="cm-intro-critico">'
			// El scroll se bloquea mientras dura la intro, pero el hueco de la barra
			// se reserva: si no, al terminar aparece la barra y la página salta.
			. 'html.cm-intro-activa{overflow:hidden;scrollbar-gutter:stable}'
			. '.cm-intro{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;'
			. ( $hex ? 'background:' . esc_attr( $hex ) . ';' : '' ) . 'background:' . $var . ';will-change:transform}'
			. '.cm-intro svg{width:' . (int) $c['tamano'] . 'px;max-width:82vw;height:auto;overflow:visible;opacity:0}'
			. '@media(max-width:767px){.cm-intro svg{width:' . (int) $c['tamano_movil'] . 'px}}'
			. '.cm-intro[hidden]{display:none}'
			. '</style>' . "\n";
	}

	public function imprimir_sabana() {
		if ( ! self::toca() ) {
			return;
		}
		$c   = self::get_settings();
		$svg = self::svg_logo( $c['logo'] );
		if ( '' === $svg ) {
			return;
		}
		self::$impresa = true;
		?>
		<div class="cm-intro" id="cm-intro" aria-hidden="true"><?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<script>
		(function(){
			var d=document.documentElement,o=document.getElementById('cm-intro'),q=location.search;
			var fuerza=/[?&]intro=1/.test(q),salta=/[?&]intro=0/.test(q);
			var dias=<?php echo (int) $c['dias']; ?>;
			var vista=false;try{vista=dias>0&&Number(localStorage.getItem('cmIntroHasta')||0)>Date.now();}catch(e){}
			var reducido=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			if(salta||reducido||(vista&&!fuerza)){o.setAttribute('hidden','');o.parentNode.removeChild(o);}
			else{d.classList.add('cm-intro-activa');}
		})();
		</script>
		<?php
	}

	public function registrar_assets() {
		$base = CARACOOL_MOTION_URL . 'assets/';
		wp_register_script( 'cm-intro', $base . 'cm-intro.js', array( 'cm-gsap' ), caracool_motion_ver( 'cm-intro.js' ), true );
	}

	public function imprimir_assets() {
		if ( ! self::$impresa ) {
			return;
		}
		$c = self::get_settings();
		wp_enqueue_script( 'cm-gsap' );
		wp_enqueue_script( 'cm-intro' );
		wp_add_inline_script(
			'cm-intro',
			'window.CaracoolMotionIntro = ' . wp_json_encode(
				array(
					'animacion' => $c['animacion'],
					'salida'    => $c['salida'],
					'tempo'     => (int) $c['tempo'],
					'dias'      => (int) $c['dias'],
				)
			) . ';',
			'before'
		);
	}

	// ── Panel ───────────────────────────────────────────────────────────

	public function admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'caracool-motion' ) ) {
			return;
		}
		wp_enqueue_media();
	}

	public function panel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$c        = self::get_settings();
		$colores  = self::colores_globales();
		$logo_url = $c['logo'] ? wp_get_attachment_url( $c['logo'] ) : '';
		?>
		<section id="cm-intro" class="cm-modulo" data-titulo="Intro">

			<div class="cm-card">
				<div class="cm-card-head">
					<div class="cm-card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="6.5"/><path d="M5 3l3 3M19 3l-3 3"/></svg></div>
					<h2>Intro de entrada</h2>
				</div>
				<p class="cm-card-desc">Una sábana del color de la casa cubre la página, el logotipo se anima en el centro y la sábana sube dejando ver el resto. <strong>Solo la primera vez</strong> que alguien entra. Para verla otra vez sin esperar, añade <code>?intro=1</code> a la dirección; con <code>?intro=0</code> se salta.</p>

				<div class="cm-campo">
					<label for="cm_intro_activo">Intro activa</label>
					<div>
						<label class="cm-sw"><input type="checkbox" name="cm_intro[activo]" id="cm_intro_activo" value="si" <?php checked( 'si', $c['activo'] ); ?>><span></span></label>
						<span class="cm-hint">Sin logotipo elegido no se muestra aunque esté activa. No se muestra en el editor de Elementor ni a quien tenga el movimiento reducido en su sistema.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_intro_inicio">En la página de inicio</label>
					<div>
						<input type="hidden" name="cm_intro[inicio]" value="no">
						<label class="cm-sw"><input type="checkbox" name="cm_intro[inicio]" id="cm_intro_inicio" value="si" <?php checked( 'si', $c['inicio'] ); ?>><span></span></label>
						<span class="cm-hint">La portada del sitio.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label>En otras páginas</label>
					<div>
						<?php
						$todas   = get_pages( array( 'sort_column' => 'post_title', 'post_status' => 'publish' ) );
						$portada = (int) get_option( 'page_on_front' );
						?>
						<?php
						$otras = array();
						foreach ( (array) $todas as $pg ) {
							if ( (int) $pg->ID !== $portada ) {
								$otras[] = $pg;
							}
						}
						?>
						<div class="cm-lista">
							<?php if ( ! $otras ) : ?>
								<span class="cm-hint" style="margin:0">Todavía no hay más páginas publicadas. Cuando existan Carta, Historia o Tarjeta regalo, aparecerán aquí.</span>
							<?php endif; ?>
							<?php foreach ( $otras as $pg ) : ?>
								<label class="cm-check"><input type="checkbox" name="cm_intro[paginas][]" value="<?php echo (int) $pg->ID; ?>" <?php checked( in_array( (int) $pg->ID, $c['paginas'], true ) ); ?>> <?php echo esc_html( $pg->post_title ? $pg->post_title : '(sin título)' ); ?></label>
							<?php endforeach; ?>
						</div>
						<span class="cm-hint">Marca las páginas concretas donde también quieres la intro. Las demás no la llevan.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label>Logotipo</label>
					<div>
						<input type="hidden" name="cm_intro[logo]" id="cm_intro_logo" value="<?php echo esc_attr( $c['logo'] ); ?>">
						<div id="cm_intro_logo_vista" style="display:flex;align-items:center;gap:14px;margin-bottom:8px;<?php echo $logo_url ? '' : 'display:none;'; ?>">
							<span style="display:inline-grid;place-items:center;width:72px;height:72px;border-radius:10px;background:#1c1b19"><img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width:52px;max-height:52px"></span>
						</div>
						<button type="button" class="button" id="cm_intro_elegir">Elegir de la biblioteca</button>
						<button type="button" class="button" id="cm_intro_quitar" <?php echo $logo_url ? '' : 'style="display:none"'; ?>>Quitar</button>
						<span class="cm-hint">Tiene que ser un <strong>SVG</strong>: se incrusta en la página para poder animar cada trazo. La forma más grande se toma como disco; las que quedan fuera de él, como marca; el resto, como letras.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_intro_tamano">Tamaño del logotipo</label>
					<div>
						<input type="number" name="cm_intro[tamano]" id="cm_intro_tamano" step="10" min="80" max="600" value="<?php echo esc_attr( $c['tamano'] ); ?>"> px en escritorio
						&nbsp;&nbsp;
						<input type="number" name="cm_intro[tamano_movil]" id="cm_intro_tamano_movil" step="10" min="60" max="400" value="<?php echo esc_attr( $c['tamano_movil'] ); ?>"> px en móvil
						<span class="cm-hint">Ancho del logotipo en el centro de la sábana. Nunca pasa del 82 % del ancho de la pantalla.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_intro_fondo">Color de la sábana</label>
					<div>
						<select name="cm_intro[fondo]" id="cm_intro_fondo">
							<?php foreach ( $colores as $id => $etiqueta ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $c['fondo'] ); ?>><?php echo esc_html( $etiqueta ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="cm-hint">Un color global del Kit de Elementor. Si cambia en el Kit, cambia aquí.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_intro_animacion">Animación del logotipo</label>
					<div>
						<select name="cm_intro[animacion]" id="cm_intro_animacion">
							<?php foreach ( self::animaciones() as $id => $a ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $c['animacion'] ); ?>><?php echo esc_html( $a['etiqueta'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php $ayudas = self::animaciones(); ?>
						<span class="cm-hint"><?php echo esc_html( isset( $ayudas[ $c['animacion'] ]['ayuda'] ) ? $ayudas[ $c['animacion'] ]['ayuda'] : '' ); ?></span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_intro_salida">Salida</label>
					<div>
						<select name="cm_intro[salida]" id="cm_intro_salida">
							<?php foreach ( self::salidas() as $id => $etiqueta ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $id, $c['salida'] ); ?>><?php echo esc_html( $etiqueta ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_intro_tempo">Tempo</label>
					<div>
						<input type="number" name="cm_intro[tempo]" id="cm_intro_tempo" step="5" min="60" max="160" value="<?php echo esc_attr( $c['tempo'] ); ?>"> %
						<span class="cm-hint">Multiplica la duración de toda la intro. Al 100 % dura unos 3,5 segundos. Entre 60 y 160.</span>
					</div>
				</div>

				<div class="cm-campo">
					<label for="cm_intro_dias">Recordar durante</label>
					<div>
						<input type="number" name="cm_intro[dias]" id="cm_intro_dias" step="1" min="0" max="90" value="<?php echo esc_attr( $c['dias'] ); ?>"> días
						<span class="cm-hint">Cuántos días pasan hasta que la misma persona vuelve a ver la intro. Con 0 se ve siempre: útil mientras se ajusta, no para producción.</span>
					</div>
				</div>
			</div>

		</section>
		<script>
		(function(){
			var elegir=document.getElementById('cm_intro_elegir'),quitar=document.getElementById('cm_intro_quitar'),
				campo=document.getElementById('cm_intro_logo'),vista=document.getElementById('cm_intro_logo_vista');
			if(!elegir||!window.wp||!wp.media){return;}
			var marco=null;
			elegir.addEventListener('click',function(e){
				e.preventDefault();
				if(!marco){
					marco=wp.media({title:'Elegir el logotipo (SVG)',button:{text:'Usar este logotipo'},library:{type:'image/svg+xml'},multiple:false});
					marco.on('select',function(){
						var a=marco.state().get('selection').first().toJSON();
						campo.value=a.id;vista.style.display='flex';vista.querySelector('img').src=a.url;quitar.style.display='';
					});
				}
				marco.open();
			});
			quitar.addEventListener('click',function(){campo.value='';vista.style.display='none';quitar.style.display='none';});
		})();
		</script>
		<?php
	}

	public function guardar() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'caracool_motion_save' );

		$post = ( isset( $_POST['cm_intro'] ) && is_array( $_POST['cm_intro'] ) ) ? wp_unslash( $_POST['cm_intro'] ) : array();

		$ani = isset( $post['animacion'] ) ? sanitize_key( $post['animacion'] ) : 'freno';
		if ( ! isset( self::animaciones()[ $ani ] ) ) {
			$ani = 'freno';
		}
		$sal = isset( $post['salida'] ) ? sanitize_key( $post['salida'] ) : 'sabana';
		if ( ! isset( self::salidas()[ $sal ] ) ) {
			$sal = 'sabana';
		}
		$colores = self::colores_globales();
		$fondo   = isset( $post['fondo'] ) ? sanitize_key( $post['fondo'] ) : 'primary';
		if ( ! isset( $colores[ $fondo ] ) ) {
			$fondo = 'primary';
		}
		$paginas = array();
		if ( isset( $post['paginas'] ) && is_array( $post['paginas'] ) ) {
			foreach ( $post['paginas'] as $id ) {
				$id = absint( $id );
				if ( $id ) {
					$paginas[] = $id;
				}
			}
		}
		$paginas = array_values( array_unique( $paginas ) );

		update_option(
			self::OPTION_KEY,
			array(
				'activo'    => ( isset( $post['activo'] ) && 'si' === $post['activo'] ) ? 'si' : 'no',
				'inicio'    => ( isset( $post['inicio'] ) && 'si' === $post['inicio'] ) ? 'si' : 'no',
				'paginas'   => $paginas,
				'logo'      => isset( $post['logo'] ) ? absint( $post['logo'] ) : 0,
				'tamano'    => self::acotar( isset( $post['tamano'] ) ? $post['tamano'] : null, 80, 600, 220 ),
				'tamano_movil' => self::acotar( isset( $post['tamano_movil'] ) ? $post['tamano_movil'] : null, 60, 400, 150 ),
				'fondo'     => $fondo,
				'animacion' => $ani,
				'salida'    => $sal,
				'tempo'     => self::acotar( isset( $post['tempo'] ) ? $post['tempo'] : null, 60, 160, 100 ),
				'dias'      => self::acotar( isset( $post['dias'] ) ? $post['dias'] : null, 0, 90, 7 ),
			)
		);
	}
}

new Caracool_Motion_Intro();
