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

// Global
global $uom_db_version;
$uom_db_version = "1.0";

if(!class_exists("xmltowp")) {
	class xmltowp {
		public static function get_instance()
    {
    	static $instance = null;
      if(null === $instance) {
      	$instance = new static();
      }

      return $instance;
    }

		/**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the `new` operator from outside of this class.
     */
    protected function __construct()
    {
    }
	
		/**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    private function __wakeup()
    {
    }

		function xmltowp_init() {
			$this->import();
		}

		function import() {
			$this->get_school_posts();
		}

		function get_school_posts() {
			$schools = array(
				'Asia Institute',
				'Graduate School of Humanities and Social Sciences',
				'Melbourne School of Government',				
				'School of Historical and Philosophical Studies',
				'School Of Culture And Communication',	
				'School of Languages and Linguistics',
				'School of Social and Political Sciences',
			);	

			array_walk($schools, '_add_encoded_space_to_name');

			foreach($schools as $school) {
				$url = $this->_build_end_point($school);
				$this->get_posts($school, $url);
			}

		}

		function get_posts($school, $url) {
			$data = $this->_get_event_data($url);

			if(isset($data->{'api-v1-entities-event-item'})) {
				global $wpdb;	

				// http://stackoverflow.com/questions/871422/looping-through-a-simplexml-object-or-turning-the-whole-thing-into-an-array
				$index = 0;
        while(isset($data->{'api-v1-entities-event-item'}[$index])) {
					$event_obj = $data->{'api-v1-entities-event-item'}[$index];
	
					$event_id = (string)$event_obj->id;
					$event_title = trim( (string)$event_obj->title );
					$event_is_public = (string)$event_obj->public;

					// If not public, continue
					if($event_is_public !== 'true') {
						$index++;
						continue;
					}

					// Check whether post already exists
					if(post_exists($event_title, '')) {
						$index++;
						continue;
					}
			
					$event_type = (string)$event_obj->type;

					// Start time					
					$event_start_time = (string)$event_obj->{'start-time'};
					$event_start_time_orig = $event_start_time;
					$event_start_time = $this->_theme_convert_date($event_start_time);

					// End time
					$event_end_time = (string)$event_obj->{'end-time'};
					$event_end_time_orig = $event_end_time;
					$event_end_time = $this->_theme_convert_date($event_end_time);

					// Start and end time
					$event_start_end_time = $this->_theme_convert_start_end_date($event_start_time, $event_end_time);	

					// Start time
					$event_start_time_with_label = $this->_theme_add_label('When', $event_start_time);

					// Legacy field for sorting
					$event_sorting_time = $this->_theme_convert_date($event_start_time_orig, 'Y-m-d'); // Ordering field

					// Presenter
					$event_presenter = "";
					foreach($event_obj->presenters as $presenter) {
						// Event may not have a presenter yet
						if(isset($presenter->presenter)) {
							$link = (string)$presenter->presenter->link;
							$title = (string)$presenter->presenter->title;
							$first_name = (string)$presenter->presenter->{'first-name'};
							$last_name = (string)$presenter->presenter->{'last-name'};

							$event_presenter .= '<a href="'. $link. '">'. $title. " ". $first_name. ' '. $last_name. '</a>';
						}					
					}
					$event_presenter = $this->_theme_add_label('Presenter', $event_presenter);					

					// Description
					$event_description = (string)$event_obj->{'description-html'};
					$event_description = $this->_theme_add_label_no_trail_br('Description', $event_description);	

					// Location
					$event_address = (string)$event_obj->location->address;
					$event_building = (string)$event_obj->location->building;
					$event_room_or_theatre = (string)$event_obj->location->{'room-or-theatre'};
					$tmp_array = array($event_room_or_theatre, $event_building, $event_address);
					$tmp_text = $this->_theme_text_inline(", ", $tmp_array);
					$event_location = $this->_theme_add_label('Where', $tmp_text);

					// Information
					$event_info_email = (string)$event_obj->information->email;
					$event_info_phone = (string)$event_obj->information->phone;			
					$event_info_url = (string)$event_obj->information->url;
					$event_info_url = $this->_theme_url($event_info_url);
					$tmp_array = array($event_info_email, $event_info_phone, $event_info_url);
					$tmp_text = $this->_theme_list($tmp_array);
					$event_info = $this->_theme_add_label_no_trail_br('Information', $tmp_text);

					// Booking
					$event_booking_email = (string)$event_obj->booking->email;
					$event_booking_phone = (string)$event_obj->booking->phone;
					$event_booking_url = (string)$event_obj->booking->url;
					$event_booking_url = $this->_theme_url($event_booking_url);
					$tmp_array = array($event_booking_email, $event_booking_phone, $event_booking_url);
          $tmp_text = $this->_theme_list($tmp_array);
					$event_booking = $this->_theme_add_label_no_trail_br('Booking', $tmp_text);

					// Link
					$event_org_link = (string)$event_obj->link;
					$event_org_link = $this->_theme_url($event_org_link, 'Original event on events.unimelb.edu.au');
					$event_org_link = $this->_theme_add_label('Link', $event_org_link);

					// Force to custom post type
      		$post_type = "uom_event";
      		$post_author = 3; // Christ strong as poster
      		$post_date = date("Y-m-d H:i:s"); //"2012-05-01 23:36:03";
      		$post_date_gmt = date("Y-m-d H:i:s"); //"2012-05-01 23:36:03";
      		$post_status = 'publish';


					// Prepare a post object
					$inserted_post = array(
						'post_type' => $post_type,
          	'post_author' => $post_author, 
          	'post_date' => $post_date, 
          	'post_date_gmt' => $post_date_gmt, 
						'post_content' => esc_html( $event_description ),
          	'post_status' => $post_status,
						'post_title' => $event_title,
					);

					// Insert
					$post_id = wp_insert_post($inserted_post);

          if(is_wp_error($post_id)) {
          	return $post_id;
					}

          if(!$post_id) {
         		return;
          }
	
					// We are safe
          // insert post meta
          add_post_meta($post_id, '_wp_page_template', 'post_uom_event.php'); // Force it to use custom post template
          add_post_meta($post_id, 'event_start_end_time', esc_html($event_start_end_time));
					add_post_meta($post_id, 'event_start_time', esc_html($event_start_time_with_label));										

          add_post_meta($post_id, 'event-time', $event_sorting_time);
          add_post_meta($post_id, 'event_location', esc_html($event_location));

          add_post_meta($post_id, 'event_presenter', esc_html($event_presenter));
          //add_post_meta($post_id, 'event_description', esc_html($event_description)); // no need
          add_post_meta($post_id, 'event_info', esc_html($event_info));
          add_post_meta($post_id, 'event_booking', esc_html($event_booking));
          add_post_meta($post_id, 'event_org_link', esc_html($event_org_link));

          // Category
          $school_cat_id = $this->_translate_uom_event_category($school);
          $event_cat_id = 5;

          // This will insert into wp_term_taxonomy
          // term_taxnonomy_id (field) -> term_id (field)-> wp_term (table) -> category name (field)
          wp_set_post_categories($post_id, array($school_cat_id, $event_cat_id));

          // Log
					/* Remove logging
          $wpdb->insert(
          	'wp_uom_event_log',
             array(
             	'created_date' => date("Y-m-d H:i:s"),
              'post_id' => $post_id
             )
          );
					*/

					$index++;
				} // End loop 

			}	
			else {
				// No event data
				return;	
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
          //test: show all data
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

		private function _theme_add_label($label, $text) {
			$html = "";
			if(!empty($text)) {

				$html = "<strong>$label</strong><br/>". $text. "<br/><br/>";
			}
			else {
				$html = "";
			}

			return $html;
		}

		private function _theme_add_label_no_trail_br($label, $text) {
			$html = "";
      if(!empty($text)) {

        $html = "<strong>$label</strong><br/>". $text;
      }
      else {
        $html = "";
      }

      return $html;
		}

		private function _theme_list($the_array) {
			$html = "";

			$the_array = array_filter($the_array, 'strlen');
			if(count($the_array) > 0) {
				$html = "<ul>";
				foreach($the_array as $element) {
					$html .= "<li>". $element. "</li>";
				}
				$html .= "</ul>";
			}
			else {
				$html = "";
			}

			return $html;
		}

		private function _theme_url($url, $url_text = "") {
			$html = "";

			if(!empty($url)) {
				if(!empty($url_text)) {
					$html .= '<a href="'. $url. '">'.  $url_text. '</a>';	
				}
				else {
					$html .= '<a href="'. $url. '">'.  $url. '</a>';
				}
			}
			else {
				$html = "";
			}
	
			return $html;
		}

		private function _theme_text_inline($glue = ", ", $the_array) {
			$html = "";
			$the_array = array_filter($the_array, 'strlen');
			$html = implode($glue, $the_array);
			
			return $html;
		}

		private function _theme_convert_date($time_text, $format = 'l, j F Y, g:i:s a') {
			$date = new DateTime($time_text);
      return $date->format($format);
		}

		private function _translate_uom_event_category($school_name) {
			$school_name = str_replace("%20", " ", $school_name);

			//test
			/*
			echo "<pre>";
			print_r($school_name);
			echo "</pre>";
			*/

			if($school_name == 'Asia Institute') {
				$category_id = 3;	
			}
			elseif($school_name == 'Graduate School of Humanities and Social Sciences') {
				$category_id = 7;
			}
			elseif($school_name == 'Melbourne School of Government') {
				$category_id = 26;
      }			
			elseif($school_name == 'School of Historical and Philosophical Studies') {
				$category_id = 11;
      }
			elseif($school_name == 'School Of Culture And Communication') {
				$category_id = 4;
      }
			elseif($school_name == 'School of Languages and Linguistics') {
				$category_id = 10;
      }
			elseif($school_name == 'School of Social and Political Sciences') {
				$category_id = 12;
      }
			else {
				$category_id = 1;
			}

			return $category_id;
		}

		private function _theme_convert_start_end_date($event_start_time, $event_end_time) {
			$html = " 
				<span><i>Start</i>: $event_start_time</span><br/>
				<span><i>End</i>: $event_end_time</span>	
			";

			$html = $this->_theme_add_label('When', $html);	
			return $html;
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
		'interval' => 21600,
    //'interval' => 60,
    'display' => __( 'My scheduled time' )
  );
  return $schedules;
}

