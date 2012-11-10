<?php
/**
 * Represents a set of posts to be exported
 *
 * An immutable object, which gathers all data needed for the export.
 */
class WP_WXR_Export {
	private static $defaults = array( 'post_ids' => null, 'post_type' => null, 'post_status' => null, );

	private $post_ids;
	private $filters;
	private $xml_gen;

	public function __construct( $filters ) {
		$this->filters = wp_parse_args( $filters, self::$defaults );
		$this->post_ids = $this->calculate_post_ids();
	}

	public function post_ids() {
		return $this->post_ids;
	}

	public function get_xml() {
		return $this->export( 'WP_WXR_XML_Returner' );
	}

	public function export_to_xml_file( $file_name ) {
		return $this->export( 'WP_WXR_XML_File_Writer', $file_name );
	}

	public function export() {
		$args = func_get_args();
		$writer_class_name = array_shift( $args );
		$writer_args = $args;
		$xml_generator = new WP_WXR_XML_Generator( $this );
		array_unshift( $writer_args, $xml_generator );
		$writer_class = new ReflectionClass( $writer_class_name );
		$writer = $writer_class->newInstanceArgs( $writer_args );
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
		$wheres[] = $this->post_status_where();

		$where = implode( ' AND ', $wheres );
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

	private function post_status_where() {
		global $wpdb;
		if ( !$this->filters['post_status'] )
			return "p.post_status != 'auto-draft'";
		return $wpdb->prepare( 'p.post_status = %s', $this->filters['post_status'] );
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
