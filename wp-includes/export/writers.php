<?php
abstract class WP_WXR_Base_Writer {
	protected $xml_generator;

	function __construct( $xml_generator ) {
		$this->xml_generator = $xml_generator;
	}

	public function export() {
		$this->export_before_posts();
		$this->export_posts();
		$this->export_after_posts();
	}

	protected function export_before_posts() {
		$this->write( $this->xml_generator->header() );
		$this->write( $this->xml_generator->site_metadata() );
		$this->write( $this->xml_generator->authors() );
		$this->write( $this->xml_generator->categories() );
		$this->write( $this->xml_generator->tags() );
		$this->write( $this->xml_generator->nav_menu_terms() );
		$this->write( $this->xml_generator->other_terms() );
	}

	protected function export_posts() {
		foreach( $this->xml_generator->posts() as $post_in_wxr ) {
			$this->write( $post_in_wxr );
		}
	}

	protected function export_after_posts() {
		$this->write( $this->xml_generator->footer() );
	}

	abstract protected function write( $xml );
}

class WP_WXR_Returner extends WP_WXR_Base_Writer {
	private $result = '';

	public function export() {
		$this->private = '';
		parent::export();
		return $this->result;
	}
	protected function write( $xml ) {
		$this->result .= $xml;
	}
}

class WP_WXR_File_Writer extends WP_WXR_Base_Writer {
	private $f;
	private $file_name;

	public function __construct( $xml_generator, $file_name ) {
		$this->file_name = $file_name;
		parent::__construct( $xml_generator );
	}

	public function export() {
		$this->f = fopen( $this->file_name, 'w' );
		if ( !$this->f ) {
			throw new WP_WXR_Exception( sprintf( __( 'WXR Export: error opening %s for writing.' ), $this->ile_name ) );
		}
		parent::export();
		fclose( $this->f );
	}

	protected function write( $xml ) {
		$res = fwrite( $this->f, $xml);
		if ( false === $res ) {
			throw new WP_WXR_Exception( __( 'WXR Export: error writing to export file.' ) );
		}
	}
}

class WP_WXR_Split_Files_Writer extends WP_WXR_Base_Writer {
	private $result = '';

	function __construct( $export, $destination_directory, $filename_template, $max_file_size_in_bytes = null ) {
		$this->max_file_size_in_bytes = is_null( $max_file_size_in_bytes ) ? 15 * MB_IN_BYTES : $max_file_size_in_bytes;
	}

	private function export_() {
		$before_posts = $this->get_xml_from_method( 'export_before_posts' );
		$after_posts = $this->get_xml_from_method( 'export_after_posts' );
		$size_of_non_posts = strlen( $before_posts ) + strlen( $after_posts );
		$wxr = $before_posts;
		foreach( $this->xml_generator->posts() as $post ) {
			$post_wxr = $this->xml_generator->post( $post );
			if ( strlen( $post_wxr ) + $size_of_non_posts > $this->max_file_size_in_bytes ) {
				$this->write_next_file( $wxr . $after_posts );
				$wxr = '';
			}
			$wxr .= $post_wxr;
		}
		$this->write_next_file( $wxr . $after_posts );
	}

	private function get_xml_from_method( $method_name ) {
		$this->result = '';
		$this->accumulate = true;
		$this->$method_name();
		$this->accumulate = false;
	}

	function write( $s ) {
		if ( $this->accumulate ) {
			$this->result = $s;
		} else {
			fwrite( $this->f, $s );
		}
	}
}