// Callback function, see array_walk above
function _add_encoded_space_to_name(&$item, $key) {
	$item = str_replace(' ', '%20', $item);
}

// Install db
function uom_event_install() {
  global $wpdb;
  global $uom_event_db_version;

  $table_name = $wpdb->prefix . "uom_event_log";

  $sql = "CREATE TABLE $table_name (
  	id mediumint(9) NOT NULL AUTO_INCREMENT,
  	created_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  	post_id VARCHAR(55) DEFAULT '' NOT NULL,
  	UNIQUE KEY id (id)
    );
	";

   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   dbDelta($sql);

   add_option("uom_event_db_version", $uom_event_db_version );
}

function uom_event_log($file_name, $log_data) {
	$dir_path = dirname(__FILE__);
	$file_path = $dir_path. "/". $file_name;
	file_put_contents($file_path, $log_data, FILE_APPEND | LOCK_EX);
}

function my_print_r($var) {
	echo "<pre>";
	print_r($var);
	echo "</pre>";
}


// Set up custom post type
add_action('init', 'uom_event_cpt');

// Custom cron schedule
add_filter('cron_schedules', 'cron_add_mytime');

// Install uom_event db
register_activation_hook(__FILE__, 'uom_event_install');


 
// Import xml to post
if(class_exists("xmltowp")) {
  $xmltowp_plugin = xmltowp::get_instance();

	// Uncomment it if testing	
	//$xmltowp_plugin->xmltowp_init();

	// Remove the scheduled event
	//http://wordpress.org/support/topic/wp_clear_scheduled_hook-not-workinga
	/*
	$timestamp = wp_next_scheduled('xmlschedule_hook');
	wp_clear_scheduled_hook($timestamp, 'mytime', 'xmlschedule_hook');
	*/

  if(isset($xmltowp_plugin)) {
		register_activation_hook(__FILE__, array(&$xmltowp_plugin, 'xmltowp_init'));
		if(!wp_next_scheduled('xmlschedule_hook')) {
			wp_schedule_event(time(), 'mytime', 'xmlschedule_hook');
		}
		add_action('xmlschedule_hook', array(&$xmltowp_plugin, 'xmltowp_init'));
  }
}

