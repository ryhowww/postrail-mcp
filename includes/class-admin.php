<?php

namespace PostRailMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function add_menu(): void {
		add_options_page(
			'PostRail MCP',
			'PostRail MCP',
			'manage_options',
			'postrail-mcp',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting( 'postrail_mcp', 'postrail_mcp_secret', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
	}

	public function render_page(): void {
		$secret  = get_option( 'postrail_mcp_secret', '' );
		$mcp_url = rest_url( 'postrail-mcp/v1/mcp' );

		if ( isset( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>PostRail MCP</h1>
			<p>Copy these values into your <a href="https://postrail.com" target="_blank">PostRail</a> site settings to connect this site.</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'postrail_mcp' ); ?>
				<table class="form-table">
					<tr>
						<th>MCP Endpoint URL</th>
						<td>
							<input type="text" value="<?php echo esc_attr( $mcp_url ); ?>" class="regular-text code" style="font-family: monospace;" readonly />
							<button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js( $mcp_url ); ?>')">Copy</button>
						</td>
					</tr>
					<tr>
						<th><label for="postrail_mcp_secret">Shared Secret</label></th>
						<td>
							<input type="text" name="postrail_mcp_secret" id="postrail_mcp_secret"
								value="<?php echo esc_attr( $secret ); ?>"
								class="regular-text code" style="font-family: monospace;" readonly />
							<button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('postrail_mcp_secret').value)">Copy</button>
							<p class="description" style="margin-top: 4px;">Auto-generated on activation. <a href="#" id="pr-regenerate">Regenerate</a></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p class="description">Keep your shared secret private. If compromised, regenerate it and update PostRail.</p>
		</div>

		<script>
		document.getElementById('pr-regenerate').addEventListener('click', function(e) {
			e.preventDefault();
			if (!confirm('This will break any existing PostRail connection. You will need to update the secret in PostRail. Continue?')) return;
			var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			var secret = '';
			var arr = new Uint8Array(64);
			crypto.getRandomValues(arr);
			for (var i = 0; i < 64; i++) secret += chars[arr[i] % chars.length];
			document.getElementById('postrail_mcp_secret').value = secret;
			document.getElementById('postrail_mcp_secret').removeAttribute('readonly');
			alert('New secret generated. Click Save Changes to apply it.');
		});
		</script>
		<?php
	}
}
