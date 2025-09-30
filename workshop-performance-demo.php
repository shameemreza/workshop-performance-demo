<?php
/**
 * Plugin Name:       Workshop Performance Demo
 * Plugin URI:        https://github.com/shameemreza/workshop-performance-demo
 * Description:       Demonstration plugin for Query Monitor workshop - showcases performance issues and optimization techniques
 * Version:           1.0.0
 * Author:            Shameem Reza
 * Author URI:        https://shameem.blog
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       workshop-demo
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * WC requires at least: 9.0.0
 * WC tested up to:   10.1.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WORKSHOP_DEMO_VERSION', '1.0.0' );
define( 'WORKSHOP_DEMO_PLUGIN_FILE', __FILE__ );
define( 'WORKSHOP_DEMO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WORKSHOP_DEMO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class Workshop_Performance_Demo {

	/**
	 * Instance of this class
	 *
	 * @var Workshop_Performance_Demo|null
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return Workshop_Performance_Demo
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Register demo scenarios.
		add_action( 'init', array( $this, 'register_demo_scenarios' ) );

		// Add custom Query Monitor collector.
		add_filter( 'qm/collectors', array( $this, 'register_qm_collector' ) );
		add_filter( 'qm/outputter/html', array( $this, 'register_qm_output' ), 10, 2 );

		// Declare WooCommerce HPOS compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );

		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// WooCommerce specific hooks.
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_init', array( $this, 'woocommerce_demos' ) );
		}

		// Add intentional performance issues for demonstration.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['demo_slow_queries'] ) ) {
			// Check nonce OR allow demo mode for testing
			$nonce_valid = $this->check_demo_nonce( 'workshop_demo_slow_queries' );
			$demo_mode = isset( $_GET['demo_mode'] ) && $_GET['demo_mode'] === 'test';
			
			if ( $nonce_valid || $demo_mode ) {
				// Run immediately and also hook to multiple points to ensure execution
				add_action( 'init', array( $this, 'demo_slow_queries' ), 999 );
				add_action( 'wp', array( $this, 'demo_slow_queries' ), 5 );
				add_action( 'template_redirect', array( $this, 'demo_slow_queries' ), 5 );
				add_action( 'wp_footer', array( $this, 'show_demo_notice' ) );
			} else {
				// Debug: Log nonce failure
				error_log( 'Workshop Demo: Nonce verification failed for N+1 demo. Add &demo_mode=test to bypass.' );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['demo_memory_leak'] ) ) {
			// Check nonce OR allow demo mode for testing
			$nonce_valid = $this->check_demo_nonce( 'workshop_demo_memory_leak' );
			$demo_mode = isset( $_GET['demo_mode'] ) && $_GET['demo_mode'] === 'test';
			
			if ( $nonce_valid || $demo_mode ) {
				// Run memory leak demo on template_redirect for better visibility
				add_action( 'template_redirect', array( $this, 'demo_memory_leak' ), 5 );
				add_action( 'wp_footer', array( $this, 'show_demo_notice' ) );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['demo_hook_overload'] ) ) {
			// Check nonce OR allow demo mode for testing
			$nonce_valid = $this->check_demo_nonce( 'workshop_demo_hook_overload' );
			$demo_mode = isset( $_GET['demo_mode'] ) && $_GET['demo_mode'] === 'test';
			
			if ( $nonce_valid || $demo_mode ) {
				add_action( 'init', array( $this, 'demo_hook_overload' ) );
				add_action( 'wp_footer', array( $this, 'show_demo_notice' ) );
			}
		}

		// Handle reset action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in check_demo_nonce()
		if ( isset( $_GET['reset_test_data'] ) && $this->check_demo_nonce( 'workshop_reset_test_data' ) ) {
			add_action( 'admin_init', array( $this, 'reset_test_data' ) );
		}

		// Handle recreate action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in check_demo_nonce()
		if ( isset( $_GET['recreate_test_data'] ) && $this->check_demo_nonce( 'workshop_recreate_test_data' ) ) {
			add_action( 'admin_init', array( $this, 'recreate_test_data' ) );
		}
	}

	/**
	 * Check demo nonce for security
	 *
	 * @since 1.0.0
	 * @param string $action The action to verify.
	 * @return bool
	 */
	private function check_demo_nonce( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';
		
		// Use the action directly as passed - it already includes full name like 'workshop_demo_slow_queries'
		$result = wp_verify_nonce( $nonce, $action );
		
		// Debug logging 
		if ( ! $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Nonce check failed for action: "' . $action . '" with nonce: ' . substr($nonce, 0, 10) . '...' );
		}
		
		return $result;
	}

	/**
	 * Declare WooCommerce HPOS (High Performance Order Storage) compatibility
	 *
	 * @since 1.0.0
	 */
	public function declare_woocommerce_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
				'custom_order_tables', 
				WORKSHOP_DEMO_PLUGIN_FILE, 
				true 
			);
			
			// Also declare compatibility with the new product editor
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
				'product_block_editor', 
				WORKSHOP_DEMO_PLUGIN_FILE, 
				false // We don't need the block editor for this demo
			);
			
			// Declare compatibility with cart and checkout blocks
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
				'cart_checkout_blocks', 
				WORKSHOP_DEMO_PLUGIN_FILE, 
				true 
			);
		}
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Workshop Demo', 'workshop-demo' ),
			__( 'Workshop Demo', 'workshop-demo' ),
			'manage_options',
			'workshop-demo',
			array( $this, 'render_admin_page' ),
			'dashicons-performance',
			99
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin's admin page.
		if ( 'toplevel_page_workshop-demo' !== $hook ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'workshop-demo-admin',
			WORKSHOP_DEMO_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WORKSHOP_DEMO_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'workshop-demo-admin',
			WORKSHOP_DEMO_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WORKSHOP_DEMO_VERSION,
			true
		);

		// No additional inline styles needed
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if Query Monitor is active.
		$qm_active = class_exists( 'QueryMonitor' );
		$wc_active = class_exists( 'WooCommerce' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php esc_html_e( 'Master WordPress performance optimization with hands-on demonstrations', 'workshop-demo' ); ?></p>

			<?php
			// Show success messages.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['message'] ) ) {
				$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
				if ( 'reset_success' === $message ) {
					echo '<div class="notice notice-success is-dismissible"><p>';
					esc_html_e( 'Test data has been reset successfully!', 'workshop-demo' );
					echo '</p></div>';
				} elseif ( 'create_success' === $message ) {
					echo '<div class="notice notice-success is-dismissible"><p>';
					esc_html_e( 'Test data has been created successfully!', 'workshop-demo' );
					echo '</p></div>';
				}
			}
			?>

			<!-- Status Notices -->
			<?php if ( ! $qm_active ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Query Monitor Not Active', 'workshop-demo' ); ?></strong> - 
						<?php esc_html_e( 'Please activate Query Monitor plugin for the full workshop experience.', 'workshop-demo' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-success">
					<p>
						<strong><?php esc_html_e( 'Query Monitor Active', 'workshop-demo' ); ?></strong> - 
						<?php esc_html_e( 'All systems ready! You can now run performance demonstrations.', 'workshop-demo' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<!-- Performance Demo Scenarios -->
			<div class="card">
				<h2><?php esc_html_e( 'Performance Demo Scenarios', 'workshop-demo' ); ?></h2>
				<p><?php esc_html_e( 'Click any scenario below to simulate common WordPress performance issues. Monitor the results in Query Monitor.', 'workshop-demo' ); ?></p>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Demo Scenario', 'workshop-demo' ); ?></th>
							<th><?php esc_html_e( 'Description', 'workshop-demo' ); ?></th>
							<th><?php esc_html_e( 'Action', 'workshop-demo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								<strong><span class="dashicons dashicons-database"></span> <?php esc_html_e( 'N+1 Query Problem', 'workshop-demo' ); ?></strong>
							</td>
							<td>
								<?php esc_html_e( 'Demonstrates inefficient database queries that occur when fetching related data in loops. Watch Query Monitor to see the query count explode!', 'workshop-demo' ); ?>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'demo_slow_queries' => '1', 'demo_mode' => 'test' ), home_url() ) ); ?>" class="button button-primary" target="_blank">
									<?php esc_html_e( 'Run Demo', 'workshop-demo' ); ?> →
								</a>
								<small style="display: block; margin-top: 5px; color: #666;">
									<?php esc_html_e( 'Opens homepage with N+1 queries active', 'workshop-demo' ); ?>
								</small>
							</td>
						</tr>
						<tr>
							<td>
								<strong><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Memory Leak Simulation', 'workshop-demo' ); ?></strong>
							</td>
							<td>
								<?php esc_html_e( 'Creates memory-intensive operations that demonstrate poor memory management. Monitor memory usage spike in Query Monitor.', 'workshop-demo' ); ?>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'demo_memory_leak' => '1', 'demo_mode' => 'test' ), home_url() ) ); ?>" class="button button-primary" target="_blank">
									<?php esc_html_e( 'Run Demo', 'workshop-demo' ); ?> →
								</a>
								<small style="display: block; margin-top: 5px; color: #666;">
									<?php esc_html_e( 'Check Environment panel for memory spike', 'workshop-demo' ); ?>
								</small>
							</td>
						</tr>
						<tr>
							<td>
								<strong><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Hook Overload', 'workshop-demo' ); ?></strong>
							</td>
							<td>
								<?php esc_html_e( 'Adds multiple slow callbacks to WordPress hooks. Check the Hooks panel in Query Monitor to see the impact.', 'workshop-demo' ); ?>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'demo_hook_overload' => '1', 'demo_mode' => 'test' ), home_url() ) ); ?>" class="button button-primary" target="_blank">
									<?php esc_html_e( 'Run Demo', 'workshop-demo' ); ?> →
								</a>
								<small style="display: block; margin-top: 5px; color: #666;">
									<?php esc_html_e( 'Check Hooks & Actions panel for overload', 'workshop-demo' ); ?>
								</small>
							</td>
						</tr>
						<?php if ( $wc_active ) : ?>
						<tr>
							<td>
								<strong><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Slow Cart Calculation', 'workshop-demo' ); ?></strong>
							</td>
							<td>
								<?php esc_html_e( 'Simulates expensive cart calculations in WooCommerce. Navigate to the cart page to see the performance impact.', 'workshop-demo' ); ?>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'demo_wc_cart_slow' => '1', 'demo_mode' => 'test' ), wc_get_cart_url() ) ); ?>" class="button button-primary" target="_blank">
									<?php esc_html_e( 'Run Demo', 'workshop-demo' ); ?> →
								</a>
								<small style="display: block; margin-top: 5px; color: #666;">
									<?php esc_html_e( 'Opens cart page - add items first!', 'workshop-demo' ); ?>
								</small>
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Code Examples -->
			<div class="card">
				<h2><?php esc_html_e( 'Optimization Examples', 'workshop-demo' ); ?></h2>
				<p><?php esc_html_e( 'Learn best practices with real-world code examples.', 'workshop-demo' ); ?></p>

				<h3><?php esc_html_e( 'Database Query Optimization', 'workshop-demo' ); ?></h3>
				
				<h4><?php esc_html_e( 'Bad Practice - N+1 Problem', 'workshop-demo' ); ?></h4>
				<pre style="background: #f6f6f6; padding: 15px; overflow-x: auto;">
