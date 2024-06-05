<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://sabrinazeidan.com/
 * @since      1.0.0
 * @package    Speedguard
 * @subpackage Speedguard/admin
 * @author     Sabrina Zeidan <sabrinazeidan@gmail.com>
 */
//temp for development



class SpeedGuard_Admin {


    const SG_METRICS_ARRAY = [
        'mobile'  => [
            'psi' => [ 'lcp', 'cls' ],
            'cwv' => [ 'lcp', 'cls', 'fid' ],
        ],
        'desktop' => [
            'psi' => [ 'lcp', 'cls' ],
            'cwv' => [ 'lcp', 'cls', 'fid' ],
        ],
    ];
    public static $cpt_name = 'guarded-page';
    private $plugin_name;
    private $version;
    public $main_page;
    public $tests_page_hook;
    public $settings_page_hook;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version     = $version;
        // PRO
        define( 'SPEEDGUARD_PRO', true );
        // Multisite
        if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
        }
        if ( is_plugin_active_for_network( 'speedguard/speedguard.php' ) ) {
            define( 'SPEEDGUARD_MU_NETWORK', true );
        }
        if ( is_multisite() && ! ( is_plugin_active_for_network( 'speedguard/speedguard.php' ) ) ) {
            define( 'SPEEDGUARD_MU_PER_SITE', true );
        }
        // Menu items and Admin notices
        add_action(
            ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? 'network_' : '' ) . 'admin_menu',
            [
                $this,
                'speedguard_admin_menu',
            ]
        );
        add_action(
            ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? 'network_' : '' ) . 'admin_notices',
            [
                $this,
                'show_admin_notices',
            ]
        );
        // If Network activated don't load stuff on subsites. Load on the main site of the Multisite network or for regular WP install
        global $blog_id;
        if ( ! ( is_plugin_active_for_network( 'speedguard/speedguard.php' ) ) || ( is_plugin_active_for_network( 'speedguard/speedguard.php' ) ) && ( is_main_site( $blog_id ) ) ) {
            require_once plugin_dir_path( __FILE__ ) . '/includes/class.widgets.php';
            require_once plugin_dir_path( __FILE__ ) . '/includes/class.settings.php';
            require_once plugin_dir_path( __FILE__ ) . '/includes/class.tests.php';
            require_once plugin_dir_path( __FILE__ ) . '/includes/class.lighthouse.php';
            require_once plugin_dir_path( __FILE__ ) . '/includes/class.notifications.php';
            add_action( 'admin_init', [ $this, 'speedguard_cpt' ] );
            add_filter( 'admin_body_class', [ $this, 'body_classes_filter' ] );
            add_action( 'transition_post_status', [ $this, 'guarded_page_unpublished_hook' ], 10, 3 );
            add_action( 'before_delete_post', [ $this, 'before_delete_test_hook' ], 10, 1 );
            // MU Headers alredy sent fix
            add_action( 'init', [ $this, 'app_output_buffer' ] );
            // Add removable query args
            add_filter( 'removable_query_args', [ $this, 'removable_query_args' ] );
            add_filter(
                ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? 'network_admin_' : '' ) . 'plugin_action_links_speedguard/speedguard.php',
                [
                    $this,
                    'speedguard_actions_links',
                ]
            );
        }
        add_action( 'wp_ajax_check_tests_progress', [ $this, 'check_tests_progress_fn' ] );
      //  add_action( 'wp_ajax_run_one_test', [ $this, 'run_one_test_fn' ] );
        add_action( 'wp_ajax_mark_test_as_done', [ $this, 'mark_test_as_done_fn' ] );

        //TODO Doing Move to the separate JS file
       // add_action( 'admin_footer', [ $this, 'run_tests_js' ] );

	    //add_action('admin_enqueue_scripts', [ $this, 'enqueue_run_tests_script']);


    }

    function check_tests_progress_fn() {

        //Only transient here
      //  check_ajax_referer( 'check_tests_queue', 'nonce' );
        //check current tests transient
        $current_tests_array  = get_transient( 'speedguard_tests_in_queue', true    );
        $last_test_is_done_tr = get_transient( 'speedguard_last_test_is_done' );
        $test_in_progress     = get_transient( 'speedguard_test_in_progress' );
        //Possible responses:
        //There were tests in the queue but now all have just been completed
        $last_test_complete = [
            'status'  => 'last_test_complete',
            'message' => 'All tests are complete',
        ];
        // There are no tests in the queue
        $do_nothing         = [
            'status'  => 'no_tests',
            'message' => 'There are no tests in queue',
        ];

        //There are still some tests in the queue
        if ( $current_tests_array ) {
            $current_tests_array = json_decode( $current_tests_array, true );
            // Run 1 test async in the separate function
            //If it's the first request and any test in not in progress now
            if ( ! get_transient( 'speedguard_test_in_progress' ) && (!empty($current_tests_array))) {
                //$one_test_id = array_shift( array_values( $current_tests_array ) );
                $one_test_id = current($current_tests_array);
                set_transient( 'speedguard_test_in_progress', $one_test_id );
                $upd_tr_value = get_transient('speedguard_test_in_progress');
                $tests_are_running  = [
                    'status'         => 'queue',
                    'message'        => 'There are tests in queue, there was NO speedguard_test_in_progress transient, setting it now',
                    'tr_value' => $upd_tr_value,
                    'tests_in_queue' => $current_tests_array,
                    'speedguard_test_in_progress_id'       => $one_test_id,
                    'speedguard_test_in_progress_url'       => get_post_meta( $one_test_id, 'speedguard_page_url', true ),
                    'action_just_done' => 'set_transient_speedguard_test_in_progress'
                ];

            }
            else { //if there is a test in progress

                $one_test_id = json_decode(get_transient( 'speedguard_test_in_progress' ), true );
                $tests_are_running  = [
                    'status'         => 'queue',
                    'message'        => 'There are tests in queue, there WAS speedguard_test_in_progress transient, do not update',
                    'tests_in_queue' => $current_tests_array,
                    'speedguard_test_in_progress_id'       => $one_test_id,
                    'speedguard_test_in_progress_url'       => get_post_meta( $one_test_id, 'speedguard_page_url', true ),
                    'action_just_done' => 'nothing, test in progress was added before'
                ];

            }
            $response = $tests_are_running;
        } else if ( !$current_tests_array && !$test_in_progress && ($last_test_is_done_tr !== false) ) { //if there are no tests in the queue, but last test has just completed
            delete_transient( 'speedguard_last_test_is_done' );
            $response = $last_test_complete;
        } else { // if there are no tests and not waiting for the last one to complete
            $response = $do_nothing;
        }
        wp_send_json( $response );
    }

