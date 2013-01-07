<?php
/*
Plugin Name: Gallery Rotator
Plugin URI: http://www.billerickson.net/
Description: Turns the WordPress Gallery into a responsive image rotator
Version: 1.0
Author: Bill Erickson
Author URI: http://www.billerickson.net
License: GPLv2
*/

class BE_Gallery_Rotator {
	var $instance,
		$shortcode_attribute = 'rotator';
	
	function __construct() {
		$this->instance =& $this;
		add_action( 'plugins_loaded', array( $this, 'init' ) );	
	}
	
	function init() {

		// Don't Update
		add_filter( 'http_request_args', array( $this, 'dont_update' ), 5, 2 );
		
		// Gallery Output
		add_filter( 'post_gallery', array( $this, 'rotator_gallery' ), 10, 2 );
		
		// Register Javascript
		add_action( 'wp_enqueue_scripts', array( $this, 'register_javascript' ) );

		// Register back end JavaScript
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_javascript' ) );

		// Output plugin JS data
		add_action( 'print_media_templates', array( $this, 'print_admin_js' ) );

	}
	
	/**
	 * Don't Update Plugin
	 * @since 1.0.0
	 * 
	 * This prevents you being prompted to update if there's a public plugin
	 * with the same name.
	 *
	 * @author Mark Jaquith
	 * @link http://markjaquith.wordpress.com/2009/12/14/excluding-your-plugin-or-theme-from-update-checks/
	 *
	 * @param array $r, request arguments
	 * @param string $url, request url
	 * @return array request arguments
	 */
	function dont_update( $r, $url ) {
		if ( 0 !== strpos( $url, 'http://api.wordpress.org/plugins/update-check' ) )
			return $r; // Not a plugin update request. Bail immediately.
		$plugins = unserialize( $r['body']['plugins'] );
		unset( $plugins->plugins[ plugin_basename( __FILE__ ) ] );
		unset( $plugins->active[ array_search( plugin_basename( __FILE__ ), $plugins->active ) ] );
		$r['body']['plugins'] = serialize( $plugins );
		return $r;
	}
	
	/**
	 * Hover Gallery
	 *
	 * @param string $output
	 * @param array $atts
	 * @return string $output
	 */
	function rotator_gallery( $output, $attr ) {
		if( !( isset( $attr[ $this->shortcode_attribute ] ) && 'true' == $attr[ $this->shortcode_attribute ] ) )
			return $output;
				
		global $post;
		extract(shortcode_atts(array(
			'order'      => 'ASC',
			'orderby'    => 'menu_order ID',
			'id'         => $post->ID,
			'itemtag'    => 'dl',
			'icontag'    => 'dt',
			'captiontag' => 'dd',
			'columns'    => 4,
			'size'       => 'thumbnail',
			'include'    => '',
			'exclude'    => ''
		), $attr));
	
		$id = intval($id);
		if ( 'RAND' == $order )
			$orderby = 'none';
	
		if ( !empty($include) ) {
			$include = preg_replace( '/[^0-9,]+/', '', $include );
			$_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
	
			$attachments = array();
			foreach ( $_attachments as $key => $val ) {
				$attachments[$val->ID] = $_attachments[$key];
			}
		} elseif ( !empty($exclude) ) {
			$exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
			$attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		} else {
			$attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
		}
	
		if ( empty($attachments) )
			return '';
	
		if ( is_feed() ) {
			$output = "\n";
			foreach ( $attachments as $att_id => $attachment )
				$output .= wp_get_attachment_link($att_id, $size, true) . "\n";
			return $output;
		}
	
		wp_enqueue_script( 'flexslider' );
		wp_enqueue_script( 'be-gallery-rotator' );
		$output = '<div class="gallery-rotator"><ul class="slides">';
		$i = 0;
		foreach( $attachments as $id => $attachment ) {
		
			$image = wp_get_attachment_image_src( $id, 'large' );
			$output .= '<li><img src="' . $image[0] . '" alt="' . get_the_title() . '" /></li>';
		}
		$output .= '</ul></div><!-- .gallery-rotator -->';
		return $output;
	
	}	
	
	/**
	 * Register Javascript
	 *
	 */
	function register_javascript() {
		// Registered Scripts
		wp_register_script( 'flexslider', plugins_url( 'lib/js/jquery.flexslider-min.js', __FILE__ ), array( 'jquery' ), '2.1', true );
		wp_register_script( 'be-gallery-rotator', plugins_url( 'lib/js/gallery-rotator.js', __FILE__ ), array( 'jquery', 'flexslider' ), '1.0', true );

		// Enqueued Styles
		wp_enqueue_style( 'be-gallery-rotator', plugins_url( 'lib/css/gallery-rotator.css', __FILE__ ) );			
	}

	/**
	 * Register Admin JavaScript
	 */
	function register_admin_javascript() {
		// This script adds the "Display as rotator" checkbox and modifies the shortcode
		wp_enqueue_script( 'be-gallery-rotator-admin', plugins_url( 'lib/js/gallery-rotator-admin.js', __FILE__ ), array( 'jquery' ), '1.0', true );
	}

	/**
	 * Output Admin JavaScript
	 */
	function print_admin_js() {
		$data = array(
			'setting_key'  => $this->shortcode_attribute,
			'setting_code' => '<label class="setting">
				<span>' . __( 'Display as Rotator' ) . '</span>
				<input type="checkbox" data-setting="' . $this->shortcode_attribute . '" />
			</label>'
		);

		$data = apply_filters( 'be_gallery_rotator_js_settings', $data );

		?>
		<script type="text/javascript">
		be_gallery_rotator = '<?php echo addslashes( json_encode( $data ) ); ?>';
		</script>
		<?php
	}
}

new BE_Gallery_Rotator;