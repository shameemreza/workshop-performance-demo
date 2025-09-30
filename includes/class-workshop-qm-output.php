<?php
/**
 * Custom Query Monitor Output for Workshop Performance Demo
 *
 * @package Workshop_Performance_Demo
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output class for Workshop Query Monitor panel
 *
 * @extends QM_Output_Html
 */
class Workshop_QM_Output extends QM_Output_Html {

	/**
	 * Constructor
	 *
	 * @param QM_Collector $collector The collector instance.
	 */
	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 110 );
	}

	/**
	 * Get the name for this output
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Workshop Demo', 'workshop-demo' );
	}

	/**
	 * Output the panel content
	 */
	public function output() {
		$data = $this->collector->get_data();

		if ( empty( $data ) ) {
			return;
		}

		echo '<div class="qm" id="' . esc_attr( $this->collector->id() ) . '">';
		echo '<table>';
		
		// Performance Issues Section
		if ( ! empty( $data['performance_issues'] ) ) {
			echo '<thead>';
			echo '<tr>';
			echo '<th colspan="3">' . esc_html__( 'Detected Performance Issues', 'workshop-demo' ) . '</th>';
			echo '</tr>';
			echo '<tr>';
			echo '<th>' . esc_html__( 'Issue Type', 'workshop-demo' ) . '</th>';
			echo '<th>' . esc_html__( 'Description', 'workshop-demo' ) . '</th>';
			echo '<th>' . esc_html__( 'Impact', 'workshop-demo' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			
			foreach ( $data['performance_issues'] as $issue ) {
				$class = '';
				if ( 'high' === $issue['severity'] ) {
					$class = 'qm-warn';
				}
				
				echo '<tr class="' . esc_attr( $class ) . '">';
				echo '<td>' . esc_html( $issue['type'] ) . '</td>';
				echo '<td>' . esc_html( $issue['description'] ) . '</td>';
				echo '<td>' . esc_html( $issue['impact'] ) . '</td>';
				echo '</tr>';
			}
			
			echo '</tbody>';
		}
		
		// Optimization Suggestions Section
		if ( ! empty( $data['suggestions'] ) ) {
			echo '<thead>';
			echo '<tr>';
			echo '<th colspan="2">' . esc_html__( 'Optimization Suggestions', 'workshop-demo' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			
			foreach ( $data['suggestions'] as $suggestion ) {
				echo '<tr>';
				echo '<td class="qm-nowrap">' . esc_html( $suggestion['area'] ) . '</td>';
				echo '<td>';
				echo esc_html( $suggestion['message'] );
				if ( ! empty( $suggestion['code'] ) ) {
					echo '<pre class="qm-pre-wrap"><code>' . esc_html( $suggestion['code'] ) . '</code></pre>';
				}
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody>';
		}
		
		// Demo Status Section
		if ( ! empty( $data['demo_status'] ) ) {
			echo '<thead>';
			echo '<tr>';
			echo '<th colspan="2">' . esc_html__( 'Active Demo Scenarios', 'workshop-demo' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			
			foreach ( $data['demo_status'] as $demo => $status ) {
				$status_text = $status ? __( 'Active', 'workshop-demo' ) : __( 'Inactive', 'workshop-demo' );
				$class = $status ? 'qm-warn' : '';
				
				echo '<tr class="' . esc_attr( $class ) . '">';
				echo '<td>' . esc_html( $demo ) . '</td>';
				echo '<td>' . esc_html( $status_text ) . '</td>';
				echo '</tr>';
			}
			
			echo '</tbody>';
		}
		
		// Frontend Conflicts Section
		if ( ! empty( $data['frontend_conflicts'] ) ) {
			echo '<thead>';
			echo '<tr>';
			echo '<th colspan="3">' . esc_html__( 'Frontend Conflicts Detected', 'workshop-demo' ) . '</th>';
			echo '</tr>';
			echo '<tr>';
			echo '<th>' . esc_html__( 'Component', 'workshop-demo' ) . '</th>';
			echo '<th>' . esc_html__( 'Conflict', 'workshop-demo' ) . '</th>';
			echo '<th>' . esc_html__( 'Resolution', 'workshop-demo' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			
			foreach ( $data['frontend_conflicts'] as $conflict ) {
				echo '<tr class="qm-warn">';
				echo '<td>' . esc_html( $conflict['component'] ) . '</td>';
				echo '<td>' . esc_html( $conflict['description'] ) . '</td>';
				echo '<td>' . esc_html( $conflict['resolution'] ) . '</td>';
				echo '</tr>';
			}
			
			echo '</tbody>';
		}
		
		// Statistics Section
		if ( ! empty( $data['stats'] ) ) {
			echo '<thead>';
			echo '<tr>';
			echo '<th colspan="2">' . esc_html__( 'Performance Statistics', 'workshop-demo' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			
			foreach ( $data['stats'] as $stat => $value ) {
				echo '<tr>';
				echo '<td>' . esc_html( $stat ) . '</td>';
				echo '<td>' . esc_html( $value ) . '</td>';
				echo '</tr>';
			}
			
			echo '</tbody>';
		}
		
		echo '</table>';
		echo '</div>';
	}

	/**
	 * Add admin menu item
	 *
	 * @param array $menu The existing menu.
	 * @return array
	 */
	public function admin_menu( array $menu ) {
		$menu['workshop'] = $this->menu( array(
			'title' => esc_html__( 'Workshop Demo', 'workshop-demo' ),
			'id'    => 'query-monitor-workshop',
		) );
		
		return $menu;
	}
}
