<?php
/**
 * Custom Query Monitor Collector for Workshop Demo
 *
 * @package workshop-performance-demo
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Workshop Query Monitor Collector
 */
class Workshop_QM_Collector extends QM_Collector {

	/**
	 * Collector ID
	 *
	 * @var string
	 */
	public $id = 'workshop';

	/**
	 * Collector name
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Workshop Demo', 'workshop-demo' );
	}

	/**
	 * Process collected data
	 */
	public function process() {
		$this->data['demo_metrics'] = array(
			'total_queries'      => $this->get_total_queries(),
			'slow_queries'       => $this->get_slow_queries(),
			'duplicate_queries'  => $this->get_duplicate_queries(),
			'memory_usage'       => $this->get_memory_usage(),
			'peak_memory'        => memory_get_peak_usage( true ),
			'execution_time'     => $this->get_execution_time(),
			'hook_count'         => $this->get_hook_count(),
			'cache_hits'         => $this->get_cache_stats(),
		);

		// Custom performance recommendations.
		$this->data['recommendations'] = $this->generate_recommendations();
	}

	/**
	 * Get total number of queries
	 *
	 * @return int
	 */
	private function get_total_queries() {
		global $wpdb;
		return isset( $wpdb->num_queries ) ? $wpdb->num_queries : 0;
	}

	/**
	 * Get slow queries (> 0.05 seconds)
	 *
	 * @return array
	 */
	private function get_slow_queries() {
		global $wpdb;
		$slow_queries = array();

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $query ) {
				if ( $query[1] > 0.05 ) {
					$slow_queries[] = array(
						'sql'    => $query[0],
						'time'   => $query[1],
						'caller' => $query[2],
					);
				}
			}
		}

		return $slow_queries;
	}

	/**
	 * Get duplicate queries
	 *
	 * @return array
	 */
	private function get_duplicate_queries() {
		global $wpdb;
		$query_counts = array();
		$duplicates = array();

		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && ! empty( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $query ) {
				$sql = $query[0];
				if ( ! isset( $query_counts[ $sql ] ) ) {
					$query_counts[ $sql ] = 0;
				}
				$query_counts[ $sql ]++;
			}

			foreach ( $query_counts as $sql => $count ) {
				if ( $count > 1 ) {
					$duplicates[] = array(
						'query' => $sql,
						'count' => $count,
					);
				}
			}
		}

		return $duplicates;
	}

	/**
	 * Get memory usage
	 *
	 * @return array
	 */
	private function get_memory_usage() {
		return array(
			'current'    => memory_get_usage( true ),
			'peak'       => memory_get_peak_usage( true ),
			'limit'      => ini_get( 'memory_limit' ),
			'percentage' => $this->calculate_memory_percentage(),
		);
	}

	/**
	 * Calculate memory usage percentage
	 *
	 * @return float
	 */
	private function calculate_memory_percentage() {
		$limit = ini_get( 'memory_limit' );
		if ( '-1' === $limit ) {
			return 0;
		}

		$limit_bytes = wp_convert_hr_to_bytes( $limit );
		$usage = memory_get_peak_usage( true );

		return ( $usage / $limit_bytes ) * 100;
	}

	/**
	 * Get execution time
	 *
	 * @return float
	 */
	private function get_execution_time() {
		if ( defined( 'WP_START_TIMESTAMP' ) ) {
			return microtime( true ) - WP_START_TIMESTAMP;
		}
		return 0;
	}

	/**
	 * Get hook count
	 *
	 * @return array
	 */
	private function get_hook_count() {
		global $wp_filter;
		$hook_count = array(
			'total'   => 0,
			'actions' => 0,
			'filters' => 0,
		);

		foreach ( $wp_filter as $hook_name => $hook ) {
			$hook_count['total'] += count( $hook->callbacks );
		}

		return $hook_count;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array
	 */
	private function get_cache_stats() {
		global $wp_object_cache;
		$stats = array(
			'hits'   => 0,
			'misses' => 0,
			'ratio'  => 0,
		);

		if ( isset( $wp_object_cache->cache_hits ) ) {
			$stats['hits'] = $wp_object_cache->cache_hits;
		}

		if ( isset( $wp_object_cache->cache_misses ) ) {
			$stats['misses'] = $wp_object_cache->cache_misses;
		}

		if ( $stats['hits'] + $stats['misses'] > 0 ) {
			$stats['ratio'] = round( ( $stats['hits'] / ( $stats['hits'] + $stats['misses'] ) ) * 100, 2 );
		}

		return $stats;
	}

	/**
	 * Generate performance recommendations
	 *
	 * @return array
	 */
	private function generate_recommendations() {
		$recommendations = array();

		// Check for too many queries.
		$total_queries = $this->get_total_queries();
		if ( $total_queries > 50 ) {
			$recommendations[] = array(
				'type'     => 'warning',
				'message'  => sprintf(
					/* translators: %d: number of queries */
					__( 'High number of database queries detected: %d. Consider implementing caching.', 'workshop-demo' ),
					$total_queries
				),
				'priority' => 'high',
			);
		}

		// Check for slow queries.
		$slow_queries = $this->get_slow_queries();
		if ( count( $slow_queries ) > 0 ) {
			$recommendations[] = array(
				'type'     => 'error',
				'message'  => sprintf(
					/* translators: %d: number of slow queries */
					__( '%d slow queries detected (>50ms). Review and optimize these queries.', 'workshop-demo' ),
					count( $slow_queries )
				),
				'priority' => 'critical',
			);
		}

		// Check memory usage.
		$memory = $this->get_memory_usage();
		if ( $memory['percentage'] > 80 ) {
			$recommendations[] = array(
				'type'     => 'warning',
				'message'  => sprintf(
					/* translators: %s: memory percentage */
					__( 'High memory usage: %s%%. Consider increasing memory limit or optimizing code.', 'workshop-demo' ),
					round( $memory['percentage'], 2 )
				),
				'priority' => 'high',
			);
		}

		// Check cache hit ratio.
		$cache_stats = $this->get_cache_stats();
		if ( $cache_stats['ratio'] < 50 && $cache_stats['hits'] + $cache_stats['misses'] > 100 ) {
			$recommendations[] = array(
				'type'     => 'info',
				'message'  => sprintf(
					/* translators: %s: cache hit ratio */
					__( 'Low cache hit ratio: %s%%. Consider implementing object caching.', 'workshop-demo' ),
					$cache_stats['ratio']
				),
				'priority' => 'medium',
			);
		}

		// Check execution time.
		$exec_time = $this->get_execution_time();
		if ( $exec_time > 2 ) {
			$recommendations[] = array(
				'type'     => 'error',
				'message'  => sprintf(
					/* translators: %s: execution time */
					__( 'Page generation took %s seconds. Target should be under 1 second.', 'workshop-demo' ),
					round( $exec_time, 2 )
				),
				'priority' => 'critical',
			);
		}

		if ( empty( $recommendations ) ) {
			$recommendations[] = array(
				'type'     => 'success',
				'message'  => __( 'No major performance issues detected. Great job!', 'workshop-demo' ),
				'priority' => 'info',
			);
		}

		return $recommendations;
	}
}
