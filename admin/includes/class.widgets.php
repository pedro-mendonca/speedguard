<?php
/**
 *
 *   Class responsible for adding metaboxes
 */


class SpeedGuard_Widgets {
	public function __construct() {
		$options = SpeedGuard_Admin::get_this_plugin_option('speedguard_options');
		if (!empty($options)) {
			if ($options['show_dashboard_widget'] === 'on') {
				add_action('wp_' . (defined('SPEEDGUARD_MU_NETWORK') ? 'network_' : '') . 'dashboard_setup', [
					$this,
					'speedguard_dashboard_widget_function',
				]);
			}
		}
	}

	/**
	 * Define all metaboxes for plugin's admin pages (Tests and Settings)
	 */
	public static function add_meta_boxes() {
		$sg_test_type = SpeedGuard_Settings::global_test_type();
		wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);

		add_meta_box('settings-meta-box', __('SpeedGuard Settings', 'speedguard'), [
			'SpeedGuard_Settings',
			'settings_meta_box',
		], '', 'normal', 'core');

		if ('cwv' === $sg_test_type) {
			$origin_widget_title = 'Core Web Vitals (real users experience) for the entire website';
		} elseif ('psi' === $sg_test_type) {
			$origin_widget_title = 'PageSpeed Insights (lab tests)';
		}

add_meta_box('speedguard-dashboard-widget', esc_html__($origin_widget_title, 'speedguard'), [
    'SpeedGuard_Widgets',
    'origin_results_widget_function',
], '', 'main-content', 'core');

		add_meta_box('speedguard-add-new-url-meta-box', esc_html__('Add new URL to monitoring', 'speedguard'), [
			'SpeedGuard_Widgets',
			'add_new_widget_function',
		], '', 'main-content', 'core');

		if ('cwv' === $sg_test_type) {
			$test_type = ' -- Core Web Vitals';
		} elseif ('psi' === $sg_test_type) {
			$test_type = ' -- PageSpeed Insights';
		}

		add_meta_box('tests-list-meta-box', sprintf(esc_html__('Test results for specific URLs %s', 'speedguard'), $test_type), [
			'SpeedGuard_Tests',
			'tests_results_widget_function',
		], '', 'main-content', 'core');

		add_meta_box('speedguard-legend-meta-box', esc_html__('How to understand the information above?', 'speedguard'), [
			'SpeedGuard_Widgets',
			'explanation_widget_function',
		], '', 'main-content', 'core');

		add_meta_box('speedguard-important-questions-meta-box', esc_html__('Important questions:', 'speedguard'), [
			'SpeedGuard_Widgets',
			'important_questions_widget_function',
		], '', 'side', 'core');

