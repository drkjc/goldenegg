<?php

/**
 * Turn off tribe scripts
 */
add_action('wp_print_scripts', 'tribe_dequeue_scripts', 100);
function tribe_dequeue_scripts() {
	// frontend only
	if (! is_admin() ) {
		// dequeue
		wp_dequeue_script( 'tribe-common' );
		wp_dequeue_script( 'tribe-events-list' );
		wp_dequeue_script( 'tribe-events-pro' );
		wp_dequeue_script( 'tribe-events-pro-geoloc' );
		wp_dequeue_script( 'tribe-placeholder' );
		wp_dequeue_script( 'tribe-events-jquery-resize' );
		wp_dequeue_script( 'tribe-events-bar' );
		wp_dequeue_script( 'tribe-events-calendar-script' );
		wp_dequeue_script( 'tribe-events-bootstrap-datepicker' );
		wp_dequeue_script( 'tribe-events-php-date-formatter' );
		wp_dequeue_script( 'tribe-moment' );
		// deregister
		wp_deregister_script( 'tribe-events-php-date-formatter' );
	}
}


/**
 * Turn off tribe styles
 */
add_action( 'wp_enqueue_scripts', 'deregister_tribe_styles' );
function deregister_tribe_styles() {
	wp_deregister_style( 'tribe-events-calendar-style' ); // may need this one
	wp_deregister_style( 'tribe-events-calendar-pro-style' );
	wp_deregister_style( 'tribe-events-calendar-override-style' );
	wp_deregister_style( 'tribe-events-full-pro-calendar-style' );
	wp_deregister_style( 'tribe-events-full-calendar-style' );
	wp_deregister_style( 'tribe-events-pro-calendar-style' );
	wp_deregister_style( 'tribe-reset-style' );
	wp_deregister_style( 'tribe-common-style' );
	wp_deregister_style( 'tribe-common-skeleton-style' );
}


/**
 * Redirect Venues post type
 */
add_action( 'template_redirect', 'venue_redirect_post' );
function venue_redirect_post() {
	$queried_post_type = get_query_var('post_type');
	if ( is_single() && 'tribe_venue' ==  $queried_post_type ) {
		wp_redirect('/calendar/');
		exit;
	}
}
    
    
/**
 * Add venue to custom tribe column
 */
add_filter( 'manage_tribe_events_posts_columns', 'tribes_add_event_columns', 10, 1 );
function tribes_add_event_columns( $columns ) {
	return array_merge( $columns,
		array(
			'venue_name' => 'Venue'
		)
	);
}


/**
 * Custom columns for tribe events, show venue
 */
add_action( 'manage_tribe_events_posts_custom_column', 'tribe_events_custom_columns', 10, 2 );
function tribe_events_custom_columns( $column, $post_id ) {
	switch ( $column ) {
		case 'venue_name':
			$venue_id = get_post_meta( $post_id, '_EventVenueID', true );
			if ( $venue_id ) {
				echo "<a href='" . get_permalink( $venue_id ) . "'>" . get_post_field( 'post_title', $venue_id ) . "</a>";
			} else {
				echo "n/a";
			}
			break;
	}
}


/**
 * Hide extra submenus in tribe admin sidebar
 */
add_action( 'admin_menu', 'tribe_remove_annoying_menus', 1000 );
function tribe_remove_annoying_menus() {

	// Hide Organizer
	remove_submenu_page( 'edit.php?post_type=tribe_events', 'edit.php?post_type=tribe_organizer' );
	remove_submenu_page( 'edit.php?post_type=tribe_organizer', 'post-new.php?post_type=tribe_organizer' );

	// Hide Tags
	remove_submenu_page( 'edit.php?post_type=tribe_events', 'edit-tags.php?taxonomy=post_tag' );

	// Hide App Shop
	remove_submenu_page( 'edit.php?post_type=tribe_events', 'tribe-app-shop' );
	
}


/**
 * Hide extra fields on single view in admin
 */
add_action( 'admin_head', 'tribe_fix_single_events_page' );
function tribe_fix_single_events_page() {
	echo '<style>
		#event_tribe_venue #google_map_link_toggle, 
		table#event_tribe_organizer, 
		table#event_url, 
		table#event_cost {
			display: none;
		}
		input#recurrence-description {
			width: 100%;
		}
		</style>';
}


/**
 * In the schema description, we WANT the venue to show, but NOT the permalink
 *
 * @param $permalink
 * @param $passed_post
 * @param $leavename
 * @param $sample
 *
 * @return string
 */
add_filter( 'post_type_link', 'tribe_adjust_venue_links', 20, 4 );
function tribe_adjust_venue_links( $permalink, $passed_post, $leavename, $sample ) {
	if ( $passed_post->post_type == 'tribe_venue' ) {
		$override = get_post_meta( $passed_post->ID, '_VenueURL', true );
		if ( $override != '' ) {
			$permalink = $override;
		}
	}
	return $permalink;
}


/**
 * Remove "United States" from all venue listings.
 * @param $output
 * @param $venue_id
 *
 * @return mixed
 */
