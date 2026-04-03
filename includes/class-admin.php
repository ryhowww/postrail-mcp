<?php

namespace PostRailMCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
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

	public function enqueue_styles( $hook ): void {
		if ( $hook !== 'settings_page_postrail-mcp' ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', $this->get_styles() );
	}

	public function render_page(): void {
		$secret     = get_option( 'postrail_mcp_secret', '' );
		$has_secret = ! empty( $secret );
		$mcp_url    = rest_url( 'postrail-mcp/v1/mcp' );

		// Mask secret for display: show first 4 chars + dots
		$masked = $has_secret
			? substr( $secret, 0, 4 ) . str_repeat( '•', max( 0, strlen( $secret ) - 4 ) )
			: '';

		if ( isset( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}
		?>
		<div class="wrap pr-wrap">
			<h1 class="pr-title">PostRail MCP</h1>

			<!-- Status banner -->
			<?php if ( $has_secret ) : ?>
				<div class="pr-status pr-status--ok">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
					<span>Secret configured — MCP endpoint is active</span>
				</div>
			<?php else : ?>
				<div class="pr-status pr-status--warn">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
					<span>Enter a connection secret to enable MCP access</span>
				</div>
			<?php endif; ?>

			<!-- Connection Secret -->
			<div class="pr-card">
				<form method="post" action="options.php" id="pr-secret-form">
					<?php settings_fields( 'postrail_mcp' ); ?>

					<label class="pr-label" for="postrail_mcp_secret">Connection Secret</label>

					<?php if ( $has_secret ) : ?>
						<div class="pr-field-row" id="pr-masked-view">
							<input type="text" value="<?php echo esc_attr( $masked ); ?>" class="pr-input pr-input--mono" readonly />
							<button type="button" class="pr-btn pr-btn--secondary" id="pr-change-secret">Change</button>
						</div>
					<?php endif; ?>

					<div class="pr-field-row" id="pr-edit-view" <?php echo $has_secret ? 'style="display:none"' : ''; ?>>
						<input type="text" name="postrail_mcp_secret" id="postrail_mcp_secret"
							value=""
							class="pr-input pr-input--mono"
							placeholder="Paste your secret here"
							autocomplete="off" />
						<button type="submit" class="pr-btn pr-btn--primary">Save</button>
						<?php if ( $has_secret ) : ?>
							<button type="button" class="pr-btn pr-btn--secondary" id="pr-cancel-change">Cancel</button>
						<?php endif; ?>
					</div>

					<p class="pr-help">Get your secret from the <a href="https://postrail.com" target="_blank">PostRail dashboard</a>.</p>
				</form>
			</div>

			<!-- MCP Endpoint -->
			<div class="pr-card">
				<label class="pr-label">MCP Endpoint</label>
				<div class="pr-field-row">
					<input type="text" value="<?php echo esc_attr( $mcp_url ); ?>" class="pr-input pr-input--wide pr-input--mono" readonly id="pr-mcp-url" />
					<button type="button" class="pr-btn pr-btn--secondary" id="pr-copy-url">Copy</button>
				</div>
				<p class="pr-help">This is the URL your MCP client connects to.</p>
			</div>

			<!-- Standalone note -->
			<p class="pr-footnote">
				* Not using the PostRail app? You can generate your own secret and connect directly via MCP, but you won't have access to the knowledge system and site-aware features.
			</p>
		</div>

		<script>
		(function() {
			var changeBtn = document.getElementById('pr-change-secret');
			var cancelBtn = document.getElementById('pr-cancel-change');
			var maskedView = document.getElementById('pr-masked-view');
			var editView = document.getElementById('pr-edit-view');
			var copyBtn = document.getElementById('pr-copy-url');
			var urlInput = document.getElementById('pr-mcp-url');

			if (changeBtn) {
				changeBtn.addEventListener('click', function() {
					maskedView.style.display = 'none';
					editView.style.display = '';
					document.getElementById('postrail_mcp_secret').focus();
				});
			}

			if (cancelBtn) {
				cancelBtn.addEventListener('click', function() {
					maskedView.style.display = '';
					editView.style.display = 'none';
				});
			}

			if (copyBtn) {
				copyBtn.addEventListener('click', function() {
					navigator.clipboard.writeText(urlInput.value).then(function() {
						var orig = copyBtn.textContent;
						copyBtn.textContent = 'Copied';
						setTimeout(function() { copyBtn.textContent = orig; }, 1500);
					});
				});
			}
		})();
		</script>
		<?php
	}

	private function get_styles(): string {
		return '
			.pr-wrap {
				max-width: 640px;
			}
			.pr-title {
				font-size: 22px;
				font-weight: 600;
				margin-bottom: 16px;
			}

			/* Status banner */
			.pr-status {
				display: flex;
				align-items: center;
				gap: 10px;
				padding: 12px 16px;
				border-radius: 8px;
				font-size: 14px;
				font-weight: 500;
				margin-bottom: 20px;
			}
			.pr-status--ok {
				background: #ecfdf5;
				color: #065f46;
				border: 1px solid #a7f3d0;
			}
			.pr-status--warn {
				background: #fffbeb;
				color: #92400e;
				border: 1px solid #fde68a;
			}

			/* Card */
			.pr-card {
				background: #fff;
				border: 1px solid #e2e4e7;
				border-radius: 8px;
				padding: 20px 24px;
				margin-bottom: 16px;
			}

			/* Label */
			.pr-label {
				display: block;
				font-size: 13px;
				font-weight: 600;
				color: #1e1e1e;
				margin-bottom: 8px;
			}

			/* Field row */
			.pr-field-row {
				display: flex;
				align-items: center;
				gap: 8px;
			}

			/* Input */
			.pr-input {
				flex: 1;
				height: 40px;
				padding: 0 12px;
				border: 1px solid #d0d5dd;
				border-radius: 6px;
				font-size: 13px;
				background: #fff;
				color: #1e1e1e;
				box-shadow: 0 1px 2px rgba(0,0,0,0.05);
				transition: border-color 0.15s, box-shadow 0.15s;
			}
			.pr-input:focus {
				outline: none;
				border-color: #155dfc;
				box-shadow: 0 0 0 3px rgba(21,93,252,0.12);
			}
			.pr-input[readonly] {
				background: #f9fafb;
				color: #6b7280;
			}
			.pr-input--mono {
				font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
				font-size: 12px;
				letter-spacing: -0.2px;
			}
			.pr-input--wide {
				min-width: 0;
			}

			/* Buttons */
			.pr-btn {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				height: 40px;
				padding: 0 18px;
				border: none;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				white-space: nowrap;
				transition: background-color 0.15s, box-shadow 0.15s;
			}
			.pr-btn--primary {
				background: #155dfc;
				color: #fff;
			}
			.pr-btn--primary:hover {
				background: #1249d6;
			}
			.pr-btn--secondary {
				background: #f3f4f6;
				color: #374151;
				border: 1px solid #d0d5dd;
			}
			.pr-btn--secondary:hover {
				background: #e5e7eb;
			}

			/* Help text */
			.pr-help {
				font-size: 13px;
				color: #6b7280;
				margin: 8px 0 0;
			}
			.pr-help a {
				color: #155dfc;
				text-decoration: none;
			}
			.pr-help a:hover {
				text-decoration: underline;
			}

			/* Footnote */
			.pr-footnote {
				font-size: 13px;
				color: #6b7280;
				margin-top: 8px;
				line-height: 1.5;
			}
		';
	}
}
