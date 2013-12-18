<?php
   /*
   Plugin Name: Uom event 
   Plugin URI: http://arts.unimelb.edu.au 
   Description: Fetch uom events and import them as post periodly. 
   Version: 1.0
   Author: Gary 
   Author URI: test@test.com 
   */
?>

<?php

require_once(ABSPATH . '/wp-admin/includes/post.php');
require_once(ABSPATH . '/wp-admin/includes/import.php');


if(!class_exists("xmltowp")) {
	class xmltowp {
		var $posts = array ();
		var $sxml;

		function xmltowp() {
			//constructor
		}

		function xmltowp_init() {
			$this->import();
		}

		function import() {
			$this->get_posts();
			$result = $this->import_posts();
		
				
		}

		function get_posts() {
			$index = 0;

			// Force to custom post type
			$post_type = "uom_event";

			$post_author = 1;
			$post_date = "2012-05-01 23:36:03";
			$post_date_gmt = "2012-05-01 23:36:03";

			$post_content = "post content";	
			$post_title = "post title";
			$post_status = 'publish';
			$guid = "http://test.com";

			// Custom fields
			$start_time = "start time";

			$this->posts[$index] = compact('post_type', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_status', 'guid', 'start_time'); 
			$index++;

		}

		function import_posts() {
			foreach ($this->posts as $post) {
				extract($post);
				if($post_id = post_exists($post_title, $post_content, $post_date)) {
					return;
				} 
				else
				{
					$post_id = wp_insert_post($post);

					if( is_wp_error($post_id) )
						return $post_id;
					
					if (!$post_id) {
						return;
					}
					
					// We are safe
    			// insert post meta
					add_post_meta($post_id, '_wp_page_template', 'post_uom_event.php'); // Force it to use custom post template
    			add_post_meta($post_id, 'start_time', $start_time);
				}
			}
		}
	} // End class
}


function uom_event_cpt() {
	register_post_type('uom_event', 
		array(
  		'labels' => array(
    		'name' => 'uom_event',
    		'singular_name' => 'uom_event',
   		),
  		'description' => 'Custom post type for uom_event',
  		'public' => true,
  		'menu_position' => 20,
  		'supports' => array('title', 'editor', 'custom-fields')
		)
	);
}


// Set up custom post type
add_action('init', 'uom_event_cpt');

// Import xml to post
if(class_exists("xmltowp")) {
  $xmltowp_plugin = new xmltowp();

  if(isset($xmltowp_plugin)) {
		$xmltowp_plugin->xmltowp_init();	
  }
}

