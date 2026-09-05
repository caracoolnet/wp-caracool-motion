<?php
/**
 * Plugin Name:  Caracool Motion
 * Plugin URI:   https://github.com/caracoolnet/wp-caracool-motion
 * Description:  Movimiento para webs hechas con Elementor: scroll con inercia y transiciones de sección que se eligen desde el propio panel de Elementor, sin escribir código ni CSS suelto.
 * Version:      0.5.0
 * Author:       Caracool
 * Author URI:   https://caracool.net
 * License:      GPL-2.0-or-later
 * Text Domain:  caracool-motion
 *
 * ARQUITECTURA
 * ─────────────────────────────────────────────────────────────────────
 * Este archivo es deliberadamente delgado. No sabe nada de efectos: solo
 * pinta la página de ajustes y carga los módulos que existan en /modules.
 *
 *   caracool_motion_settings_panels  → cada módulo cuelga aquí sus tarjetas
 *
 * Todo va en una sola página, sin pestañas: con pocos módulos cabe de sobra
 * y se lee de un vistazo. Si algún día se hace larga, se reintroducen.
 *
 * Cada módulo guarda sus propias opciones enganchando la MISMA acción
 * admin_post_caracool_motion_save con prioridad 5, para que le dé tiempo
 * antes de que este archivo haga el redirect final (prioridad 10).
 *
 * AMPLIAR CON UN EFECTO NUEVO
 * ─────────────────────────────────────────────────────────────────────
 * No hace falta tocar ningún archivo existente:
 *   1. En PHP, engancha el filtro `caracool_motion_efectos_seccion` y añade
 *      una entrada al array (clave técnica => etiqueta + opciones que usa).
 *      Aparece solo en el desplegable de Elementor.
 *   2. En JavaScript, registra su comportamiento:
 *      CaracoolMotion.registrar('mi-efecto', function(el, op, ctx){ ... });
 * El núcleo recorre los elementos con data-cm-efecto y llama al que toque.
 * Un efecto que no esté registrado en JS simplemente no hace nada: no rompe
 * la página ni los demás efectos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CARACOOL_MOTION_VERSION', '0.5.0' );
define( 'CARACOOL_MOTION_FILE', __FILE__ );
define( 'CARACOOL_MOTION_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARACOOL_MOTION_URL', plugin_dir_url( __FILE__ ) );
define( 'CARACOOL_MOTION_REPO', 'caracoolnet/wp-caracool-motion' );

/**
 * Versión de un recurso del plugin para la URL: la del plugin más la fecha
 * del archivo. Así, cada build nuevo (aunque no suba de número) invalida la
 * caché del navegador y de los plugins de caché.
 */
function caracool_motion_ver( $archivo ) {
	$ruta = CARACOOL_MOTION_DIR . 'assets/' . $archivo;
	$fecha = file_exists( $ruta ) ? filemtime( $ruta ) : 0;
	return CARACOOL_MOTION_VERSION . ( $fecha ? '.' . $fecha : '' );
}

class Caracool_Motion {

