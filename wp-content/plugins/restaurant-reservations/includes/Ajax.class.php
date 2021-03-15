<?php
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'rtbAJAX' ) ) {
	/**
	 * Class to handle AJAX date interactions for Restaurant Reservations
	 *
	 * @since 2.0.0
	 */
	class rtbAJAX {

		/**
		 * The year of the booking date we're getting timeslots for
		 * @since 2.0.0
		 */
		public $year;

		/**
		 * The month of the booking date we're getting timeslots for
		 * @since 2.0.0
		 */
		public $month;

		/**
		 * The day of the booking date we're getting timeslots for
		 * @since 2.0.0
		 */
		public $day;

		/**
		 * The time of the booking we're getting timeslots for
		 * @since 2.1.5
		 */
		public $time;

		/**
		 * The party size we're looking to find valid tables for
		 * @since 2.1.7
		 */
		public $party;

		public function __construct() {

			add_action( 'wp_ajax_rtb_get_available_time_slots', array( $this, 'get_time_slots' ) );
			add_action( 'wp_ajax_nopriv_rtb_get_available_time_slots', array( $this, 'get_time_slots' ) );

			add_action( 'wp_ajax_rtb_find_reservations', array( $this, 'get_reservations' ) );
			add_action( 'wp_ajax_nopriv_rtb_find_reservations', array( $this, 'get_reservations' ) );

			add_action( 'wp_ajax_rtb_cancel_reservations', array( $this, 'cancel_reservation' ), 10, 0 );
			add_action( 'wp_ajax_nopriv_rtb_cancel_reservations', array( $this, 'cancel_reservation' ), 10, 0 );

			add_action( 'wp_ajax_rtb_get_available_party_size', array( $this, 'get_available_party_size' ) );
			add_action( 'wp_ajax_nopriv_rtb_get_available_party_size', array( $this, 'get_available_party_size' ) );

			add_action( 'wp_ajax_rtb_get_available_tables', array( $this, 'get_available_tables' ) );
			add_action( 'wp_ajax_nopriv_rtb_get_available_tables', array( $this, 'get_available_tables' ) );

			// add_action( 'admin_init', array( $this, 'get_available_tables' ) );
		}

		/**
		 * Get reservations that are associated with the email address that was sent
		 * @since 2.1.0
		 */
		public function get_reservations() {
			global $wpdb;

			$email = isset($_POST['booking_email']) ? sanitize_email( $_POST['booking_email'] ) : '';

			if ( ! $email ) {
				wp_send_json_error(
					array(
						'error' => 'noemail',
						'msg' => __( 'The email you entered is not valid.', 'restaurant-reservations' ),
					)
				);
			}

			require_once( RTB_PLUGIN_DIR . '/includes/Booking.class.php' );

			$bookings = array();
			$booking_ids = $wpdb->get_results($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='rtb' AND meta_value LIKE %s", '%' . sanitize_email( $email ) . '%'));
			foreach ( $booking_ids as $booking_id ) {
				$booking = new rtbBooking();
				if ( $booking->load_post( $booking_id->post_id ) ) {
					$booking_date = (new DateTime($booking->date, wp_timezone()))->format('U');
					if ( ( $booking->post_status == 'pending' or $booking->post_status == 'confirmed' ) and time() < $booking_date ) {
						$bookings[] = array(
							'ID' 		=> $booking->ID,
							'email' 	=> $booking->email,
							'datetime' 	=> $booking->format_date( $booking->date ),
							'party'		=> $booking->party
						);
					}
				}
			}

			if ( ! empty($bookings) ) {
				wp_send_json_success(
					array(
						'bookings' => $bookings
					)
				);
			}
			else {
				wp_send_json_error(
					array(
						'error' => 'nobookings',
						'msg' => __( 'No bookings were found for the email address you entered.', 'restaurant-reservations' ),
					)
				);
			}

			die();
		}

		/**
		 * Cancel a reservation based on its ID, with the email address used for confirmation
		 * @since 2.1.0
		 */
		public function cancel_reservation( $ajax = true ) {
			global $rtb_controller; 

			$cancelled_redirect = $rtb_controller->settings->get_setting( 'cancelled-redirect-page' );

			$booking_id = isset($_REQUEST['booking_id']) ? absint( $_REQUEST['booking_id'] ) : '';
			$booking_email = isset($_REQUEST['booking_email']) ? sanitize_email( $_REQUEST['booking_email'] ) : '';

			require_once( RTB_PLUGIN_DIR . '/includes/Booking.class.php' );

			$success = false;

			$booking = new rtbBooking();
			if ( $booking->load_post( $booking_id ) ) {
				if ( $booking_email == $booking->email ) {
					wp_update_post( array( 'ID' => $booking->ID, 'post_status' => 'cancelled' ) );

					$success = true;
				}
				else {
					wp_send_json_error(
						array(
							'error' => 'invalidemail',
							'msg' => __( 'No booking matches the information that was sent.', 'restaurant-reservations' ),
						)
					);
				}
			}
			else {
				wp_send_json_error(
					array(
						'error' => 'invalidid',
						'msg' => __( 'No booking matches the information that was sent.', 'restaurant-reservations' ),
					)
				);
			}

			if ( $ajax ) { 
				if ( $success ) {
					
					$response = array( 'booking_id' => $booking_id );
					
					if( '' != $cancelled_redirect ) {
						$response['cancelled_redirect'] = $cancelled_redirect;
					}
					
					wp_send_json_success( $response );
				}
				else {
					wp_send_json_error(
						array(
							'error' => 'unknown',
							'msg' => __( 'Unkown error. Please try again', 'restaurant-reservations' ),
						)
					);
				}
			}
			else {
				$redirect_url = '';

				if( '' != $cancelled_redirect && $success ) {
					$redirect_url = $cancelled_redirect;
				}
				else {
					$booking_page_id = $rtb_controller->settings->get_setting( 'booking-page' );
					$booking_page_url = get_permalink( $booking_page_id );

					$redirect_url = add_query_arg(
						array(
							'bookingCancelled' => $success ? 'success' : 'fail'
						),
						$booking_page_url
					);
				}

				header( 'location:' . $redirect_url );
			}
		}

		/**
		 * Get available timeslots when "Max Reservations" or "Max People" is enabled
		 * @since 2.0.0
		 */
		public function get_time_slots() {
			global $rtb_controller;

			$min_party_size = $rtb_controller->settings->get_setting( 'party-size-min' );

			$max_reservations_setting = $rtb_controller->settings->get_setting( 'rtb-max-tables-count' );
			$max_reservations = substr( $max_reservations_setting, 0, strpos( $max_reservations_setting, '_' ) );

			$max_people_setting = $rtb_controller->settings->get_setting( 'rtb-max-people-count' );
			$max_people = substr( $max_people_setting, 0, strpos( $max_people_setting, '_' ) );
			
			$this->year = sanitize_text_field( $_POST['year'] );
			$this->month = sanitize_text_field( $_POST['month'] );
			$this->day = sanitize_text_field( $_POST['day'] );
			
			$dining_block_setting = $rtb_controller->settings->get_setting( 'rtb-dining-block-length' );
			$dining_block = substr( $dining_block_setting, 0, strpos( $dining_block_setting, '_' ) );
			$dining_block_seconds = ( $dining_block * 60 - 1 ); // Take 1 second off, to avoid bookings that start or end exactly at the beginning of a booking block
			
			// Get opening/closing times for this particular day
			$hours = $this->get_opening_hours();

			// var_dump($hours);
			
			// If the restaurant is closed that day, return false
			if ( ! $hours ) { echo $hours; die(); }
			
			$args = array(
				'posts_per_page' => -1,
				'date_range' => 'dates',
				'start_date' => $this->year . '-' . $this->month . '-' . $this->day,
				'end_date' => $this->year . '-' . $this->month . '-' . $this->day
			);
			
			require_once( RTB_PLUGIN_DIR . '/includes/Query.class.php' );
			$query = new rtbQuery( $args );
			$query->prepare_args();
				
			// Get all current bookings sorted by date
			$bookings = $query->get_bookings();
			
			// Go through all current booking times and figure out when we're at or above the max
			$blocked = false;
			$blocked_times = array();
			$current_times = array();
			$party_sizes = array();
			if ($max_reservations != 'undefined' and $max_reservations != 0) {
				foreach ( $bookings as $key => $booking ) {
					// Convert booking date to seconds from UNIX
					$booking_time = strtotime($booking->date);
					$current_times[] = $booking_time;
				
					while ( sizeOf( $current_times ) > 0 and reset( $current_times ) < $booking_time - $dining_block_seconds ) { 
						//save the time to know when the blocking potentially ends
						$removed_time = reset( $current_times );

						// remove the expired time
						array_shift( $current_times ); 

						// remove the block if we've dropped below the max reservation
						if ( $blocked and sizeOf( $current_times ) <= $max_reservations ) {
							$blocked = false;
							$blocked_times[] = $removed_time + $dining_block_seconds;
						}
					}
				
					// Check if we're at or above the maximum number of reservations
					if ( ! $blocked and sizeOf( $current_times ) >= $max_reservations ) {
						$blocked = true;
						$blocked_times[] = $booking_time - $dining_block_seconds;
					} 
				}
			} else if ( $max_people != 'undefined' and $max_people != 0 ) {
				foreach ( $bookings as $key => $booking ) {
					// Convert booking date to seconds from UNIX
					$booking_time = strtotime($booking->date);
					$current_times[] = $booking_time;
					$party_sizes[] = (int) $booking->party;
					
					while ( sizeOf( $current_times ) > 0 and reset( $current_times ) < ( $booking_time - $dining_block_seconds ) ) { 
						//save the time to know when the blocking potentially ends
						$removed_time = reset( $current_times );

						// remove the expired time and party size
						array_shift( $current_times ); 
						array_shift( $party_sizes ); 

						// remove the block if we've dropped below the max people count - min party size
						if ( $blocked and array_sum($party_sizes) <= ( $max_people - $min_party_size ) ) {
							$blocked = false;
							$blocked_times[] = $removed_time + $dining_block_seconds;
						}
					}
					
					// Check if we're above the maximum number of people - min party size
					if ( ! $blocked and array_sum($party_sizes) > ( $max_people - $min_party_size ) ) {
						$blocked = true;
						$blocked_times[] = $booking_time - $dining_block_seconds;
					} 
				}
			}
			
			if ( $blocked ) { $blocked_times[] = end( $current_times ) + $dining_block_seconds; }
			
			$combined_times = array_merge( $blocked_times, $hours );
			sort( $combined_times );
			
			//Go through all of times to determine when the restaurant is open and not blocked
			$open = false;
			$blocked = false;
			$valid_times = array(); 
			foreach ( $combined_times as $time ) {
				if ( in_array( $time, $blocked_times ) ) {
					if ( ! $blocked ) {
						$blocked = true;
						if ( $open ) { 
							$valid_times[] = (object) array( 'from' => $this->format_pickadate_time( $open_time ), 'to' => $this->format_pickadate_time( $time ), 'inverted' => true );
						}
					}
					else {
						$blocked = false;
						if ( $open ) { $open_time = $time; }
					}
				}
				else {
					if ( ! $open ) {
						$open = true;
						if ( ! $blocked ) { $open_time = $time; }
					}
					else {
						$open = false;
						if ( ! $blocked ) { $valid_times[] = (object) array( 'from' => $this->format_pickadate_time( $open_time ), 'to' => $this->format_pickadate_time( $time ), 'inverted' => true ); }
					}
				}
			}
			
			echo json_encode( $valid_times );
			
			die();
		}

		public function get_opening_hours() {
			global $rtb_controller;

			$schedule_closed = is_array( $rtb_controller->settings->get_setting( 'schedule-closed' ) ) ? $rtb_controller->settings->get_setting( 'schedule-closed' ) : array();

			$valid_times = array();

			// Check if this date is an exception to the rules
			if ( $schedule_closed !== 'undefined' ) {

				foreach ( $schedule_closed as $closing ) {
					$time = strtotime( $closing['date'] );

					if ( date( 'Y', $time ) == $this->year &&
							date( 'm', $time ) == $this->month &&
							date( 'd', $time ) == $this->day
							) {

						// Closed all day
						if ( $closing['time'] == 'undefined' ) {
							return false;
						}

						if ( $closing['time']['start'] !== 'undefined' ) {
							$open_time = strtotime( $closing['date'] . ' ' . $closing['time']['start'] );
						} else {
							$open_time = strtotime( $closing['date'] ); // Start of the day
						}

						if ( $closing['time']['end'] !== 'undefined' ) {
							$close_time = strtotime( $closing['date'] . ' ' . $closing['time']['end'] );
						} else {
							$close_time = strtotime( $closing['date'] . ' 23:59:59' ); // End of the day
						}

						$open_time = $this->get_earliest_time( $open_time );

						if ( $open_time <= $close_time ) {
							$valid_times[] = $open_time;
							$valid_times[] = $close_time;
						}
					}
				}

				// Exit early if this date is an exception
				if ( isset( $open_time ) ) {
					return $valid_times;
				}
			}

			$schedule_open = $rtb_controller->settings->get_setting( 'schedule-open' );

			// Get any rules which apply to this weekday
			if ( $schedule_open != 'undefined' ) {

				$day_of_week =  strtolower( date( 'l', strtotime( $this->year . '-' . $this->month . '-' . $this->day . ' 1:00:00' ) ) );

				foreach ( $schedule_open as $opening ) {

					if ( $opening['weekdays'] !== 'undefined' ) {
						foreach ( $opening['weekdays'] as $weekday => $value ) {
							if ( $weekday == $day_of_week ) {

								// Closed all day
								if ( $opening->time == 'undefined' ) {
									return false;
								}

								if ( $opening['time']['start'] !== 'undefined' ) {
									$open_time = strtotime( $this->year . '-' . $this->month . '-' . $this->day . ' ' . $opening['time']['start'] );
								} else {
									$open_time = strtotime( $this->year . '-' . $this->month . '-' . $this->day );
								}

								if ( $opening['time']['end'] !== 'undefined' ) {
									$close_time = strtotime( $this->year . '-' . $this->month . '-' . $this->day . ' ' . $opening['time']['end'] );
								} else {
									$close_time = strtotime( $this->year . '-' . $this->month . '-' . $this->day . ' 23:59:59' ); // End of the day
								}

								$open_time = $this->get_earliest_time( $open_time );

								if ( $open_time <= $close_time ) {
									$valid_times[] = $open_time;
									$valid_times[] = $close_time;
								}
							}
						}
					}
				}

				// Pass any valid times located
				if ( sizeOf( $valid_times ) >= 1 ) {
					return $valid_times;
				}
			}

			return false;
		}

		public function get_earliest_time( $open_time ) {
			global $rtb_controller;

			// Only make adjustments for current day selections
			if ( date( 'y-m-d', strtotime( $this->year . '-' . $this->month . '-' . $this->day ) ) !== date( 'y-m-d' ) ) {
				return $open_time;
			}

			$late_bookings = ( is_admin() && current_user_can( 'manage_bookings' ) ) ? '' : $rtb_controller->settings->get_setting( 'late-bookings' );
			
			$timezone = wp_timezone(); 
			$offset = $timezone->getOffset( new DateTime );

			$open_time = time() + $offset > $open_time ? time() + $offset : $open_time;

			if ( $late_bookings === 'number' && $late_bookings % 1 === 0 ) {
				if ( time() + $late_bookings * 60 > $open_time ) {
					$open_time = time() + $late_bookings;
				}
			}
			
			return $open_time;
		}		
		
		/**
		 * Get number of seats remaining avilable to be booked
		 * @since 2.1.5
		 */
		public function get_available_party_size() {
			global $rtb_controller;

			$max_people_setting = $rtb_controller->settings->get_setting( 'rtb-max-people-count' );
			$max_people = substr( $max_people_setting, 0, strpos( $max_people_setting, '_' ) );
			
			$this->year = sanitize_text_field( $_POST['year'] );
			$this->month = sanitize_text_field( $_POST['month'] );
			$this->day = sanitize_text_field( $_POST['day'] );
			$this->time = sanitize_text_field( $_POST['time'] );

			$dining_block_setting = $rtb_controller->settings->get_setting( 'rtb-dining-block-length' );
			$dining_block = substr( $dining_block_setting, 0, strpos( $dining_block_setting, '_' ) );
			$dining_block_seconds = ( $dining_block * 60 - 1 ); // Take 1 second off, to avoid bookings that start or end exactly at the beginning of a booking block
			
			// Get opening/closing times for this particular day
			$hours = $this->get_opening_hours();
			
			// If the restaurant is closed that day, return false
			if ( ! $hours ) { die(); }

			// If no time is selected, return false
			if ( ! $this->time ) { die(); }
			
			$args = array(
				'posts_per_page' => -1,
				'date_range' => 'dates',
				'start_date' => $this->year . '-' . $this->month . '-' . $this->day,
				'end_date' => $this->year . '-' . $this->month . '-' . $this->day
			);
			
			require_once( RTB_PLUGIN_DIR . '/includes/Query.class.php' );
			$query = new rtbQuery( $args );
			$query->prepare_args();
				
			// Get all current bookings sorted by date
			$bookings = $query->get_bookings();

			$selected_date_time = strtotime($this->year . '-' . $this->month . '-' . $this->day . ' ' . $this->time);
			$selected_date_time_start = $selected_date_time - $dining_block_seconds;
			$selected_date_time_end = $selected_date_time + $dining_block_seconds;
			$party_sizes = [];

			if ($max_people != 'undefined' and $max_people != 0) {

				$max_time_size = 0;
				$current_times = array();
				$party_sizes = array();

				// Go through all current booking and collect the total party size
				foreach ( $bookings as $key => $booking ) {

					// Convert booking date to seconds from UNIX
					$booking_time = strtotime($booking->date);
					
					// Ignore bookings outside of our time range
					if ($booking_time < $selected_date_time_start or $booking_time > $selected_date_time_end) { continue; }
					
					$current_times[] = $booking_time;
					$party_sizes[] = (int) $booking->party;
					
					while ( sizeOf( $current_times ) > 0 and reset( $current_times ) < $booking_time - $dining_block_seconds ) { 
						//save the time to know when the blocking potentially ends
						$removed_time = reset( $current_times );

						// remove the expired time and party size
						array_shift( $current_times ); 
						array_shift( $party_sizes ); 
					}
					
					$max_time_size = max( $max_time_size, array_sum( $party_sizes ) );
				}

				$response = (object) array( 'available_spots' => $max_people - $max_time_size);

				echo json_encode($response);
				
				die();
			} else {
				return false;
			}
		}

		/**
		 * Get tables available to be booked at a specific time and party size
		 * @since 2.1.7
		 */
		public function get_available_tables() {
			global $rtb_controller;

			$tables = $rtb_controller->settings->get_sorted_tables();

			$this->booking_id 	= isset( $_POST['booking_id'] ) ? intval( $_POST['booking_id'] ) : 0;
			$this->year 		= isset( $_POST['year'] ) ? sanitize_text_field( $_POST['year'] ) : false;
			$this->month 		= isset( $_POST['month'] ) ? sanitize_text_field( $_POST['month'] ) : false;
			$this->day 			= isset( $_POST['day'] ) ? sanitize_text_field( $_POST['day'] ) : false;
			$this->time 		= isset( $_POST['time'] ) ? sanitize_text_field( $_POST['time'] ) : false;
			$this->party 		= isset( $_POST['party'] ) ? sanitize_text_field( $_POST['party'] ) : false;

			/*$this->year = 2020;
			$this->month = 06;
			$this->day = 12;
			$this->time = '02:15 PM';
			$this->party = 12;*/

			if ( ! isset( $this->year ) or ! isset( $this->month ) or ! isset( $this->day ) or ! isset( $this->time ) ) { return false; }

			$datetime = strtotime( $this->year . '-' . $this->month . '-' . $this->day . ' ' . $this->time );

			$valid_tables = rtb_get_valid_tables( $datetime );

			if ( $this->booking_id ) {

				require_once( RTB_PLUGIN_DIR . '/includes/Booking.class.php' );
				
				$current_booking = new rtbBooking();
				$current_booking->load_post( $this->booking_id );

				if ( $current_booking->table ) { $valid_tables = array_merge( $valid_tables, $current_booking->table ); }
			}

			if ( isset( $this->party ) ) { 

				$possible_combinations = array();
				foreach ( $valid_tables as $valid_table ) {

					// If the party size is between the min and max for the table, great
					if ( $tables[ $valid_table ]->min_people <= $this->party and $tables[ $valid_table ]->max_people >= $this->party ) {
						
						$possible_combinations[] = $valid_table;
					}
					// If the party is above the minimum for the table, look to see if combinations could work
					elseif ( $tables[ $valid_table ]->min_people <= $this->party ) {

						$combination = $this->get_combinations_chain( $tables, $valid_tables, $valid_table, $tables[ $valid_table ]->max_people, $this->party );

						if ( $combination ) { 
							$possible_combinations[] = $combination; 
						}
					}

					$return_tables = $this->format_tables( $possible_combinations );
				}
			}
			else {
				$return_tables = $this->format_tables( $valid_tables );
			}

			$selected_table = ( isset( $current_booking ) and $current_booking->table ) ? implode(',', $current_booking->table ) : -1;

			$response = (object) array( 'available_tables' => $return_tables, 'selected_table' => $selected_table );

			echo json_encode($response);

			die();
		} 

		/**
		 * Recursively go through table combinations to find one that has enough seats
		 * @since 2.1.7
		 */
		public function get_combinations_chain( $tables, $valid_tables, $table_chain, $current_size, $needed_size ) {

			$current_table = substr( $table_chain, strrpos($table_chain, ',') ? strrpos($table_chain, ',') + 1 : 0 );

			if ( ! $tables[ $current_table ]->combinations ) { return false; }

			$possible_tables = explode( ',', $tables[ $current_table ]->combinations );

			foreach ( $possible_tables as $possible_table ) {

				// Only search larger table numbers to avoid going over the same combinations multiple times
				if ( $possible_table < $current_table ) { continue; }

				// If the table has already been booked, continue
				if ( !in_array( $possible_table, $valid_tables) ) { continue; }

				// If the table can hold the group on its own, continue
				if ( $tables[ $possible_table ]->max_people >= $needed_size ) { continue; }

				$current_size += $tables[ $possible_table ]->max_people;
				$table_chain .= ',' . $possible_table;

				if ( $current_size >= $needed_size ) { return $table_chain; }
				else {
					// Keep going to see if we can add more tables to make it work
					$table_chain = $this->get_combinations_chain( $tables, $valid_tables, $table_chain, $current_size, $needed_size );

					if ( $table_chain ) { return $table_chain; }
				}
			}	

			//no viable combination found
			return false;					
		}

		/**
		 * Format the tables available to be booked as number(s)_string => human_value pairs
		 * @since 2.1.7
		 */
		public function format_tables ( $table_numbers ) {
			global $rtb_controller;

			$formatted_tables = array();

			$tables = json_decode( html_entity_decode( $rtb_controller->settings->get_setting( 'rtb-tables' ) ) );
			$tables = is_array( $tables ) ? $tables : array();

			foreach ( $table_numbers as $table_number ) {

				$table_parts = explode( ',', $table_number );

				$table_values = array(
					'numbers' 		=> '',
					'min_people'	=> 0,
					'max_people'	=> 0
				);

				foreach ( $tables as $table ) {
					if ( in_array($table->number, $table_parts) ) {
						$table_values['numbers'] .= ( strlen( $table_values['numbers'] ) ? ', ' : '' ) . $table->number;
						$table_values['min_people'] +=  $table->min_people;
						$table_values['max_people'] +=  $table->max_people;

						if ( ! isset( $section_name ) ) { $section_name = $this->get_section_name( $table->section ); }
					}
				}

				$formatted_tables[ $table_values['numbers'] ] = $table_values['numbers'] . ' - ' . $section_name . ' (min. ' . $table_values['min_people'] . '/max. ' . $table_values['max_people'] . ')';

				unset( $section_name );
			}

			return $formatted_tables;
		}

		public function get_section_name( $section_id ) {
			global $rtb_controller;

			$sections = json_decode( html_entity_decode( $rtb_controller->settings->get_setting( 'rtb-table-sections' ) ) );
			$sections = is_array( $sections ) ? $sections : array();

			foreach ( $sections as $section ) {

				if ( $section->section_id == $section_id ) { return $section->name; }
			}

			return false;
		}

		public function format_pickadate_time( $time ) {
			return array( date( 'G', $time ), date( 'i', $time ) );
		}
	}
}