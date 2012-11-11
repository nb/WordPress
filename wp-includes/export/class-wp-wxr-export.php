<?php
/**
 * Represents a set of posts and other site data to be exported.
 *
 * An immutable object, which gathers all data needed for the export.
 */
class WP_WXR_Export {
	private static $defaults = array(
		'post_ids' => null,
		'post_type' => null,
		'status' => null,
	);

	private $post_ids;
	private $filters;
	private $xml_gen;

	public function __construct( $filters = array( 'all' => true ) ) {
		$this->filters = wp_parse_args( $filters, self::$defaults );
		$this->post_ids = $this->calculate_post_ids();
	}

	public function post_ids() {
		return $this->post_ids;
	}

	public function get_xml() {
		return $this->export_using_writer_class( 'WP_WXR_Returner' );
	}

	public function export_to_xml_file( $file_name ) {
		return $this->export_using_writer_class( 'WP_WXR_File_Writer', array( $file_name ) );
	}

	/**
	 * Exports the current data using a specific export writer class
	 *
	 * You should use this method only when you need to export using a
	 * custom writer. For built-in writers, please see the other public
	 * methods like get_xml(), export_to_xml_file(), etc.
	 *
	 * Example:
	 * $export = new WP_WXR_Export(…);
	 * $export->export( 'WP_WXR_CSV_Writer', array( '/home/baba/baba.csv', ';' );
	 *
	 * @param string $writer_class_name The name of the PHP class representing the writer
	 * @param mixed[] $writer_args Optional additional arguments with which to call the writer constructor
	 */
	public function export_using_writer_class( $writer_class_name, $writer_args = array() ) {
		$xml_generator = new WP_WXR_XML_Generator( $this );
		array_unshift( $writer_args, $xml_generator );
		$writer_class = new ReflectionClass( $writer_class_name );
		$writer = $writer_class->newInstanceArgs( $writer_args );
		return $this->export_using_writer( $writer );
	}

	private function export_using_writer( $writer ) {
		try {
			return $writer->export();
		} catch ( WP_WXR_Exception $e ) {
			return new WP_Error( 'wxr-error', $e->getMessage() );
		}
	}

	public function charset() {
		return get_bloginfo( 'charset' );
	}

	public function site_metadata() {
		$metadata = array(
			'name' => $this->bloginfo_rss( 'name' ),
			'url' => $this->bloginfo_rss( 'url' ),
			'language' => $this->bloginfo_rss( 'language' ),
			'description' => $this->bloginfo_rss( 'description' ),
			'pubDate' => date( 'D, d M Y H:i:s +0000' ),
			'site_url' => is_multisite()? network_home_url() : $this->bloginfo_rss( 'url' ),
			'blog_url' => $this->bloginfo_rss( 'url' ),
		);
		return $metadata;
	}

	public function wp_generator_tag() {
		return apply_filters( 'the_generator', get_the_generator( 'export' ), 'export' );
	}

	private function bloginfo_rss( $section ) {
		return apply_filters( 'bloginfo_rss', get_bloginfo_rss( $section ), $section );
	}

	private function calculate_post_ids() {
		global $wpdb;
		if ( is_array( $this->filters['post_ids'] ) ) {
			return $this->filters['post_ids'];
		}
		$join = '';
		$wheres = array();

		$wheres[] = $this->post_type_where();
		$wheres[] = $this->status_where();

		$where = implode( ' AND ', array_filter( $wheres ) );
		if ( $where ) $where = "WHERE $where";
		return $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} AS p $join $where" );
	}

	private function post_type_where() {
		global $wpdb;
		$post_types_filters = array( 'can_export' => true );
		if ( $this->filters['post_type'] ) {
			$post_types_filters += array( 'name' => $this->filters['post_type'] );
		}
		$post_types = get_post_types( $post_types_filters );
		if ( !$post_types ) {
			return 'p.post_type IS NULL';
		}
		return $this->build_IN_condition( 'p.post_type', $post_types );
	}

	private function status_where() {
		global $wpdb;
		if ( !$this->filters['status'] )
			return "p.post_status != 'auto-draft'";
		return $wpdb->prepare( 'p.post_status = %s', $this->filters['status'] );
	}

	private function build_IN_condition( $column_name, $values ) {
		global $wpdb;
		if ( !is_array( $values ) || empty( $values ) ) {
			return false;
		}
		$esses = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
		return $wpdb->prepare( "$column_name IN ($esses)", $values );
	}
}

class WP_WXR_Exception extends RuntimeException {
}