	const OPTION_KEY = 'caracool_motion_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_caracool_motion_save', array( $this, 'save_settings' ), 10 );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'update_details' ), 20, 3 );
	}

	/** Ajustes generales del plugin (los de cada módulo viven en su archivo). */
	public static function get_settings() {
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), array() );
	}

	// ── Menú y página de ajustes ────────────────────────────────────────

	public function add_menu() {
		add_menu_page(
			'Caracool Motion',
			'Caracool Motion',
			'manage_options',
			'caracool-motion',
			array( $this, 'render_settings_page' ),
			'dashicons-image-filter',
			59
		);
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sin permisos.' );
		}
		check_admin_referer( 'caracool_motion_save' );

		// Este archivo no guarda nada propio todavía: los módulos ya han
		// guardado lo suyo en la prioridad 5. Se conserva el punto de
		// guardado para cuando haya ajustes generales.
		wp_safe_redirect( admin_url( 'admin.php?page=caracool-motion&guardado=1' ) );
		exit;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$guardado = isset( $_GET['guardado'] );
		?>
		<div class="cm-wrap">
			<style><?php echo self::estilos_admin(); // phpcs:ignore WordPress.Security.EscapeOutput ?></style>

			<header class="cm-head">
				<div class="cm-brand">
					<?php echo self::logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div class="cm-brand-txt">
						<h1>Motion <span class="cm-badge cm-badge-ver">v<?php echo esc_html( CARACOOL_MOTION_VERSION ); ?></span></h1>
						<p>Movimiento para webs hechas con Elementor</p>
					</div>
				</div>
				<span class="cm-chip"><i></i>Activo</span>
			</header>

			<?php if ( $guardado ) : ?>
				<div class="cm-aviso">Configuración guardada.</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="caracool_motion_save">
				<?php wp_nonce_field( 'caracool_motion_save' ); ?>

				<nav class="cm-tabs" id="cm-tabs" aria-label="Módulos"></nav>

				<?php do_action( 'caracool_motion_settings_panels' ); ?>

				<p class="cm-acciones">
					<button type="submit" class="cm-btn cm-btn-primario">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m20 6-11 11-5-5"/></svg>
						Guardar configuración
					</button>
				</p>
			</form>

			<footer class="cm-pie">Hecho con <span class="cm-corazon">&#10084;</span> por Caracool</footer>
		</div>
		<script>
		(function(){
			// Una pestaña por módulo. Cada módulo pinta su <section class="cm-modulo" data-titulo="…">;
			// aquí solo se construye la barra. El formulario sigue siendo uno: guardar guarda todo.
			var nav=document.getElementById('cm-tabs'),secs=Array.prototype.slice.call(document.querySelectorAll('.cm-modulo'));
			if(!nav||secs.length<2){return;}
			var clave='cmMotionTab',activa=null;
			try{activa=localStorage.getItem(clave);}catch(e){}
			if(location.hash&&document.getElementById(location.hash.slice(1))){activa=location.hash.slice(1);}
			if(!activa||!document.getElementById(activa)){activa=secs[0].id;}
			function ir(id){
				secs.forEach(function(s){s.hidden=(s.id!==id);});
				Array.prototype.forEach.call(nav.children,function(b){b.classList.toggle('activa',b.dataset.id===id);b.setAttribute('aria-selected',b.dataset.id===id?'true':'false');});
				try{localStorage.setItem(clave,id);}catch(e){}
			}
			secs.forEach(function(s){
				var b=document.createElement('button');b.type='button';b.className='cm-tab';b.dataset.id=s.id;b.setAttribute('role','tab');
				b.textContent=s.dataset.titulo||s.id.replace(/^cm-/,'');
				b.addEventListener('click',function(){ir(s.id);history.replaceState(null,'','#'+s.id);});
				nav.appendChild(b);
			});
			ir(activa);
		})();
		</script>
		<?php
	}

	// ── Sistema visual compartido de los plugins de Caracool ────────────

	private static function estilos_admin() {
		// Mismos valores que el resto de plugins de Caracool: el negro manda en
		// lo interactivo (pestaña activa, interruptores, botón) y la terracota
		// se reserva para los iconos de las tarjetas.
		return '
		.cm-wrap{--cm-acento:#c1502e;--cm-acento-ink:#7a3319;--cm-acento-suave:#f4e3dc;
			--cm-ok:#2f7a4f;--cm-ok-suave:#e3f1e8;
			--cm-tinta:#1c1b19;--cm-tinta-suave:#6b6660;--cm-tinta-tenue:#a29c93;
			--cm-panel:#ffffff;--cm-fondo:#f6f5f2;--cm-linea:#e7e4de;
			--cm-r-grande:16px;--cm-r-medio:10px;--cm-r-chico:7px;
			--cm-sombra:0 1px 2px rgba(28,27,25,.04), 0 8px 24px -12px rgba(28,27,25,.12);
			max-width:1040px;margin:20px 20px 40px 0;color:var(--cm-tinta);
			font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}
		.cm-wrap *{box-sizing:border-box;}
		.cm-head{display:flex;align-items:center;justify-content:space-between;gap:16px;
			padding:6px 4px 18px;margin-bottom:6px;}
		.cm-brand{display:flex;align-items:center;gap:16px;}
		.cm-logo{height:30px;width:auto;display:block;flex:none;}
		.cm-brand-txt{border-left:1px solid var(--cm-linea);padding-left:16px;}
		.cm-brand h1{margin:0;font-size:19px;line-height:1.2;display:flex;align-items:center;gap:10px;}
		.cm-brand p{margin:3px 0 0;font-size:12.5px;color:var(--cm-tinta-suave);}
		.cm-badge{display:inline-block;font-size:11px;font-weight:600;letter-spacing:.03em;
			padding:3px 9px;border-radius:var(--cm-r-chico);}
		.cm-badge-ver{background:var(--cm-fondo);color:var(--cm-tinta-suave);}
		.cm-chip{display:inline-flex;align-items:center;gap:7px;background:var(--cm-ok-suave);
			color:var(--cm-ok);font-size:12px;font-weight:600;padding:6px 13px;border-radius:999px;}
		.cm-chip i{width:6px;height:6px;border-radius:50%;background:var(--cm-ok);display:block;}
		.cm-aviso{background:var(--cm-ok-suave);color:var(--cm-ok);border-radius:var(--cm-r-medio);
			padding:12px 16px;margin-bottom:16px;font-size:13.5px;}
		.cm-modulo{display:block;}
		.cm-modulo[hidden]{display:none;}
		.cm-tabs{display:flex;flex-wrap:wrap;gap:4px;margin:0 0 18px;border-bottom:1px solid var(--cm-linea);}
		.cm-tab{background:none;border:0;border-bottom:2px solid transparent;margin-bottom:-1px;
			padding:10px 14px;font-size:13.5px;font-weight:600;color:var(--cm-tinta-suave);cursor:pointer;}
		.cm-tab:hover{color:var(--cm-tinta);}
		.cm-tab.activa{color:var(--cm-tinta);border-color:var(--cm-tinta);}
		.cm-tab:focus-visible{outline:2px solid var(--cm-tinta);outline-offset:2px;border-radius:4px;}
		.cm-lista{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px 16px;
			max-height:220px;overflow:auto;padding:10px 12px;border:1px solid var(--cm-linea);
			border-radius:var(--cm-r-medio);background:#fff;max-width:640px;}
		.cm-check{display:flex;align-items:center;gap:8px;font-size:13.5px;}
		.cm-check input{margin:0;accent-color:var(--cm-tinta);}
		.cm-card{background:var(--cm-panel);border-radius:var(--cm-r-grande);box-shadow:var(--cm-sombra);
			padding:22px 24px;margin-bottom:16px;}
		.cm-card-head{display:flex;align-items:center;gap:12px;margin-bottom:6px;}
		.cm-card-icon{width:34px;height:34px;border-radius:50%;background:var(--cm-acento-suave);
			color:var(--cm-acento);display:flex;align-items:center;justify-content:center;flex:none;}
		.cm-card-icon svg{width:18px;height:18px;}
		.cm-card h2{margin:0;font-size:16px;}
		.cm-card-desc{margin:0 0 18px;font-size:13.5px;color:var(--cm-tinta-suave);max-width:70ch;}
		.cm-campo{display:grid;grid-template-columns:220px 1fr;gap:14px;align-items:start;
			padding:14px 0;border-top:1px solid var(--cm-linea);}
		.cm-campo:first-of-type{border-top:0;}
		.cm-campo > label{font-size:13.5px;font-weight:600;padding-top:6px;}
		.cm-hint{display:block;margin-top:6px;font-size:12.5px;color:var(--cm-tinta-tenue);max-width:60ch;}
		.cm-wrap select,.cm-wrap input[type=number],.cm-wrap input[type=text]{
			border:1px solid var(--cm-linea);border-radius:var(--cm-r-medio);padding:7px 10px;
			font-size:13.5px;min-width:220px;background:#fff;color:var(--cm-tinta);}
		.cm-sw{position:relative;display:inline-block;width:44px;height:25px;flex:none;}
		.cm-sw input{opacity:0;width:0;height:0;}
		.cm-sw span{position:absolute;inset:0;background:#cfd3d8;border-radius:999px;
			transition:background .2s;cursor:pointer;}
		.cm-sw span:before{content:"";position:absolute;width:19px;height:19px;left:3px;top:3px;
			background:#fff;border-radius:50%;transition:transform .2s;}
		.cm-sw input:checked + span{background:var(--cm-tinta);}
		.cm-sw input:checked + span:before{transform:translateX(19px);}
		.cm-sw input:focus-visible + span{outline:2px solid var(--cm-tinta);outline-offset:2px;}
		.cm-acciones{margin:22px 0 0;}
		.cm-btn{display:inline-flex;align-items:center;gap:9px;border:0;border-radius:999px;
			padding:12px 26px;font-size:13.5px;font-weight:600;cursor:pointer;}
		.cm-btn svg{width:15px;height:15px;}
		.cm-btn-primario{background:var(--cm-tinta);color:#fff;}
		.cm-btn-primario:hover{background:#000;}
		.cm-pie{margin-top:26px;text-align:center;font-size:12px;color:var(--cm-tinta-tenue);}
		.cm-corazon{color:var(--cm-acento);}
		.cm-tabla{width:100%;border-collapse:collapse;font-size:13.5px;margin-top:8px;}
		.cm-tabla th{text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;
			color:var(--cm-tinta-tenue);border-bottom:1px solid var(--cm-linea);padding:0 12px 8px 0;}
		.cm-tabla td{padding:10px 12px 10px 0;border-bottom:1px solid var(--cm-linea);vertical-align:top;
			color:var(--cm-tinta-suave);}
		.cm-tabla td code{background:var(--cm-fondo);padding:2px 6px;border-radius:4px;color:var(--cm-tinta);}
		@media (max-width:782px){.cm-campo{grid-template-columns:1fr;}}
		';
	}

	/** Logotipo de Caracool incrustado como markup, sin archivo de imagen
	 *  aparte — mismo criterio que el resto de plugins de la agencia. */
	private static function logo_svg() {
		$p = array(
			'M39.23,9.44c0,4.65-.8,7.71-2.13,9.98-1.6-1.2-3.99-2.26-6.92-2.26-7.58,0-14.5,6.92-14.5,35.91,0,23.14,2.93,30.06,10.37,30.06,4.12,0,7.85-.67,10.37-2,1.06,2.26,2,5.59,2,10.37,0,3.86-6.12,8.25-14.1,8.25-15.16,0-24.34-3.99-24.34-45.49C0,10.64,14.63,2.66,26.6,2.66c11.31,0,12.64,3.86,12.64,6.78',
			'M41.9,36.84c0-1.6.27-3.46,1.46-4.39,1.6-1.33,9.58-3.19,23.41-3.19,9.04,0,13.57,4.26,13.57,16.23v6.92c0,22.74-.67,43.09-.67,43.09-4.52,2.66-11.17,4.26-19.15,4.26-9.84,0-18.89-.66-18.89-21.41,0-18.22,7.45-21.94,14.76-21.94,2.53,0,6.65.4,9.04,1.99v-9.84c0-3.46-1.46-4.92-4.39-4.92-5.05,0-12.77,1.2-17.16,2.79-1.86-3.06-2-8.51-2-9.58M65.44,68.36c-.93-.93-2.39-1.06-3.46-1.06-2.93,0-4.65,2.13-4.65,10.64s.67,9.58,3.99,9.58c.93,0,3.06-.27,3.86-1.33,0,0,.27-8.51.27-17.82',
			'M88.31,35.24c6.12-4.26,11.04-5.98,18.49-5.98s8.91,1.33,8.91,5.98c0,2.39-.27,5.98-1.46,9.31-1.86-.93-3.59-1.06-4.92-1.06-1.6,0-4.12.8-5.72,3.06l-.13,48.54q0,2.66-15.16,2.66v-62.51Z',
			'M119.96,36.84c0-1.6.27-3.46,1.46-4.39,1.6-1.33,9.58-3.19,23.41-3.19,9.04,0,13.57,4.26,13.57,16.23v6.92c0,22.74-.67,43.09-.67,43.09-4.52,2.66-11.17,4.26-19.15,4.26-9.84,0-18.88-.66-18.88-21.41,0-18.22,7.45-21.94,14.76-21.94,2.53,0,6.65.4,9.04,1.99v-9.84c0-3.46-1.46-4.92-4.39-4.92-5.05,0-12.77,1.2-17.16,2.79-1.86-3.06-2-8.51-2-9.58M143.51,68.36c-.93-.93-2.39-1.06-3.46-1.06-2.93,0-4.65,2.13-4.65,10.64s.66,9.58,3.99,9.58c.93,0,3.06-.27,3.86-1.33,0,0,.27-8.51.27-17.82',
			'M202.69,36.97c-2.53-1.86-4.65-3.59-9.84-3.59-9.44,0-20.22,3.99-20.22,33.12,0,26.6,6.92,29.39,17.82,29.39,5.19,0,9.44-2.39,12.5-4.92.53.93.8,2.13.8,3.06,0,1.73-6.38,5.99-13.7,5.99-12.9,0-21.81-2.53-21.81-34.05,0-33.12,13.43-36.71,24.21-36.71,5.59,0,11.17,2.79,11.17,4.26,0,1.06-.27,2.53-.93,3.46',
			'M234.21,29.26c13.43,0,20.75,8.11,20.75,35.38,0,23.27-7.98,35.11-21.28,35.11s-21.41-4.92-21.41-34.98c0-24.87,8.25-35.51,21.94-35.51M233.01,95.62c10.64,0,17.42-10.37,17.42-31.39,0-24.47-6.12-30.85-16.23-30.85s-17.55,8.51-17.55,32.18,5.99,30.06,16.36,30.06',
			'M286.88,29.26c13.43,0,20.75,8.11,20.75,35.38,0,23.27-7.98,35.11-21.28,35.11s-21.41-4.92-21.41-34.98c0-24.87,8.25-35.51,21.94-35.51M285.68,95.62c10.64,0,17.42-10.37,17.42-31.39,0-24.47-6.12-30.85-16.23-30.85s-17.55,8.51-17.55,32.18,5.99,30.06,16.36,30.06',
			'M325.71,96.29c0,1.46.27,1.46-4.39,1.46V2.39c0-2.39,1.07-2.39,4.39-2.39v96.29Z',
		);

		$svg = '<svg class="cm-logo" viewBox="0 0 325.72 100.01" role="img" aria-label="Caracool">';
		foreach ( $p as $d ) {
			$svg .= '<path d="' . $d . '" fill="#1a1a1a"/>';
		}
		return $svg . '</svg>';
	}

	// ── Comprobador de actualizaciones contra las releases de GitHub ────

	private function ultima_release() {
		$cache = get_site_transient( 'caracool_motion_release' );
		if ( false !== $cache ) {
			return $cache;
		}

		$r = wp_remote_get(
			'https://api.github.com/repos/' . CARACOOL_MOTION_REPO . '/releases/latest',
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
			set_site_transient( 'caracool_motion_release', array(), 30 * MINUTE_IN_SECONDS );
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $r ), true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		set_site_transient( 'caracool_motion_release', $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$rel = $this->ultima_release();
		if ( empty( $rel['tag_name'] ) ) {
			return $transient;
		}

		$nueva = ltrim( $rel['tag_name'], 'vV' );
		if ( version_compare( $nueva, CARACOOL_MOTION_VERSION, '<=' ) ) {
			return $transient;
		}

		$zip = '';
		if ( ! empty( $rel['assets'][0]['browser_download_url'] ) ) {
			$zip = $rel['assets'][0]['browser_download_url'];
		} elseif ( ! empty( $rel['zipball_url'] ) ) {
			$zip = $rel['zipball_url'];
		}

		$slug = plugin_basename( CARACOOL_MOTION_FILE );

		$item                 = new stdClass();
		$item->slug           = 'caracool-motion';
		$item->plugin         = $slug;
		$item->new_version    = $nueva;
		$item->url            = 'https://github.com/' . CARACOOL_MOTION_REPO;
		$item->package        = $zip;
		$item->tested         = get_bloginfo( 'version' );
		$transient->response[ $slug ] = $item;

		return $transient;
	}

	public function update_details( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'caracool-motion' !== $args->slug ) {
			return $res;
		}

		$rel = $this->ultima_release();

		$info                = new stdClass();
		$info->name          = 'Caracool Motion';
		$info->slug          = 'caracool-motion';
		$info->version       = ! empty( $rel['tag_name'] ) ? ltrim( $rel['tag_name'], 'vV' ) : CARACOOL_MOTION_VERSION;
		$info->author        = '<a href="https://caracool.net">Caracool</a>';
		$info->homepage      = 'https://github.com/' . CARACOOL_MOTION_REPO;
		$info->sections      = array(
			'description' => 'Movimiento para webs de Elementor: scroll con inercia y transiciones de sección que se eligen desde el propio panel.',
			'changelog'   => ! empty( $rel['body'] ) ? nl2br( esc_html( $rel['body'] ) ) : 'Sin notas.',
		);
		if ( ! empty( $rel['assets'][0]['browser_download_url'] ) ) {
			$info->download_link = $rel['assets'][0]['browser_download_url'];
		}

		return $info;
	}
}

new Caracool_Motion();

// ── Módulos ─────────────────────────────────────────────────────────────
// Cada uno se autorregistra. Si el archivo no existe, el plugin sigue
// funcionando sin ese módulo.
foreach ( array( 'cm-scroll.php', 'cm-botones.php', 'cm-cabecera.php', 'cm-intro.php' ) as $modulo ) {
	$ruta = CARACOOL_MOTION_DIR . 'modules/' . $modulo;
	if ( file_exists( $ruta ) ) {
		require_once $ruta;
	}
}
