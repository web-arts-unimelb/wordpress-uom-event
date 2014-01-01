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
			$this->get_school_posts();
			//$this->import_posts();
		}

		function get_school_posts() {
			/*
			$schools = array(
				'Asia Institute',
				'Graduate School of Humanities and Social Sciences',
				'Melbourne School of Government',				
				'School of Historical and Philosophical Studies',
				'School Of Culture And Communication',	
				'School of Languages and Linguistics',
				'School of Social and Political Sciences',
			);	
			*/

			$schools = array('conference');


			array_walk($schools, '_add_encoded_space_to_name');

			foreach($schools as $school) {
				$url = $this->_build_end_point($school);
				$this->get_posts($url);
			}

		}

		function get_posts($url) {
			$data = $this->_get_event_data($url);

			$api_attr = "api-v1-entities-event-item";
			
			if(isset($data->$api_attr)) {
				// http://stackoverflow.com/questions/871422/looping-through-a-simplexml-object-or-turning-the-whole-thing-into-an-array
				foreach($data->$api_attr as $event_obj) {
					$event_id = (string)$event_obj->id;
					$event_title = (string)$event_obj->title;				
					$event_type = (string)$event_obj->type;
					
					$event_start_time = (string)$event_obj->{'start-time'};
					$event_end_time = (string)$event_obj->{'end-time'};	
					
					$event_presenter_html = "";
					foreach($event_obj->presenters as $presenter) {
						// Event may not have a presenter yet
						if(isset($presenter->presenter)) {
							$link = (string)$presenter->presenter->link;
							$title = (string)$presenter->presenter->title;
							$first_name = (string)$presenter->presenter->{'first-name'};
							$last_name = (string)$presenter->presenter->{'last-name'};

							$event_presenter_html .= '<a href="'. $link. '">'. $title. " ". $first_name. ' '. $last_name. '</a><br/>';
						}					

					}

					$event_description =  (string)$event_obj->{'description-html'} ;

					//echo "<pre>";
          echo $event_description;
          //echo "</pre>";

				}
			}	
			else {
				// No event data
				return;	
			}	


			$index = 0;

			// Force to custom post type
			$post_type = "uom_event";
			$post_author = 1;
			$post_date = date("Y-m-d H:i:s"); //"2012-05-01 23:36:03";
			$post_date_gmt = date("Y-m-d H:i:s"); //"2012-05-01 23:36:03";

			$post_content = "post content";	
			$post_title = "post title";
			$post_status = 'publish';

			// Custom fields
			$start_time = "start time";
			$end_time = 'end time';
			$event_time = 'event time';

			$this->posts[$index] = array(
				'post_type' => $post_type, 
				'post_author' => $post_author, 
				'post_date' => $post_date, 
				'post_date_gmt' => $post_date_gmt, 
	
				'post_content' => $post_content, 
				'post_title' => $post_title, 
				'post_status' => $post_status, 

				'start_time' => $start_time,
				'end_time' => $end_time,
				'event_time' => $event_time,

			); 

			$index++;

		}

		function import_posts() {
			global $wpdb;

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
					add_post_meta($post_id, 'end_time', $start_time);
					add_post_meta($post_id, 'event-time', $event_time);			
	
					// Log
					$wpdb->insert(
						'wp_uom_event_log',
						array(
							'created_date' => date("Y-m-d H:i:s"),
							'post_id' => $post_id
						)
					);
					
	
				}
			}
		}

		private function _build_end_point($school) {
			$part_get_from_tag = "http://events.unimelb.edu.au/api/v1/events/current/tagged/";
			$part_school_xml = $school. ".xml";
			$part_token = "?auth_token=dsv5n24uLUqtSyZ5Darq";
			$part_full = "&full=true"; 

			//http://events.unimelb.edu.au/api/v1/events/current/tagged/School%20Of%20Culture%20And%20Communication.xml?auth_token=dsv5n24uLUqtSyZ5Darq&full=true
			$end_point = $part_get_from_tag. $part_school_xml. $part_token. $part_full;
			return $end_point;
    }

		private function _get_event_data($url) {
			$return_data = null;

			if( ($response_xml_data = file_get_contents($url)) === false ) {
        echo "Error fetching XML\n";
      }
      else {
        libxml_use_internal_errors(true);
        $data = simplexml_load_string($response_xml_data);
        if(!$data) {
          echo "Error loading XML\n";
          foreach(libxml_get_errors() as $error) {
           echo "\t", $error->message;
          }
        }
        else {
          //test
					/*
          echo "<pre>";
          print_r($data);
          echo "</pre>";
					*/

					$return_data = $data;
        }
      }

			return $return_data;
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

function cron_add_mytime($schedules) {
	$schedules['mytime'] = array(
    'interval' => 120,
    'display' => __( 'My scheduled time' )
  );
  return $schedules;
}

// Callback function, see array_walk above
function _add_encoded_space_to_name(&$item, $key) {
	$item = str_replace(' ', '%20', $item);
}


// Set up custom post type
add_action('init', 'uom_event_cpt');

// Custom cron schedule
add_filter('cron_schedules', 'cron_add_mytime');
 
// Import xml to post
if(class_exists("xmltowp")) {
  $xmltowp_plugin = new xmltowp();
	
	$xmltowp_plugin->xmltowp_init();

	/*
  if(isset($xmltowp_plugin)) {
		register_activation_hook(__FILE__, array(&$xmltowp_plugin, 'xmltowp_init'));
		if(!wp_next_scheduled('xmlschedule_hook')) {
			wp_schedule_event(time(), 'mytime', 'xmlschedule_hook');
		}
		add_action('xmlschedule_hook', array(&$xmltowp_plugin, 'xmltowp_init'));
  }
	*/

	// Remove the scheduled event
	// http://codex.wordpress.org/Function_Reference/wp_clear_scheduled_hook
	//wp_clear_scheduled_hook('xmlschedule_hook');

}