add_filter('tribe_get_country', 'tribe_customize_country', 10, 2);
function trbie_customize_country($output, $venue_id) {
	return str_replace("United States", '', $output);
}


/**
 * If an event is cancelled, add schema so google knows about it
 * Requires ACF checkbox field `event_status`
 * 
 * @param $data
 * @param $args
 * @param $post
 *
 * @return mixed
 */
add_filter('tribe_json_ld_event_object', 'tribe_add_cancelled_to_json_if_required', 10, 3);
function tribe_add_cancelled_to_json_if_required($data, $args, $post) {
	if (get_field('event_status', $post->ID)) {
		$data->eventStatus = "http://schema.org/EventCancelled";
	}
	return $data;
}


/**
 * If an event is registerable, add the schema so google knows about it
 * Requires ACF field `rsvp_form`
 * @param $data
 * @param $args
 * @param $post
 *
 * @return mixed
 */
add_filter('tribe_json_ld_event_object', 'tribe_add_offer_to_json_if_required', 10, 3);
function tribe_add_offer_to_json_if_required($data, $args, $post) {
	if (get_field('rsvp_form', $post->ID)) {
		$offer = new stdClass();
		$offer->{'@type'} = "Event Registration";
		$offer->lowPrice = "Free";
		$offer->offerCount = get_field( 'rsvp_limit', $post->ID );
		$data->offers = $offer;
	}
	return $data;
}

/*
 * Recurring events in wp-admin: only display first (parent) occurrence in list of Events
 * (i.e. hide child recurring events)
 *
 * From https://theeventscalendar.com/knowledgebase/hide-recurring-event-instances-in-admin/
 * - https://gist.github.com/cliffordp/81f23a207ab483c9e7c6d910f9b29c0a
 *
 */
new Events_Admin_List__Remove_Child_Events;
class Events_Admin_List__Remove_Child_Events {
	public function __construct() {
		// Don't kick in unless we're on the edit.php screen
		add_action( 'load-edit.php', array( $this, 'setup' ) );
	}

	public function setup() {
		// Listen out for the main events query
		if ( @$_GET['collapse_recurring'] == 'true' ) {
			if ( 'tribe_events' === $GLOBALS['typenow'] ) {
				add_action( 'parse_query', array( $this, 'modify' ) );
			}
		}
	}

	function modify( WP_Query $query ) {
		// Run once, only for the main query
		if ( ! $query->is_main_query() ) {
			return;
		}
		remove_action( 'parse_query', array( $this, 'modify' ) );

		// Only return top level posts as a means of ignoring child posts
		$query->set( 'post_parent', 0 );
	}
}

// Tab to turn filter on and off
add_filter( 'views_edit-tribe_events', 'tribe_add_admin_event_filter_links' );
function tribe_add_admin_event_filter_links( $views ) {
	if ( @$_GET['collapse_recurring'] == 'true' ) {
		$views['all_recur'] = "Showing recurring instances once";
	} else {
		$views['all_recur'] = "<a href='/wp-admin/edit.php?post_status=publish&post_type=tribe_events&collapse_recurring=true'>Show recurring instances once</a>";
	}
	return $views;
}

/**
 *  Remove tribe credits injected into footer
 */
add_action( 'wp_footer', 'tribe_kill_tribe_credits', 0 );
function tribe_kill_tribe_credits() {
	remove_anonymous_object_filter(
		'tribe_events_after_html',
		'Tribe__Credits',
		'html_comment_credit'
	);
}

add_action( 'admin_footer', 'tribe_kill_tribe_nudge', 0 );
function tribe_kill_tribe_nudge() {
	remove_anonymous_object_filter(
		'admin_footer_text',
		'Tribe__Credits',
		'rating_nudge'
	);
}
if ( ! function_exists( 'remove_anonymous_object_filter' ) ) {
	/**
	 *
	 * Remove an anonymous object filter.
	 *
	 * @param  string $tag    Hook name.
	 * @param  string $class  Class name
	 * @param  string $method Method name
	 * @return void
	 */
	function remove_anonymous_object_filter( $tag, $class, $method ) {
		$filters = $GLOBALS['wp_filter'][ $tag ];
		if ( empty ( $filters ) ) {
			return;
		} foreach ( $filters as $priority => $filter ) {
			foreach ( $filter as $identifier => $function ) {
				if ( is_array( $function)
				     and is_a( $function['function'][0], $class )
				         and $method === $function['function'][1]
				) {
					remove_filter(
						$tag,
						array ( $function['function'][0], $method ),
						$priority
					);
				}
			}
		}
	}
}

/**
 *  Remove comment support from events
 */
add_action('init', 'remove_comment_support', 100);
function remove_comment_support() {
	remove_post_type_support( 'tribe_events', 'comments' );
}


/**
 *  Remove annoying geo nag message
 *  'You have venues for which we don't have Geolocation information'
 */
add_action( 'admin_init', function() {
	remove_action( 'admin_notices', array(
		Tribe__Events__Pro__Geo_Loc::instance(),
		'show_offer_to_fix_notice'
	) );
}, 100 );
