<?php
abstract class WP_WXR_Base_Writer {
	protected $xml_generator;

	function __construct( $xml_generator ) {
		$this->xml_generator = $xml_generator;
	}

	public function export() {
		$this->write( $this->xml_generator->before_posts() );
		foreach( $this->xml_generator->posts() as $post_in_wxr ) {
			$this->write( $post_in_wxr );
		}
		$this->write( $this->xml_generator->after_posts() );
	}

	protected function write( $xml ) {
		echo $xml;
	}
}

class WP_WXR_XML_Over_HTTP extends WP_WXR_Base_Writer {
	private $file_name;

	function __construct( $xml_generator, $file_name ) {
		parent::__construct( $xml_generator );
		$this->file_name = $file_name;
	}

	public function export() {
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $this->file_name );
		header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
		parent::export();
	}

	protected function write( $xml ) {
		echo $xml;
	}
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
		parent::__construct( $xml_generator );
		$this->file_name = $file_name;
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
	private $f;
	private $next_file_number = 0;
	private $current_file_size = 0;

	function __construct( $xml_generator, $destination_directory, $filename_template, $max_file_size = null ) {
		parent::__construct( $xml_generator );
		$this->max_file_size = is_null( $max_file_size ) ? 15 * MB_IN_BYTES : $max_file_size;
		$this->destination_directory = $destination_directory;
		$this->filename_template = $filename_template;
		$this->before_posts_xml = $this->xml_generator->before_posts();
		$this->after_posts_xml = $this->xml_generator->after_posts();
	}

	public function export() {
		$this->start_new_file();
		foreach( $this->xml_generator->posts() as $post_xml ) {
			if ( $this->current_file_size + strlen( $post_xml ) > $this->max_file_size ) {
				$this->start_new_file();
			}
			$this->write( $post_xml );
		}
		$this->close_current_file();
	}

	protected function write( $xml ) {
		$res = fwrite( $this->f, $xml);
		if ( false === $res ) {
			throw new WP_WXR_Exception( __( 'WXR Export: error writing to export file.' ) );
		}
		$this->current_file_size += strlen( $xml );
	}

	private function start_new_file() {
		if ( $this->f ) {
			$this->close_current_file();
		}
		$file_path = $this->next_file_path();
		$this->f = fopen( $file_path, 'w' );
		if ( !$this->f ) {
			throw new WP_WXR_Exception( sprintf( __( 'WXR Export: error opening %s for writing.' ), $file_path ) );
		}
		$this->current_file_size = 0;
		$this->write( $this->before_posts_xml );
	}

	private function close_current_file() {
		if ( !$this->f ) {
			return;
		}
		$this->write( $this->after_posts_xml );
		fclose( $this->f );
	}

	private function next_file_name() {
		$next_file_name = sprintf( $this->filename_template, $this->next_file_number );
		$this->next_file_number++;
		return $next_file_name;
	}

	private function next_file_path() {
		return untrailingslashit( $this->destination_directory ) . DIRECTORY_SEPARATOR . $this->next_file_name();
	}

}