// This creates 101 queries!
$products = get_posts([
    'post_type' => 'product',
    'numberposts' => 100
]);

foreach ($products as $product) {
    // Each iteration triggers a new query
    $meta = get_post_meta($product->ID);
    $terms = wp_get_post_terms($product->ID, 'product_cat');
}
				</pre>

				<h4><?php esc_html_e( 'Good Practice - Single Query', 'workshop-demo' ); ?></h4>
				<pre style="background: #f6f6f6; padding: 15px; overflow-x: auto;">
// This creates only 1 query!
global $wpdb;
$products_with_meta = $wpdb->get_results("
    SELECT p.*, pm.meta_key, pm.meta_value
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'product'
    AND p.post_status = 'publish'
    LIMIT 100
");
				</pre>

				<h3><?php esc_html_e( 'Transient Caching Strategy', 'workshop-demo' ); ?></h3>
				<pre style="background: #f6f6f6; padding: 15px; overflow-x: auto;">
function get_expensive_data() {
    $cache_key = 'my_expensive_data_' . get_current_blog_id();
    $cached = get_transient($cache_key);
    
    if (false !== $cached) {
        return $cached; // Return cached data
    }
    
    // Perform expensive operation
    $data = $this->perform_expensive_calculation();
    
    // Cache for 12 hours
    set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
    
    return $data;
}
				</pre>

				<h3><?php esc_html_e( 'Object Cache Implementation', 'workshop-demo' ); ?></h3>
				<pre style="background: #f6f6f6; padding: 15px; overflow-x: auto;">
class Product_Cache {
    private static $cache = [];
    
    public static function get($product_id) {
        $cache_key = 'product_' . $product_id;
        
        // Check runtime cache first
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        // Check persistent cache
        $product = wp_cache_get($cache_key, 'products');
        
        if (false === $product) {
            $product = get_post($product_id);
            wp_cache_set($cache_key, $product, 'products', HOUR_IN_SECONDS);
        }
        
        self::$cache[$cache_key] = $product;
        return $product;
    }
}
				</pre>

				<?php if ( $wc_active ) : ?>
				<h3><?php esc_html_e( 'WooCommerce HPOS Optimization', 'workshop-demo' ); ?></h3>
				
				<h4><?php esc_html_e( 'Legacy Post Meta Queries', 'workshop-demo' ); ?></h4>
				<pre style="background: #f6f6f6; padding: 15px; overflow-x: auto;">
// Old way - uses post meta
$order_total = get_post_meta($order_id, '_order_total', true);
$customer_id = get_post_meta($order_id, '_customer_user', true);
$items = get_post_meta($order_id, '_order_items', true);
				</pre>

				<h4><?php esc_html_e( 'HPOS Optimized', 'workshop-demo' ); ?></h4>
				<pre style="background: #f6f6f6; padding: 15px; overflow-x: auto;">
// New way - uses HPOS tables
$order = wc_get_order($order_id);
$order_total = $order->get_total();
$customer_id = $order->get_customer_id();
$items = $order->get_items();
				</pre>
				<?php endif; ?>
			</div>

			<!-- Quick Reference -->
			<div class="card">
				<h2><?php esc_html_e( 'Quick Reference', 'workshop-demo' ); ?></h2>
				
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tool', 'workshop-demo' ); ?></th>
							<th><?php esc_html_e( 'Description', 'workshop-demo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Query Monitor', 'workshop-demo' ); ?></strong></td>
							<td><?php esc_html_e( 'Check the admin bar for Query Monitor. Look for red indicators showing slow queries or errors.', 'workshop-demo' ); ?></td>
						</tr>
						<tr>
							<td><strong><span class="dashicons dashicons-chart-line"></span> <?php esc_html_e( 'Performance Metrics', 'workshop-demo' ); ?></strong></td>
							<td><?php esc_html_e( 'Monitor page generation time, memory usage, database queries, and HTTP API calls.', 'workshop-demo' ); ?></td>
						</tr>
						<tr>
							<td><strong><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e( 'Best Practices', 'workshop-demo' ); ?></strong></td>
							<td><?php esc_html_e( 'Use caching, optimize database queries, lazy load resources, and minimize external API calls.', 'workshop-demo' ); ?></td>
						</tr>
						<tr>
							<td><strong><span class="dashicons dashicons-hammer"></span> <?php esc_html_e( 'Debug Tools', 'workshop-demo' ); ?></strong></td>
							<td><?php esc_html_e( 'Enable WP_DEBUG, use error logs, leverage browser DevTools, and install Xdebug for deep analysis.', 'workshop-demo' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Data Management -->
			<div class="card">
				<h2><?php esc_html_e( 'Data Management', 'workshop-demo' ); ?></h2>
				<p><?php esc_html_e( 'Manage test data for practice sessions and workshops.', 'workshop-demo' ); ?></p>
				
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Test Posts', 'workshop-demo' ); ?></th>
						<td>
							<?php
							$test_posts = get_posts(
								array(
									'post_type'   => 'workshop_test',
									'numberposts' => -1,
									'post_status' => 'any',
								)
							);
							$count = count( $test_posts );
							?>
							<p>
								<?php
								printf(
									/* translators: %d: Number of test posts */
									esc_html__( 'Currently %d test posts in database.', 'workshop-demo' ),
									$count
								);
								?>
							</p>
							<?php if ( $count > 0 ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'reset_test_data', '1' ), 'workshop_reset_test_data' ) ); ?>" 
								   class="button button-secondary" 
								   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to reset all test data? This will delete all workshop test posts and clear the demo logs table.', 'workshop-demo' ); ?>');">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Reset Test Data', 'workshop-demo' ); ?>
								</a>
								<span class="description"><?php esc_html_e( 'Remove all test posts and logs', 'workshop-demo' ); ?></span>
							<?php else : ?>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'recreate_test_data', '1' ), 'workshop_recreate_test_data' ) ); ?>" 
								   class="button button-secondary">
									<span class="dashicons dashicons-plus"></span>
									<?php esc_html_e( 'Create Test Data', 'workshop-demo' ); ?>
								</a>
								<span class="description"><?php esc_html_e( 'Create 50 test posts for demonstrations', 'workshop-demo' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
			
		</div>
		<?php
	}

	/**
	 * Demo: Slow queries (N+1 problem)
	 */
	public function demo_slow_queries() {
		// Prevent running multiple times
		static $has_run = false;
		if ( $has_run ) {
			return;
		}
		$has_run = true;

		// Debug: Log that demo is starting
		error_log( 'Workshop Demo: Starting N+1 demo...' );

		// Trigger slow queries that Query Monitor will catch.
		global $wpdb;

		// First, get ALL our custom posts to maximize the N+1 effect
		$test_posts = get_posts(
			array(
				'post_type'   => 'workshop_test',
				'numberposts' => -1, // Get ALL posts for maximum impact
				'post_status' => 'publish',
			)
		);

		// Fall back to regular posts if no test posts exist
		if ( empty( $test_posts ) || count( $test_posts ) < 10 ) {
			$test_posts = get_posts(
				array(
					'post_type'   => array( 'post', 'page', 'workshop_test' ),
					'numberposts' => 100,
					'post_status' => 'any',
				)
			);
		}

		// Track query count for demonstration
		$query_count = 0;

		// Bad practice #1: N+1 problem - query in a loop
		foreach ( $test_posts as $post ) {
			// Individual query for each post meta (BAD!)
			$all_meta = get_post_meta( $post->ID );
			$query_count++;

			// Get each meta value separately (EVEN WORSE!)
			get_post_meta( $post->ID, '_edit_last', true );
			get_post_meta( $post->ID, '_edit_lock', true );
			get_post_meta( $post->ID, '_thumbnail_id', true );
			$query_count += 3;

			// Another individual query for author (BAD!)
			$author_name = get_the_author_meta( 'display_name', $post->post_author );
			$author_email = get_the_author_meta( 'user_email', $post->post_author );
			$query_count += 2;

			// Direct database query for each post (VERY BAD!)
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$custom_query = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d",
					$post->ID
				)
			);
			$query_count++;

			// More direct queries (TERRIBLE!)
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d",
					$post->ID
				)
			);
			$query_count++;

			// Get terms for each post individually (BAD!)
			wp_get_post_terms( $post->ID, 'category' );
			wp_get_post_terms( $post->ID, 'post_tag' );
			wp_get_post_categories( $post->ID );
			wp_get_post_tags( $post->ID );
			$query_count += 4;

			// Get comments data individually (BAD!)
			wp_count_comments( $post->ID );
			get_comments( array( 'post_id' => $post->ID, 'number' => 1 ) );
			$query_count += 2;

			// Get post thumbnail data (BAD in loop!)
			get_post_thumbnail_id( $post->ID );
			has_post_thumbnail( $post->ID );
			$query_count += 2;
		}

		// Bad practice #2: Large uncached query
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Demo purpose
		$large_query = $wpdb->get_results(
			"SELECT p.*, pm.* 
			FROM {$wpdb->posts} p 
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
			WHERE p.post_status = 'publish' 
			ORDER BY p.post_date DESC 
			LIMIT 500"
		);
		$query_count++;

		// Bad practice #3: Multiple slow JOIN queries
		for ( $i = 0; $i < 5; $i++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Demo purpose
			$slow_join = $wpdb->get_results(
				"SELECT p.ID, p.post_title, 
					COUNT(DISTINCT pm.meta_id) as meta_count,
					COUNT(DISTINCT c.comment_ID) as comment_count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				LEFT JOIN {$wpdb->comments} c ON p.ID = c.comment_post_ID
				WHERE p.post_type IN ('post', 'page', 'workshop_test')
				GROUP BY p.ID
				HAVING meta_count > 0
				ORDER BY meta_count DESC
				LIMIT 20"
			);
			$query_count++;
		}

		// Store demo data for display
		set_transient( 'workshop_demo_n1_query_count', $query_count, 60 );

		// Calculate total expected queries (approximate)
		$posts_count = count( $test_posts );
		$expected_queries = $posts_count * 16; // 16 queries per post from our loop

		// Log for demonstration - Always log, not just in debug mode
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( 
			'Workshop Demo N+1 Complete: %d posts processed, generating approximately %d+ extra queries! Check Query Monitor!', 
			$posts_count, 
			$expected_queries 
		) );
	}

	/**
	 * Demo: Memory leak
	 */
	public function demo_memory_leak() {
		// Track initial memory
		$initial_memory = memory_get_usage( true );
		$initial_peak = memory_get_peak_usage( true );

		// Create multiple large arrays to consume significant memory
		$memory_hog = array();
		$large_strings = array();
		$object_cache = array();

		// Bad practice #1: Store large amounts of data in memory
		for ( $i = 0; $i < 50000; $i++ ) {
			$memory_hog[] = array(
				'id'          => $i,
				'data'        => str_repeat( 'Memory test data ', 100 ),
				'nested'      => array(
					'level1' => str_repeat( 'Nested data ', 50 ),
					'level2' => array(
						'deep' => str_repeat( 'Deep nested data ', 25 ),
					),
				),
				'large_text'  => wp_rand( 1000000, 9999999 ),
				'binary_data' => base64_encode( str_repeat( 'X', 1024 ) ),
			);

			// Create string concatenation leak
			if ( $i % 100 === 0 ) {
				$large_strings[] = str_repeat( 'LEAK-' . $i . '-', 1000 );
			}
		}

		// Bad practice #2: Store in multiple global variables (creates memory leak)
		$GLOBALS['workshop_memory_leak'] = $memory_hog;
		$GLOBALS['workshop_memory_leak_copy1'] = $memory_hog;
		$GLOBALS['workshop_memory_leak_copy2'] = array_merge( $memory_hog, $memory_hog );

		// Bad practice #3: Create circular references
		$obj1 = new stdClass();
		$obj2 = new stdClass();
		$obj1->data = str_repeat( 'Circular reference data ', 10000 );
		$obj2->data = str_repeat( 'More circular data ', 10000 );
		$obj1->ref = $obj2;
		$obj2->ref = $obj1;
		$GLOBALS['workshop_circular_ref'] = $obj1;

		// Bad practice #4: Cache everything without limits
		for ( $j = 0; $j < 1000; $j++ ) {
			$cache_key = 'workshop_cache_' . $j;
			$object_cache[ $cache_key ] = array(
				'data' => str_repeat( 'Cached data ' . $j, 500 ),
				'time' => time(),
				'meta' => array_fill( 0, 100, 'metadata' ),
			);
		}
		$GLOBALS['workshop_object_cache'] = $object_cache;

		// Bad practice #5: Load all posts into memory
		$all_posts = get_posts(
			array(
				'numberposts' => -1,
				'post_type'   => 'any',
				'post_status' => 'any',
			)
		);
		$GLOBALS['workshop_all_posts'] = $all_posts;

		// Calculate memory increase
		$final_memory = memory_get_usage( true );
		$final_peak = memory_get_peak_usage( true );
		$memory_increase = ( $final_memory - $initial_memory ) / 1024 / 1024; // Convert to MB
		$peak_increase = ( $final_peak - $initial_peak ) / 1024 / 1024; // Convert to MB

		// Store for display
		set_transient(
			'workshop_memory_demo_stats',
			array(
				'initial'  => round( $initial_memory / 1024 / 1024, 2 ),
				'final'    => round( $final_memory / 1024 / 1024, 2 ),
				'increase' => round( $memory_increase, 2 ),
				'peak'     => round( $final_peak / 1024 / 1024, 2 ),
			),
			60
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Workshop Demo: Memory leak created - Increased memory by %.2f MB! Check Query Monitor!', $memory_increase ) );
		}
	}

	/**
	 * Demo: Hook overload
	 */
	public function demo_hook_overload() {
		// Track hook count
		$hook_count = 0;
		
		// Add multiple slow callbacks to common hooks.
		for ( $i = 0; $i < 50; $i++ ) {
			// Add to the_content filter
			add_filter(
				'the_content',
				function( $content ) use ( $i ) {
					// Simulate slow processing.
					usleep( 5000 ); // 5ms delay per callback.
					return $content . '<!-- Content hook callback ' . $i . ' -->';
				},
				10 + $i
			);
			$hook_count++;
			
			// Add to the_title filter
			add_filter(
				'the_title',
				function( $title ) use ( $i ) {
					// Simulate processing
					if ( $i % 5 === 0 ) {
						usleep( 3000 ); // 3ms delay for some callbacks
					}
					return $title;
				},
				10 + $i
			);
			$hook_count++;
		}

		// Add to common action hooks.
		for ( $j = 0; $j < 30; $j++ ) {
			// wp_head hooks
			add_action(
				'wp_head',
				function() use ( $j ) {
					// Simulate external API call
					usleep( 8000 ); // 8ms delay
					echo '<!-- Head hook ' . esc_html( $j ) . ' -->';
				},
				10 + $j
			);
			$hook_count++;
			
			// wp_footer hooks
			add_action(
				'wp_footer',
				function() use ( $j ) {
					// Simulate heavy computation
					usleep( 10000 ); // 10ms delay.
					echo '<!-- Footer hook ' . esc_html( $j ) . ' -->';
				},
				10 + $j
			);
			$hook_count++;
			
			// init hooks (already fired but shows in QM)
			add_action(
				'wp_enqueue_scripts',
				function() use ( $j ) {
					// Simulate asset loading
					if ( $j % 3 === 0 ) {
						usleep( 15000 ); // 15ms delay for some
					}
				},
				10 + $j
			);
			$hook_count++;
		}

		// Add some very slow hooks to make the impact obvious
		add_action( 'wp_footer', function() {
			// Simulate a really slow operation
			usleep( 50000 ); // 50ms delay
			echo '<!-- SLOW HOOK: Database sync simulation -->';
		}, 999 );
		$hook_count++;

		// Store count for display
		set_transient( 'workshop_hook_overload_count', $hook_count, 60 );

		// Log for debugging
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( 'Workshop Demo: Hook overload created - Added %d hooks! Check Query Monitor Hooks & Actions panel!', $hook_count ) );
	}

	/**
	 * Reset test data
	 */
	public function reset_test_data() {
		global $wpdb;

		// Delete all workshop test posts.
		$test_posts = get_posts(
			array(
				'post_type'   => 'workshop_test',
				'numberposts' => -1,
				'post_status' => 'any',
			)
		);

		foreach ( $test_posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		// Clear the demo logs table.
		$table_name = $wpdb->prefix . 'workshop_demo_logs';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		// Clear any transients.
		delete_transient( 'workshop_demo_slow_query_data' );
		delete_transient( 'workshop_demo_memory_leak_data' );
		delete_transient( 'workshop_demo_hook_overload_data' );
		delete_transient( 'workshop_demo_cart_calc_data' );

		// Redirect with success message.
		wp_safe_redirect( add_query_arg( 'message', 'reset_success', admin_url( 'admin.php?page=workshop-demo' ) ) );
		exit;
	}

	/**
	 * Recreate test data
	 */
	public function recreate_test_data() {
		// Create sample data.
		self::create_sample_data();

		// Redirect with success message.
		wp_safe_redirect( add_query_arg( 'message', 'create_success', admin_url( 'admin.php?page=workshop-demo' ) ) );
		exit;
	}

	/**
	 * Show demo notice on frontend
	 */
	public function show_demo_notice() {
		// Display enhanced notice that demo is running
		?>
		<div style="position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px 25px; border-radius: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; z-index: 99999; box-shadow: 0 4px 20px rgba(0,0,0,0.4); max-width: 400px; animation: slideInRight 0.5s ease;">
			<style>
				@keyframes slideInRight {
					from { transform: translateX(100%); opacity: 0; }
					to { transform: translateX(0); opacity: 1; }
				}
			</style>
			<div style="display: flex; align-items: center; margin-bottom: 10px;">
				<span style="font-size: 24px; margin-right: 10px;">⚠️</span>
				<strong style="font-size: 16px;">Workshop Demo Active</strong>
			</div>
			<?php
			// Check which demo is running
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['demo_slow_queries'] ) ) {
				$query_count = get_transient( 'workshop_demo_n1_query_count' );
				?>
				<div style="font-size: 18px; margin-bottom: 8px; font-weight: 600;">N+1 Query Problem</div>
				<div style="font-size: 14px; line-height: 1.4; margin-bottom: 8px;">
					Open Query Monitor → Database Queries panel
				</div>
				<?php if ( $query_count ) : ?>
					<div style="font-size: 12px; opacity: 0.9; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3);">
						Generated <?php echo esc_html( $query_count ); ?>+ excessive queries! Normal page: ~80 queries
					</div>
				<?php endif; ?>
				<?php
			} elseif ( isset( $_GET['demo_memory_leak'] ) ) {
				$memory_stats = get_transient( 'workshop_memory_demo_stats' );
				?>
				<div style="font-size: 18px; margin-bottom: 8px; font-weight: 600;">Memory Leak Simulation</div>
				<div style="font-size: 14px; line-height: 1.4; margin-bottom: 8px;">
					Open Query Monitor → Environment panel
				</div>
				<?php if ( $memory_stats && isset( $memory_stats['increase'] ) ) : ?>
					<div style="font-size: 12px; opacity: 0.9; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3);">
						Memory increased by <?php echo esc_html( $memory_stats['increase'] ); ?> MB | Peak: <?php echo esc_html( $memory_stats['peak'] ); ?> MB
					</div>
				<?php endif; ?>
				<?php
			} elseif ( isset( $_GET['demo_hook_overload'] ) ) {
				$hook_count = get_transient( 'workshop_hook_overload_count' );
				?>
				<div style="font-size: 18px; margin-bottom: 8px; font-weight: 600;">Hook Overload</div>
				<div style="font-size: 14px; line-height: 1.4; margin-bottom: 8px;">
					Open Query Monitor → Hooks & Actions panel<br>
					Look for multiple slow callbacks on wp_head, wp_footer, the_content
				</div>
				<?php if ( $hook_count ) : ?>
					<div style="font-size: 12px; opacity: 0.9; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3);">
						Added <?php echo esc_html( $hook_count ); ?> hooks with delays | Total delay: ~1 second
					</div>
				<?php endif; ?>
				<?php
			} elseif ( isset( $_GET['demo_wc_cart_slow'] ) ) {
				?>
				<div style="font-size: 18px; margin-bottom: 8px; font-weight: 600;">Slow Cart Calculation</div>
				<div style="font-size: 14px; line-height: 1.4; margin-bottom: 8px;">
					Cart calculations artificially slowed (500ms per item)<br>
					Check Query Monitor timing for woocommerce_before_calculate_totals
				</div>
				<div style="font-size: 12px; opacity: 0.9; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.3);">
					Add more items to cart to see cumulative impact
				</div>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * WooCommerce specific demos
	 */
	public function woocommerce_demos() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['demo_wc_cart_slow'] ) ) {
			return;
		}

		// Check nonce OR allow demo mode for testing
		$nonce_valid = $this->check_demo_nonce( 'workshop_demo_wc_cart_slow' );
		$demo_mode = isset( $_GET['demo_mode'] ) && $_GET['demo_mode'] === 'test';
		
		if ( ! $nonce_valid && ! $demo_mode ) {
			return;
		}

		// Slow down cart calculations.
		add_filter(
			'woocommerce_calculated_total',
			function( $total ) {
				// Simulate complex tax calculation.
				for ( $i = 0; $i < 100; $i++ ) {
					$tax_calculation = $total * 0.1 * ( $i / 100 );
				}

				// Simulate external API call for shipping.
				usleep( 100000 ); // 100ms delay.

				return $total;
			}
		);

		// Add unnecessary database queries in cart.
		add_action(
			'woocommerce_before_calculate_totals',
			function( $cart ) {
				// Validate cart object exists and has required method.
				if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
					return;
				}

				foreach ( $cart->get_cart() as $cart_item ) {
					// Bad: Individual queries for each item.
					$product = wc_get_product( $cart_item['product_id'] );
					$all_meta = get_post_meta( $cart_item['product_id'] );
					$stock = get_post_meta( $cart_item['product_id'], '_stock', true );
				}
			}
		);
	}

	/**
	 * Register demo scenarios
	 */
	public function register_demo_scenarios() {
		// Register a custom post type for testing.
		register_post_type(
			'workshop_test',
			array(
				'labels'      => array(
					'name'          => __( 'Workshop Tests', 'workshop-demo' ),
					'singular_name' => __( 'Workshop Test', 'workshop-demo' ),
				),
				'public'      => true,
				'has_archive' => true,
				'supports'    => array( 'title', 'editor', 'custom-fields' ),
			)
		);
	}

	/**
	 * Register custom Query Monitor collector
	 *
	 * @since 1.0.0
	 * @param array $collectors Existing QM collectors.
	 * @return array Modified collectors array.
	 */
	public function register_qm_collector( $collectors ) {
		if ( class_exists( 'QM_Collector' ) ) {
			include_once WORKSHOP_DEMO_PLUGIN_DIR . 'includes/class-workshop-qm-collector.php';
			if ( class_exists( 'Workshop_QM_Collector' ) ) {
				$collectors['workshop'] = new Workshop_QM_Collector();
			}
		}
		return $collectors;
	}

	/**
	 * Register custom Query Monitor output
	 *
	 * @since 1.0.0
	 * @param array $output Existing QM output handlers.
	 * @param QM_Collectors $collectors QM collectors object.
	 * @return array Modified output array.
	 */
	public function register_qm_output( $output, $collectors ) {
		// Check if collectors is an object and has the get_collectors method
		if ( is_object( $collectors ) && method_exists( $collectors, 'get_collectors' ) ) {
			$collectors_array = $collectors->get_collectors();
			if ( isset( $collectors_array['workshop'] ) && class_exists( 'QM_Output_Html' ) ) {
				include_once WORKSHOP_DEMO_PLUGIN_DIR . 'includes/class-workshop-qm-output.php';
				if ( class_exists( 'Workshop_QM_Output' ) ) {
					$output['workshop'] = new Workshop_QM_Output( $collectors_array['workshop'] );
				}
			}
		}
		return $output;
	}

	/**
	 * Activation hook
	 */
	public static function activate() {
		// Create sample data for testing.
		self::create_sample_data();

		// Set up database tables if needed.
		self::setup_database_tables();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create sample data
	 */
	private static function create_sample_data() {
		// Create 50 test posts for demonstration.
		for ( $i = 1; $i <= 50; $i++ ) {
			$post_id = wp_insert_post(
				array(
					'post_title'   => 'Workshop Test Post ' . $i,
					'post_content' => 'This is test content for performance demonstration.',
					'post_type'    => 'workshop_test',
					'post_status'  => 'publish',
				)
			);

			if ( $post_id ) {
				// Add multiple meta values for N+1 query demonstration.
				update_post_meta( $post_id, 'workshop_meta_1', 'Value ' . $i );
				update_post_meta( $post_id, 'workshop_meta_2', 'Data ' . $i );
				update_post_meta( $post_id, 'workshop_meta_3', wp_rand( 1000, 9999 ) );
			}
		}
	}

	/**
	 * Setup database tables
	 */
	private static function setup_database_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'workshop_demo_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			log_time datetime DEFAULT CURRENT_TIMESTAMP,
			log_type varchar(50) NOT NULL,
			log_message text,
			meta_data longtext,
			PRIMARY KEY (id),
			KEY log_type (log_type),
			KEY log_time (log_time)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Deactivation hook
	 */
	public static function deactivate() {
		// Clean up transients.
		delete_transient( 'workshop_demo_cache' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'Workshop_Performance_Demo', 'get_instance' ) );

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Workshop_Performance_Demo', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Workshop_Performance_Demo', 'deactivate' ) );
