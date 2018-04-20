<?php
/**
 * Plugin name:       The Events Calendar: Advance Mini Calendar Widget
 * Description:       Tries to force the minicalendar widget to show the month of the next upcoming event by default, rather than simply showing the current month (which might be empty).
 * Version:           1.0.0
 * Extension Class:   Tribe__Extension__Advance_Minical
 * GitHub Plugin URI: https://github.com/mt-support/tribe-ext-advance-mini-calendar/
 * Author:            Modern Tribe, Inc
 * Author URI:        http://theeventscalendar.com
 * License:           GPL v3 - see http://www.gnu.org/licenses/gpl.html
 *
 * The Events Calendar: Advance Mini Calendar Widget
 * Copyright (C) 2018 Modern Tribe, Inc
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Do not load unless Tribe Common is fully loaded and our class does not yet exist.
if (
	class_exists( 'Tribe__Extension' )
	&& ! class_exists( 'Tribe__Extension__Advance_Minical' )
) {
	/**
	 * Extension main class, class begins loading on init() function.
	 */
	class Tribe__Extension__Advance_Minical extends Tribe__Extension {

		protected $target_date = false;

		/**
		 * Set the minimum required version of The Events Calendar
		 * and The Events Calendar Pro
		 */
		public function construct() {

			// Add required plugins
			$this->add_required_plugin( 'Tribe__Events__Main', '4.3.3' );
			$this->add_required_plugin( 'Tribe__Events__Pro__Main', '4.3.3' );
			$this->set_version( '1.0.0' );

		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init( $target_date = false ) {

			if ( is_admin() ) {
				return;
			}

			$this->target_date = $target_date;

			add_action( 'wp_loaded', array( $this, 'set_target_date' ) );
			add_filter( 'widget_display_callback', array( $this, 'advance_minical' ), 20, 2 );

		}

		/**
		 * Basic check to help filter out spurious date formats or automatically determine
		 * the next most appropriate date to use.
		 *
		 * @since 1.0.0
		 */
		public function set_target_date() {

			if ( ! is_string( $this->target_date ) || 1 !== preg_match( '#^\d{4}-\d{2}(-\d{2})?$# ', $this->target_date ) ) {
				$this->target_date = $this->next_upcoming_date();
			}
		}

		/**
		 * Check if the functionality should be applied, given the
		 * context and the instance
		 *
		 * @since 1.0.0
		 *
		 * @param array     $instance  The current widget instance's settings.
		 * @param WP_Widget $widget    The current widget instance.
		 *
		 * @return array $instance
		 */
		public function advance_minical( $instance, $widget ) {

			if ( 'tribe-mini-calendar' !== $widget->id_base || isset( $instance['eventDate'] ) ) {
				return $instance;
			}

			if ( date( 'Y-m' ) === $this->target_date ) {
				return $instance;
			}

			add_action( 'tribe_before_get_template_part', array( $this, 'modify_list_query' ), 5 );

			$instance['eventDate'] = $this->target_date;

			return $instance;
		}

		/**
		 * Check if the query amendments should be triggered or not.
		 * (if the template is the Mini Calendar List Loop)
		 *
		 * @since 1.0.0
		 *
		 * @param string $template
		 *
		 */
		public function modify_list_query( $template ) {

			if ( false === strpos( $template, 'mini-calendar/list.php' ) ) {
				return;
			}

			add_action( 'parse_query', array( $this, 'amend_list_query' ) );
		}

		/**
		 * Modify the Mini Calendar List Loop query
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Query $query
		 */
		public function amend_list_query( $query ) {

			// Run this once only.
			remove_action( 'parse_query', array( $this, 'amend_list_query' ) );

			$the_query = $query->query_vars;

			$the_query['start_date'] = $this->target_date . '-01';
			$last_day = Tribe__Date_Utils::get_last_day_of_month( strtotime( $the_query['start_date'] ) );
			$the_query['end_date'] = substr_replace( $the_query['start_date'], $last_day, -2 );
			$the_query['end_date'] = tribe_end_of_day( $the_query['end_date'] );

			$query->query_vars = $the_query;
		}

		/**
		 * Get the next upcoming event date
		 *
		 * @since 1.0.0
		 *
		 * @return string $start_date
		 */
		protected function next_upcoming_date() {
			$next_event = tribe_get_events( array(
				'eventDisplay'   => 'list',
				'posts_per_page' => 1,
				'start_date'     => date( Tribe__Date_Utils::DBDATEFORMAT )
			));

			$start_date = date( 'Y-m' );

			if ( ! empty( $next_event ) || isset( $next_event[0] ) ) {
				$next_event_date = tribe_get_start_date( $next_event[0]->ID, false, 'Y-m' );

				// Prevent calendar from rewinding to the start of a currently ongoing event
				$start_date = ( $next_event_date > $start_date ) ? $next_event_date : $start_date;
			}

			return $start_date;
		}
	}
}