//TOOD remove this, check JS execution to replicate first
   /**
 * function run_one_test_fn() {
        check_ajax_referer( 'sg_run_one_test_nonce', 'nonce' );
        //check current tests transient
        $speedguard_test_in_progress = get_transient( 'speedguard_test_in_progress' );
        $test_id = $_POST['current_test_id'];
        $speedguard_sending_request_now = get_transient( 'speedguard_sending_request_now' );
        //if test in progress, and the request to Lighthouse is sending at the moment
        if ( $speedguard_test_in_progress && $speedguard_sending_request_now ) {
            $response = [
                'status' => 'busy',
                'comment' => 'waiting for LightHouse to respond'
            ];
        }
        //if test in progress, and the request to Lighthouse has not been sent yet -- send it
        else if ( $speedguard_test_in_progress && !$speedguard_sending_request_now ) {
            set_transient('speedguard_sending_request_now', $test_id );
            $test_response = SpeedGuard_Lighthouse::lighthouse_new_test( json_decode( $test_id ) );
            delete_transient('speedguard_sending_request_now');
            $response      = [
                'status'  => $test_response,
                'test_id' => $test_id
            ];
        }
        else {
            $response = [
                'status' => 'weird',
                'comment' => 'no other cases where this funciton is called should exist, only when there is speedguard_test_in_progress transient set',
            ];
        }
        wp_send_json( $response );
    }

**/
   //MArk individual test as done and save results to post_meta
    //body: `action=mark_test_as_done&current_test_id=${post_id}&test_result_data=${test_result_data}&nonce=${sgnoncee}`,
	//
   function mark_test_as_done_fn() {
	   // check_ajax_referer( 'mark_test_as_done', 'nonce' );
	    if (empty($_POST['current_test_id'])) return;
        //Data that we expect to have in the request: current_test_id, test_result_data, nonce
	   //var_dump($_POST);

        $current_test = $_POST['current_test_id'];
	    $test_result_data_from_post = wp_unslash($_POST['test_result_data']); // don't know where those slashes come from
	    $test_result_data = json_decode($test_result_data_from_post, true);

        $mobile_data = $test_result_data[0]['mobile'];
        $desktop_data = $test_result_data[1]['desktop'];


	   $both_devices_values =
            ['mobile' =>[
                'cwv'=>[
                        'lcp' => $mobile_data['cwv']['lcp'], //TODO check seems to be fine
                        'cls' => $mobile_data['cwv']['cls'],
                        'fid' => $mobile_data['cwv']['fid'],
                        'overall_category' => $mobile_data['cwv']['overall_category']
                       ],
                'psi'=>[
                        'lcp' => $mobile_data['psi']['lcp'],
                    // title, description, score, scoreDisplayMode, displayValue, numericValue
                        'cls' => $mobile_data['psi']['cls'],
                ]
            ],
            'desktop' => [
                       'cwv'=>[
                            'lcp' => $desktop_data['cwv']['lcp'], //array if ok, string if no data
                            'cls' => $desktop_data['cwv']['cls'],
                            'fid' => $desktop_data['cwv']['fid'],
                            'overall_category' => $desktop_data['cwv']['overall_category']
                           ],
                    'psi'=>[
                            'lcp' => $desktop_data['psi']['lcp'], //array
                        // title, description, score, scoreDisplayMode, displayValue, numericValue
                            'cls' => $desktop_data['psi']['cls'],
                    ]

            ]
            ];
        $update_url_values = update_post_meta( $current_test, 'sg_test_result',  $both_devices_values );
//wp_mail('sabrinazeidanspain@gmail.com', 'another attempt1205',    '$test_result_data:  '.print_r($test_result_data,true).'$mobile_data:  '.print_r($mobile_data,true).'$desktop_data:  '.print_r($desktop_data,true).'<br>$device_values ' .print_r($both_devices_values,true).'<br>$test_result_data[0]'.print_r($test_result_data[0], true), 'Content-Type: text/html; charset=UTF-8');





	    //Mark test as done in the queue
       $current_tests_array = json_decode( get_transient( 'speedguard_tests_in_queue' ), true );
	   //How  $current_tests_array can be empty here?

		if (is_array($current_tests_array) && in_array( $current_test, $current_tests_array ) ) {
			$key = array_search( $current_test, $current_tests_array );
			unset( $current_tests_array[ $key ] );
		}
		
	    delete_transient( 'speedguard_test_in_progress' );
	    //if after removing this test there are no tests left to process, mark that this is the last test in queue and delete transient
	    if ( count( $current_tests_array ) < 1 ) {
		    delete_transient( 'speedguard_tests_in_queue' );
		    set_transient( 'speedguard_last_test_is_done', true, 300 );
            $last_test_is_done = true;

		    //Update CWV here, and count average psi

            //TODO? move this seomwhere else, after checking the  speedguard_last_test_is_done ? Maybe not -- another page reload would be needed
		   $calculated_average_psi =  SpeedGuard_Lighthouse::count_average_psi();

            //Save CWV for origin

		    $both_devices_values_origin =
			    ['mobile' => [
				    'cwv'=>[
					    'lcp' => $mobile_data['originCWV']['lcp'], //TODO check seems to be fine
					    'cls' => $mobile_data['originCWV']['cls'],
					    'fid' => $mobile_data['originCWV']['fid'],
					    'overall_category' => $mobile_data['originCWV']['overall_category']
				    ],
                    'psi' => [
                            'lcp' => $calculated_average_psi['mobile']['psi']['lcp'],
                            'cls' => $calculated_average_psi['mobile']['psi']['cls']
                    ]

			    ],
			     'desktop' => [
				     'cwv'=>[
					     'lcp' => $desktop_data['originCWV']['lcp'], //array if ok, string if no data
					     'cls' => $desktop_data['originCWV']['cls'],
					     'fid' => $desktop_data['originCWV']['fid'],
					     'overall_category' => $desktop_data['originCWV']['overall_category']
				     ],
                 'psi' => [
			        'lcp' => $calculated_average_psi['desktop']['psi']['lcp'],
			        'cls' => $calculated_average_psi['desktop']['psi']['cls']
		            ]
			     ]
			    ];


		    $update_cwv_origin_data = SpeedGuard_Admin::update_this_plugin_option( 'sg_origin_results', $both_devices_values_origin);


		   // error_log( print_r( $calculated_average_psi, true ) );
		   // error_log( print_r( $both_devices_values, true ) );
		   // error_log( print_r( $mobile_data, true ) );






	    } else {
		    // delete_transient('speedguard_waiting_for_the_last_test_to_finish'); //for the case test was added while the last one was running, and that one is not the last one anymore
		    set_transient( 'speedguard_tests_in_queue', wp_json_encode( $current_tests_array ) );
	    }
	    $response = [
		    'status' => 'test marked as done',
		    'test_id_passed'  => $current_test,
            'last_test_in_queue' => isset($last_test_is_done)? $last_test_is_done : false
	    ];


	    wp_send_json( $response );

    }





    public static function capability() {
        $capability = 'manage_options';

        return $capability;
    }

    public static function supported_post_types() {
        $args                 = [ 'publicly_queryable' => true ];
        $output               = 'names';
        $operator             = 'and';
        $supported_post_types = get_post_types( $args, $output, $operator );
        unset( $supported_post_types['attachment'] );
        $supported_post_types['page'] = 'page';

        return $supported_post_types;
    }

    public static function before_delete_test_hook( $postid ) {
        if ( get_post_type( $postid ) === self::$cpt_name ) {
            $guarded_item_id   = get_post_meta( $postid, 'guarded_post_id', true );
            $guarded_item_type = get_post_meta( $postid, 'speedguard_item_type', true );
            if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
                $blog_id = get_post_meta( $postid, 'guarded_post_blog_id', true );
                switch_to_blog( $blog_id );
            }
            if ( $guarded_item_type === 'single' ) {
                update_post_meta( $guarded_item_id, 'speedguard_on', 'false' );
            } elseif ( $guarded_item_type === 'archive' ) {
                update_term_meta( $guarded_item_id, 'speedguard_on', 'false' );
            }
            if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
                switch_to_blog( get_network()->site_id );
            }
        }
    }

    public static function guarded_page_unpublished_hook( $new_status, $old_status, $post ) {
        // Delete test data when original post got unpublished
        if ( ( $old_status === 'publish' ) && ( $new_status != 'publish' ) && ( get_post_type( $post->ID ) ) != self::$cpt_name ) {
            $speedguard_on = get_post_meta( $post->ID, 'speedguard_on', true );
            if ( $speedguard_on && $speedguard_on[0] === 'true' ) {
                $connected_guarded_pages = get_posts(
                    [
                        'post_type'      => self::$cpt_name,
                        'post_status'    => 'publish',
                        'posts_per_page' => 1,
                        'fields'         => 'ids',
                        'meta_query'     => [
                            [
                                'key'     => 'guarded_post_id',
                                'value'   => $post->ID,
                                'compare' => 'LIKE',
                            ],
                        ],
                        'no_found_rows'  => true,
                    ]
                );
                if ( $connected_guarded_pages ) {
                    foreach ( $connected_guarded_pages as $connected_guarded_page_id ) {
                        SpeedGuard_Tests::delete_test_fn($connected_guarded_page_id);
                    }
                    // uncheck speedguard_on
                    update_post_meta( $post->ID, 'speedguard_on', 'false' );
                }
            }
        }
    }

    public static function update_this_plugin_option( $option_name, $option_value ) {
        if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
            return update_site_option( $option_name, $option_value );
        } else {
            return update_option( $option_name, $option_value );
        }
    }

    public static function delete_this_plugin_option( $option_name ) {
        if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
            return delete_site_option( $option_name );
        } else {
            return delete_option( $option_name );
        }
    }

    public static function speedguard_cpt() {
        $args = [
            'public'              => false,
            'exclude_from_search' => true,
            // 'publicly_queryable'      => true,
            'show_ui'             => true,
            'supports'            => [ 'title', 'custom-fields' ],
        ];
        register_post_type( 'guarded-page', $args );
    }

    public static function show_admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // All screens
        // Dashboard and SpeedGuard Settigns screens
        if ( self::is_screen( 'settings,dashboard' ) ) {
            if ( (int)get_transient('speedguard_tests_count') === 1 ) { // TODO: set transient/user meta on dissmissal action
                $message = sprintf( __( 'You only have the speed of 1 page monitored currently. Would you like to %1$sadd other pages%2$s to see the whole picture of the site speed?', 'speedguard' ), '<a href="' . self::speedguard_page_url( 'tests' ) . '">', '</a>' );
                $notices = self::set_notice( $message, 'warning' );
            }
        }
        // Plugins screen
        if ( self::is_screen( 'plugins' ) ) {
            // homepage was added/updated on activation
            if ( get_transient( 'speedguard-notice-activation' ) ) {
                $message = sprintf( __( 'Homepage performance test has just started. Would you like to %1$stest some other pages%2$s as well?', 'speedguard' ), '<a href="' . self::speedguard_page_url( 'tests' ) . '">', '</a>' );
                $notices = self::set_notice( $message, 'success' );
            }
            // TODO: On plugin deactivation
            if ( ( self::is_screen( 'plugins' ) ) && ( get_transient( 'speedguard-notice-deactivation' ) ) ) {
                // $notices =  SpeedGuard_Admin::set_notice(__('Shoot me an email if something didn\'t work as expected','speedguard'),'warning' );
                // delete_transient( 'speedguard-notice-deactivation' );
            }
        }
        // Tests screen Notices
        if ( self::is_screen( 'tests' ) ) {
                if( ! empty( $_REQUEST['speedguard'] ) && isset( $_GET[ 'sg_redirect_nonce' ] ) && wp_verify_nonce( $_GET[ 'sg_redirect_nonce' ], 'sg_redirect_nonce_action' ) ) {

                    if ( get_transient('speedguard_notice_add_new_url_error_empty') ) {
                    $notices = self::set_notice( __( 'Please select the post you want to add.', 'speedguard' ), 'warning' );
                }

                if (  get_transient('speedguard_notice_add_new_url_error_not_current_domain')) {
                    $notices = self::set_notice( __( 'SpeedGuard only monitors pages from current website.', 'speedguard' ), 'warning' );
                }

                if (  get_transient('speedguard_notice_add_new_url_error_not_url')) {
                    $notices = self::set_notice( __( 'Please enter valid URL or select the post you want to add.', 'speedguard' ), 'warning' );
                }

                if (  get_transient('speedguard_notice_new_url_added')) {
                    $notices = self::set_notice( __( 'New URL is successfully added!', 'speedguard' ), 'success' );
                }

                if (  get_transient('speedguard_notice_speedguard_test_being_updated')) {
                    $notices = self::set_notice( __( 'Test is being updating...', 'speedguard' ), 'success' );
                }
                if ( $_REQUEST['speedguard'] === 'load_time_updated' ) {
                    // TODO: This doesn't work properly, load_time_updated is added via JS Switch to transient
                    $notices = self::set_notice( __( 'Results have been updated!', 'speedguard' ), 'success' );
                }

                if (  get_transient('speedguard_notice_slow_down')) {
                    $notices = self::set_notice( __( 'You are moving too fast. Wait at least 3 minutes before updating the tests', 'speedguard' ), 'warning' );
                }

                if (  get_transient('speedguard_notice_delete_guarded_pages')) {
                    $notices = self::set_notice( __( 'Selected pages are not guarded anymore!', 'speedguard' ), 'success' );
                }
            }

        }
        if ( self::is_screen( 'settings' ) ) {
            if ( ! empty( $_REQUEST['settings-updated'] ) && $_REQUEST['settings-updated'] === 'true' ) {
                $notices = self::set_notice( __( 'Settings have been updated!' ), 'success' );
            }
        }

        if ( isset( $notices ) ) {
            print $notices;
        }
    }

    // Delete test data when original post got unpublished
    public static function get_this_plugin_option( $option_name ) {
        if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
            return get_site_option( $option_name );
        } else {
            return get_option( $option_name );
        }
    }

    public static function is_screen( $screens ) {
        // screens: dashboard,settings,tests,plugins, clients
        $screens = explode( ',', $screens );
        $screens = str_replace(
            [ 'tests', 'settings', 'clients' ],
            [
                'toplevel_page_speedguard_tests',
                'speedguard_page_speedguard_settings',
                'speedguard_page_speedguard_clients',
            ],
            $screens
        );
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        // Multisite screens
        if ( defined( 'SPEEDGUARD_MU_NETWORK' ) ) {
            foreach ( $screens as $screen ) {
                $screens[] = $screen . '-network';
            }
        }
        $current_screen = get_current_screen();
        if ( $current_screen ) {
            $current_screen = $current_screen->id;
        }
        if ( in_array( ( $current_screen ), $screens ) ) {
            $return = true;
        } else {
            $return = false;
        }

        return $return;
    }

    public static function speedguard_page_url( $page ) {
        if ( $page === 'tests' ) {
            $admin_page_url = defined( 'SPEEDGUARD_MU_NETWORK' ) ? network_admin_url( 'admin.php?page=speedguard_tests' ) : admin_url( 'admin.php?page=speedguard_tests' );
        } elseif ( $page === 'settings' ) {
            $admin_page_url = defined( 'SPEEDGUARD_MU_NETWORK' ) ? network_admin_url( 'admin.php?page=speedguard_settings' ) : admin_url( 'admin.php?page=speedguard_settings' );
        }

        return $admin_page_url;
    }

    public static function set_notice( $message, $class ) {
        return "<div class='notice notice-$class is-dismissible'><p>$message</p></div>";
    }



    public function speedguard_actions_links( array $actions ) {
        return array_merge(
            [
                'settings' => sprintf( __( '%1$sSettings%2$s', 'speedguard' ), '<a href="' . self::speedguard_page_url( 'settings' ) . '">', '</a>' ),
                'tests'    => sprintf( __( '%1$sTests%2$s', 'speedguard' ), '<a href="' . self::speedguard_page_url( 'tests' ) . '">', '</a>' ),
            ],
            $actions
        );
    }

    // Plugin Styles
    public function removable_query_args( $query_args ) {
        if ( self::is_screen( 'settings,tests,clients' ) ) {
            $new_query_args = [ 'speedguard', 'new_url_id' ];
            $query_args     = array_merge( $query_args, $new_query_args );
        }

        return $query_args;
    }



    public function enqueue_styles() {
        if ( ( is_admin_bar_showing() ) && ( self::is_screen( 'dashboard,settings,tests' ) || ! is_admin() ) ) {
            wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/css/speedguard-admin.css', [], $this->version );
        }
        if ( is_admin_bar_showing() && self::is_screen( 'tests' ) ) {
            wp_enqueue_style( $this->plugin_name . '-awesompletecss', plugin_dir_url( __FILE__ ) . 'assets/awesomplete/awesomplete.css', [], $this->version );
        }
    }

     public function speedguard_tests_module_inline_fix( $tag, $handle ) {
    if( strpos( $handle, 'speedguard_tests_module-js' ) === 0 ) {
        if( current_theme_supports( 'html5', 'script' ) ) {
            return substr_replace( $tag, '<script type="module"', strpos( $tag, '<script' ), 7 );
        }
        else {
            return substr_replace( $tag, 'module', strpos( $tag, 'text/javascript' ), 15 );
        }
    }

    return $tag;
}

	public function enqueue_scripts() {
		if ( is_admin_bar_showing() && ( self::is_screen( 'dashboard,settings,tests,plugins,clients' ) || ! is_admin() ) ) {
			//general JS
            wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'assets/js/speedguard-admin.js', [], $this->version, false );

            //TODO Maybe include on tests page only?

            //For checking queue and initiating tests
			//if (self::is_screen('tests')) {


            //For making requests to API
            wp_enqueue_script(
				'speedguard_tests_module',
				plugin_dir_url( __FILE__ ) . 'assets/js/execute_tests.js',
				[],
				$this->version,
				true
			);
			wp_add_inline_script(
				'speedguard_tests_module',
				'const SG_Tests_Data = "data here"' );




			wp_enqueue_script(
				'speedguard_initiate_tests',
				plugin_dir_url( __FILE__ ) . 'assets/js/initiate_tests.js',
				[],
				$this->version,
				true
			);

			// Localize the script with your data
			$data = [
				'sg_ajaxurl' => admin_url('admin-ajax.php'),
				'sg_check_tests_queue_nonce' => wp_create_nonce('sg_check_tests_queue_nonce'),
				'sg_run_one_test_nonce' => wp_create_nonce('sg_run_one_test_nonce'),
				//'sgnoncee' => wp_create_nonce('sgnoncee'),
				//'newsgnoncee' => wp_create_nonce('newsgnoncee'),

				'reload' => self::is_screen('tests') ? 'true' : 'false',
			];
			//	wp_localize_script('speedguard_initiate_tests', 'initiate_tests_data', $data);

			$script = 'var initiate_tests_data = ' . json_encode($data) . ';';

			wp_add_inline_script('speedguard_initiate_tests', $script, 'before');







		}
		if ( is_admin_bar_showing() && self::is_screen( 'tests' ) ) {
			// search field with vanilla js
			wp_enqueue_script( $this->plugin_name . '-awesompletejs', plugin_dir_url( __FILE__ ) . 'assets/awesomplete/awesomplete.js' );
			wp_enqueue_script( 'speedguardsearch', plugin_dir_url( __FILE__ ) . 'assets/js/speedguard-search.js', [ $this->plugin_name . '-awesompletejs' ], $this->version, true );
			wp_localize_script(
				'speedguardsearch',
				'speedguardsearch',
				[
					'search_url' => home_url( '/wp-json/speedguard/search?term=' ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
				]
			);
		}
	}


	// Plugin Admin Notices
    function body_classes_filter( $classes ) {
        if ( self::is_screen( 'settings,tests,dashboard' ) ) {
            if (  get_transient('speedguard_tests_count') < 1 ) {
                $classes = $classes . ' no-guarded-pages';
            }
        }
        if ( self::is_screen( 'tests' ) ) {
            $sg_test_type = SpeedGuard_Settings::global_test_type();
            if ( 'cwv' === $sg_test_type ) {
                $class = 'test-type-cwv';
            } elseif ( 'psi' === $sg_test_type ) {
                $class = 'test-type-psi';
            }
            $classes = $classes . ' ' . $class;
        }
        if ( self::is_screen( 'plugins' ) ) {
            if ( get_transient( 'speedguard-notice-activation' ) ) {
                $classes = $classes . ' speedguard-just-activated';
            }
        }

        return $classes;
    }

    function speedguard_admin_menu() {
        $this->main_page          = add_menu_page(
            __( 'SpeedGuard', 'speedguard' ),
            __( 'SpeedGuard', 'speedguard' ),
            'manage_options',
            'speedguard_tests',
            [
                'SpeedGuard_Tests',
                'tests_page',
            ],
            'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48IURPQ1RZUEUgc3ZnIFs8IUVOVElUWSBuc19mbG93cyAiaHR0cDovL25zLmFkb2JlLmNvbS9GbG93cy8xLjAvIj5dPjxzdmcgdmVyc2lvbj0iMS4yIiBiYXNlUHJvZmlsZT0idGlueSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayIgeG1sbnM6YT0iaHR0cDovL25zLmFkb2JlLmNvbS9BZG9iZVNWR1ZpZXdlckV4dGVuc2lvbnMvMy4wLyIgeD0iMHB4IiB5PSIwcHgiIHdpZHRoPSI5MXB4IiBoZWlnaHQ9IjkxcHgiIHZpZXdCb3g9Ii0wLjUgLTAuNSA5MSA5MSIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PGRlZnM+PC9kZWZzPjxwYXRoIGZpbGw9IiM4Mjg3OEMiIGQ9Ik04NS42NDYsNDAuNjQ1Yy0yLjQwNCwwLTQuMzU1LDEuOTUyLTQuMzU1LDQuMzU1YzAsMjAuMDEzLTE2LjI3NywzNi4yOS0zNi4yOSwzNi4yOUMyNC45ODgsODEuMjksOC43MDksNjUuMDEzLDguNzA5LDQ1QzguNzA5LDI0Ljk4OCwyNC45ODgsOC43MDksNDUsOC43MDljMi40MDQsMCw0LjM1NC0xLjk1MSw0LjM1NC00LjM1NFM0Ny40MDQsMCw0NSwwQzIwLjE4NywwLDAsMjAuMTg3LDAsNDVjMCwyNC44MTQsMjAuMTg3LDQ1LDQ1LDQ1YzI0LjgxNCwwLDQ1LTIwLjE4Niw0NS00NUM5MCw0Mi41OTcsODguMDQ5LDQwLjY0NSw4NS42NDYsNDAuNjQ1eiIvPjxwYXRoIGZpbGw9IiM4Mjg3OEMiIGQ9Ik00Ny4zMiwzMC42MjRjLTEuMjM2LDEuODA1LTEuOTIzLDMuODA5LTIuMzkzLDUuNjc1Yy00Ljc3NiwwLjA0MS04LjYzNywzLjkyLTguNjM3LDguNzAxYzAsNC44MDcsMy45MDIsOC43MSw4LjcwOSw4LjcxYzQuODA3LDAsOC43MS0zLjkwMyw4LjcxLTguNzFjMC0xLjE1OC0wLjIzOC0yLjI1OS0wLjY0OC0zLjI3MmMxLjU0My0xLjE0OSwzLjEyOC0yLjU1NSw0LjMyNC00LjM5NmMxLjI5MS0yLjA4MywxLjkyNS00LjgwOCwzLjA5NC03LjE3N2MxLjExOS0yLjM5OCwyLjI4NC00Ljc3MSwzLjIzNi03LjA3OGMxLjAwNi0yLjI3OSwxLjg3Ny00LjQ1LDIuNjMxLTYuMzA5YzEuNDg3LTMuNzI1LDIuMzYxLTYuMjg2LDIuMzYxLTYuMjg2YzAuMDY3LTAuMTk3LDAuMDMyLTAuNDI0LTAuMTE2LTAuNTkyYy0wLjIyMS0wLjI1LTAuNjAyLTAuMjczLTAuODQ4LTAuMDU2YzAsMC0yLjAyNiwxLjc5NC00Ljg5Nyw0LjYwMmMtMS40MjMsMS40MDgtMy4wOTIsMy4wNTItNC44MTEsNC44NTRjLTEuNzY3LDEuNzY5LTMuNTA0LDMuNzU3LTUuMjkxLDUuNzEzQzUxLjAxOSwyNi45OTQsNDguNzQ4LDI4LjYyNiw0Ny4zMiwzMC42MjR6Ii8+PC9zdmc+',
            '81'
        );
        $this->tests_page_hook    = add_submenu_page( 'speedguard_tests', __( 'Speed Tests', 'speedguard' ), __( 'Speed Tests', 'speedguard' ), 'manage_options', 'speedguard_tests' );
        $this->settings_page_hook = add_submenu_page(
            'speedguard_tests',
            __( 'Settings', 'speedguard' ),
            __( 'Settings', 'speedguard' ),
            'manage_options',
            'speedguard_settings',
            [
                'SpeedGuard_Settings',
                'settings_page_function',
            ]
        );
    }

    function app_output_buffer() {
        ob_start();
    }
}