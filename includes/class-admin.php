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
		$secret      = get_option( 'postrail_mcp_secret', '' );
		$has_secret   = ! empty( $secret );
		$mcp_url     = rest_url( 'postrail-mcp/v1/mcp' );

		if ( isset( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>PostRail MCP</h1>

			<?php if ( ! $has_secret ) : ?>
				<div class="notice notice-warning">
					<p><strong>Enter a connection secret to enable MCP access.</strong></p>
				</div>
			<?php else : ?>
				<div class="notice notice-success">
					<p><strong>Secret configured.</strong> MCP endpoint is active.</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'postrail_mcp' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="postrail_mcp_secret">Connection Secret</label></th>
						<td>
							<input type="text" name="postrail_mcp_secret" id="postrail_mcp_secret"
								value="<?php echo esc_attr( $secret ); ?>"
								class="regular-text code" style="font-family: monospace;"
								placeholder="Paste your 64-character secret here" />
							<?php submit_button( 'Save', 'primary', 'submit', false ); ?>
						</td>
					</tr>
				</table>
			</form>

			<div style="margin-top: 24px; padding: 16px; background: #f6f7f7; border-left: 4px solid #2271b1;">
				<p style="margin: 0 0 8px;"><strong>Using PostRail?</strong> Copy your property secret from the <a href="https://postrail.com" target="_blank">PostRail dashboard</a>.</p>
				<p style="margin: 0 0 8px;"><strong>Connecting directly?</strong> Generate a secret on your computer:</p>
				<code style="display: block; padding: 8px 12px; background: #fff; border: 1px solid #ddd; margin: 8px 0;">openssl rand -hex 32</code>
				<p style="margin: 8px 0 0;">Paste it here and add the same value to your MCP client config.</p>
			</div>

			<div style="margin-top: 16px;">
				<p><strong>Your MCP endpoint:</strong></p>
				<div style="display: flex; align-items: center; gap: 8px;">
					<input type="text" value="<?php echo esc_attr( $mcp_url ); ?>" class="regular-text code" style="font-family: monospace;" readonly />
					<button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js( $mcp_url ); ?>')">Copy</button>
				</div>
			</div>
		</div>
		<?php
	}
}