		add_meta_box('speedguard-about-meta-box', esc_html__('Do you like this plugin?', 'speedguard'), [
			'SpeedGuard_Widgets',
			'about_widget_function',
		], '', 'side', 'core');
	}
	/**
	 * Function responsible for displaying the Origin widget, both n Tests page and Dashboard
	 */
	public static function origin_results_widget_function( $post = '', $args = '' ) {
    // Retrieving data to display
    $speedguard_cwv_origin = SpeedGuard_Admin::get_this_plugin_option( 'sg_origin_results' );
    // Preparing data to display
    $sg_test_type = SpeedGuard_Settings::global_test_type();
    foreach ( SpeedGuard_Admin::SG_METRICS_ARRAY as $device => $test_types ) {
        foreach ( $test_types as $test_type => $metrics ) {
            if ( $test_type === $sg_test_type ) { //prepare metrics only for needed test type
                foreach ( $metrics as $metric ) {
                    $current_metric  = $device . '_' . $metric;
                    $$current_metric = SpeedGuard_Widgets::single_metric_display( $speedguard_cwv_origin, $device, $test_type, $metric );
                }
            }
        }
    }
    if ( 'cwv' === $sg_test_type ) {
        $fid_tr = '<tr><th>' . esc_html__( 'First Input Delay (FID)', 'speedguard' ) . '</th>
    <td>' . esc_html($mobile_fid) . '</td>
    <td>' . esc_html($desktop_fid) . '</td></tr>';
    } else {
        $fid_tr = '';
    }

    if ( 'cwv' === $sg_test_type && isset($speedguard_cwv_origin['desktop']['cwv']['overall_category']) && isset($speedguard_cwv_origin['mobile']['cwv']['overall_category']) ) {

        $overall_category_desktop = $speedguard_cwv_origin['desktop']['cwv']['overall_category'];
        $overall_category_mobile  = $speedguard_cwv_origin['mobile']['cwv']['overall_category'];
        //overall_category can be FAST, AVERAGE, SLOW. Assign color (red, yellow, green) accordingly
        $mobile_color  = ( $overall_category_mobile === 'FAST' ) ? 'score-green' : ( ( $overall_category_mobile === 'AVERAGE' ) ? 'score-yellow' : 'score-red' );
        $desktop_color = ( $overall_category_desktop === 'FAST' ) ? 'score-green' : ( ( $overall_category_desktop === 'AVERAGE' ) ? 'score-yellow' : 'score-red' );
    }

    $mobile_color = isset($mobile_color) ? esc_attr($mobile_color) : '';
    $desktop_color = isset($desktop_color) ? esc_attr($desktop_color) : '';


   $content = "
<table class='widefat fixed striped toplevel_page_speedguard_tests_cwv_widget'>
<thead>
<tr class='bc-platforms'><td></td>
<th><i class='sg-device-column mobile speedguard-score " . esc_attr($mobile_color) . "' aria-hidden='true' title='Mobile'></i></th>
<th><i class='sg-device-column desktop speedguard-score " . esc_attr($desktop_color) . "' aria-hidden='true' title='Desktop'></i></th>
</tr>
</thead>
<tbody>
<tr>
<th>" . esc_html__( 'Largest Contentful Paint (LCP)', 'speedguard' ) . "</th>
<td>". wp_kses_post($mobile_lcp) . "</td>
<td>" . wp_kses_post($desktop_lcp) . "</td>
</tr>                                                                   
<tr><th>" . esc_html__( 'Cumulative Layout Shift (CLS)', 'speedguard' ) . "</th>

<td>" . wp_kses_post($mobile_cls) . "</td>
<td>" . wp_kses_post($desktop_cls) . "</td>
</tr>
   " . wp_kses_post($fid_tr) . "
</tbody>
</table>
";





		if ('cwv' === $sg_test_type && str_contains($mobile_lcp, 'N')) {
			$info_text = sprintf(
				             esc_html__('N/A means that there is no data from Google available -- most likely your website has not got enough traffic for Google to make an evaluation (Not enough usage data in the last 90 days for this device type)', 'speedguard'),
				             '<a href="#">',
				             '</a>'
			             ) . '<div><br></div>';
		} elseif ('psi' === $sg_test_type) {
			$info_text = sprintf(
				             esc_html__('This is not real user data. These are averages calculated based on the tests below. Core Web Vitals -- is where the real data is. You can switch in Settings', 'speedguard'),
				             '<a href="#">',
				             '</a>'
			             ) . '<div><br></div>';
		} else {
			$info_text = '';
		}

		echo wp_kses_post($content . $info_text);

}

	/**
	 * Function responsible for formatting CWV data for display
	 */
	public static function single_metric_display( $results_array, $device, $test_type, $metric ) {

		$display_value = '';
		$category      = '';
		$class         = '';
		if ( ( $results_array === 'waiting' ) ) {  // tests are currently running, //PSI Origin results will be calculated after all tests are finished
			$class = 'waiting';
		} elseif ( ( is_array( $results_array ) ) ) {// tests are not currently running
			// Check if metric data is available for this device
			if ( isset( $results_array[ $device ][ $test_type ][ $metric] ) && is_array($results_array[ $device ][ $test_type ][ $metric]) ) {

				if ( $test_type === 'psi' ) {
					$display_value = $results_array[ $device ][ $test_type ][ $metric ]['displayValue'];
					$class         = 'score';
					$category      = $results_array[ $device ][ $test_type ][ $metric ]['score'];
				} elseif ( $test_type === 'cwv' ) {
					$metrics_value = $results_array[ $device ][ $test_type ][ $metric ]['percentile'];
					// Format metrics output for display
					if ( $metric === 'lcp' ) {
						$display_value = round( $metrics_value / 1000, 2 ) . ' s';
					} elseif ( $metric === 'cls' ) {
						$display_value = $metrics_value / 100;
					} elseif ( $metric === 'fid' ) {
						$display_value = $metrics_value . ' ms';
					}
					$class    = 'score';
					$category = $results_array[ $device ][ $test_type ][ $metric ]['category'];
				}
			} elseif ( $test_type === 'psi' && get_transient( 'speedguard-tests-running' ) ) {
				$class = 'waiting';
			} else {
				// No data available for the metric
				$class         = 'na';
				$display_value = 'N/A';
			}
		}
		$category             = 'data-score-category="' . $category . '"';
		$class                = 'class="speedguard-' . $class . '"';
		$metric_display_value = '<span ' . $category . ' ' . $class . '>' . $display_value . '</span>';

		return $metric_display_value;
	}

	public static function explanation_widget_function() {
		$cwv_link = 'https://web.dev/lcp/';
		// Create the table.
		?>
        <ul>
            <li>
                <h3><?php esc_html_e( 'What does N/A mean?' ); ?></h3>
                <span>
			<?php
			echo sprintf( esc_html__( 'If you see "N/A" for a metric in Core Web Vitals tests, it means that there is not enough real-user data to provide a score. This can happen if your website is new or has very low traffic. The same will be displayed in your <a href="%1$s">Google Search Console (GSC)</a>, which uses the same data source (<a href="%2$s">CrUX report</a>) as CWV.', 'speedguard' ), esc_url( 'https://search.google.com/search-console/' ), esc_url( 'https://developer.chrome.com/docs/crux/' ), );
			?>

		</span>
            </li>
            <li>
                <h3><?php esc_html_e( 'What is the difference between Core Web Vitals and PageSpeed Insights?' ); ?></h3>
                <span>
			<?php esc_html_e( 'The main difference between CWV and PSI is that CWV is based on real-user data, while PSI uses lab data collected in a controlled environment. Lab data can be useful for debugging performance issues, but it is not as representative of the real-world user experience as real-user data.' ); ?>
			<p><strong>
					<?php esc_html_e( 'If you have CWV data available, you should always refer to that data first, as it represents the real experience real users of your website are having.' ); ?></strong></p>
			<?php esc_html_e( 'If there is no CWV data avalable -- you CAN use PSI as a reference, but you need to remember these are LAB tests: on the devices, connection and location that are most certainlely don\'t match the actual state of things.' ); ?>
		</span>
            </li>
            <li>
                <h3><?php esc_html_e( 'Understanding metrics:' ); ?></h3>
                <span>
			<p>

				<?php esc_html_e( '<strong>Largest Contentful Paint (LCP):</strong> The time it takes for the largest content element on a page to load. This is typically an image or video.' ); ?>
				<img src="<?php echo esc_url(plugin_dir_url( __DIR__ ) . 'assets/images/lcp.svg') ?>"
                     alt="<?php echo esc_attr( 'Largest Contentful Paint chart' ); ?>">
			</p>
			<p>
				<?php esc_html_e( '<strong>Cumulative Layout Shift (CLS):</strong> The total amount of layout shift on a page while it is loading. This is a measure of how much the content on a page moves around while it is loading.' ); ?>
                <img src="<?php echo esc_url(plugin_dir_url( __DIR__ ) . 'assets/images/cls.svg'); ?>"
                     alt="Cumulative Layout Shift chart">
			</p>
			<p>
				<?php esc_html_e( '<strong>First Input Delay (FID):</strong> The time it takes for a browser to respond to a user interaction, such as clicking a button or tapping on a link. This is a measure of how responsive a web page feels to users.' ); ?>
                  <img src="<?php echo esc_url(plugin_dir_url( __DIR__ ) . 'assets/images/fid.svg'); ?>"
                       alt="First Input Delay chart">

			</p>
			<p>
				<?php esc_html_e( 'All three of these metrics are important for providing a good user experience. A fast LCP means that users will not have to wait long for the main content of a page to load. A low CLS means that users will not have to deal with content that moves around while they are trying to read it. And a low FID means that users will be able to interact with a web page quickly and easily.' ); ?>
			</p>
		</span>
            </li>
        </ul>

		<?php
	}

	public static function add_new_widget_function() {
		$nonce_field = wp_nonce_field( 'sg_add_new_url', 'sg_add_new_nonce_field' );
		$content     = '<form name="speedguard_add_url" id="speedguard_add_url"  method="post" action="">' . $nonce_field . '
		<input class="form-control"  type="text" id="speedguard_new_url" name="speedguard_new_url" value="" placeholder="' . __( 'Start typing the title of the post, page or custom post type...', 'speedguard' ) . '" autofocus="autofocus"/>
		<input type="hidden" id="blog_id" name="blog_id" value="" />
		<input type="hidden" id="speedguard_new_url_permalink" name="speedguard_new_url_permalink" value=""/> 
		<input type="hidden" id="speedguard_item_type" name="speedguard_item_type" value=""/> 
		<input type="hidden" id="speedguard_new_url_id" name="speedguard_new_url_id" value=""/>
		<input type="hidden" name="speedguard" value="add_new_url" />
		<input type="submit" name="Submit" class="button action" value="' . __( 'Add', 'speedguard' ) . '" />
		</form>';
		echo wp_kses_post($content);
	}

	public static function important_questions_widget_function() {
		echo wp_kses_post(SpeedGuard_Widgets::get_important_questions_widget_function());
	}

	public static function get_important_questions_widget_function() {
        //Convert this function to return instead of echo

		$links = [
			sprintf( __( '%1$sWhy CWV fail after they were passing before? [video]%2$s', 'speedguard' ), '<a href="https://www.youtube.com/watch?v=Q40B5cscObc" target="_blank">', '</a>' ),
			sprintf( __( '%1$sOne single reason why your CWV are not passing [video]%2$s', 'speedguard' ), '<a href="https://youtu.be/-d7CPbjLXwg?si=VmZ_q-9myI4SBYSD" target="_blank">', '</a>' ),
			sprintf( __( '%1$s5 popular recommendations that don’t work [video]%2$s', 'speedguard' ), '<a href="https://youtu.be/5j3OUaBDXKI?si=LSow4BWgtF9cSQKq" target="_blank">', '</a>' ),
		];
		$content = '<ul>';
		foreach ( $links as $link ) {
			$content .= '<li>' . $link . '</li>';
		}
		$content .= '</ul>';

        return $content;
	}






	public static function about_widget_function() {
		$picture        = '<a href="https://sabrinazeidan.com/?utm_source=speedguard&utm_medium=sidebar&utm_campaign=avatar" target="_blank"><div id="szpic"></div></a>';
		$hey            = sprintf( __( 'Hey!%1$s My name is %3$sSabrina%4$s. 
		%1$sI speed up websites every day, and I built this plugin because I needed a simple tool to monitor site speed and notify me if something is not right.%2$s
		%1$sHope it will be helpful for you too.%2$s
		%2$s', 'speedguard' ), '<p>', '</p>', '<a href="https://sabrinazeidan.com/?utm_source=speedguard&utm_medium=sidebar&utm_campaign=sabrina" target="_blank">', '</a>' );
		$rate_link      = 'https://wordpress.org/support/plugin/speedguard/reviews/?rate=5#new-post';
		$rate_it        = sprintf( __( 'If you like it, I would greatly appreciate if you add your %1$s★★★★★%2$s to spread the love.', 'speedguard' ), '<a class="rate-link" href="' . $rate_link . '" target="_blank">', '</a>' );
		$translate_link = 'https://translate.wordpress.org/projects/wp-plugins/speedguard/';
		$translate_it   = sprintf( __( 'You can also help to %1$stranslate it to your language%2$s so that more people will be able to use it ❤︎', 'speedguard' ), '<a href="' . $translate_link . '" target="_blank">', '</a>' );
		$cheers         = sprintf( __( 'Cheers!', 'speedguard' ) );
		$content        = $picture . $hey . '<p>' . $rate_it . '</p><p>' . $translate_it . '<p>' . $cheers;
		echo wp_kses_post($content);
	}

	function speedguard_dashboard_widget_function() {
		wp_add_dashboard_widget( 'speedguard_dashboard_widget', __( 'Current Performance', 'speedguard' ), [
				$this,
				'origin_results_widget_function',
			], '', [ 'echo' => 'true' ] );
		// Widget position
		global $wp_meta_boxes;
		$normal_dashboard      = $wp_meta_boxes[ 'dashboard' . ( defined( 'SPEEDGUARD_MU_NETWORK' ) ? '-network' : '' ) ]['normal']['core'];
		$example_widget_backup = [ 'speedguard_dashboard_widget' => $normal_dashboard['speedguard_dashboard_widget'] ];
		unset( $normal_dashboard['speedguard_dashboard_widget'] );
		$sorted_dashboard                             = array_merge( $example_widget_backup, $normal_dashboard );
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}


}

new SpeedGuard_Widgets();
