<?php
namespace Ortszeichen\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Settings {

	public function register() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_settings_page() {
		add_menu_page(
			'ORTSZEICHEN Channels',
			'OZ Channels',
			'manage_options',
			'oz-channels',
			[ $this, 'render_settings_page' ],
			'dashicons-networking',
			58
		);
	}

	public function register_settings() {
		register_setting( 'oz_channels_options', 'oz_channels_active_adapters' );
		register_setting( 'oz_channels_options', 'oz_channels_pricing_formulas' );
		register_setting( 'oz_channels_options', 'oz_channels_feed_mode' );
	}

	public function render_settings_page() {
		$active_adapters  = get_option( 'oz_channels_active_adapters', [] );
		$pricing_formulas = get_option( 'oz_channels_pricing_formulas', [] );
		$feed_mode        = get_option( 'oz_channels_feed_mode', 'live' );

		$adapters = [ 'amazon' => 'Amazon Custom', 'etsy' => 'Etsy' ];
		?>
		<div class="wrap">
			<h1>ORTSZEICHEN Channels - Multi-Channel-Hub</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'oz_channels_options' ); ?>
				
				<h2>1. Aktive Kanäle (Adapter)</h2>
				<table class="form-table">
					<?php foreach ( $adapters as $key => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="oz_channels_active_adapters[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( isset( $active_adapters[$key] ), true ); ?>>
								Aktivieren
							</label>
							<br><br>
							<label>
								Preis-Aufschlag Formel (z.B. <code>* 1.15 + 4.90</code>):<br>
								<input type="text" class="regular-text" name="oz_channels_pricing_formulas[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $pricing_formulas[$key] ?? '' ); ?>" placeholder="* 1.15">
							</label>
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<h2>2. Feed- & Export-Modus</h2>
				<table class="form-table">
					<tr>
						<th scope="row">Export-Typ</th>
						<td>
							<label>
								<input type="radio" name="oz_channels_feed_mode" value="live" <?php checked( $feed_mode, 'live' ); ?>> Live REST-API (JSON)
							</label><br>
							<label>
								<input type="radio" name="oz_channels_feed_mode" value="static" <?php checked( $feed_mode, 'static' ); ?>> Statische JSON-Datei (via WP-Cron)
							</label>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
