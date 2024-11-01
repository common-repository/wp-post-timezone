<?php
/*
Plugin Name: WP Post Timezone
Plugin URI: http://www.callum-macdonald.com/code/
Description: Optionally set a timezone for each post as you publish it.
Version: 0.1
Author: Callum Macdonald
Author URI: http://www.callum-macdonald.com/
*/

add_action('admin_init', 'wppt_admin_init');
function wppt_admin_init() {
	
	add_meta_box('tz-for-post', __('Timezone'), 'wppt_meta_box_html', 'post', 'advanced');
	
	// If a custom timezone has been specified, overwride the options
	if (
		isset($_REQUEST['use_custom_post_tz'])
		&&
		( isset($_REQUEST['gmt_offset']) || isset($_REQUEST['timezone_string']) )
		&&
		// This could be more secure
		current_user_can('edit_post', intval($_REQUEST['post_ID']))
	) {
		
		global $wppt_gmt_offset, $wppt_timezone_string;
		$wppt_gmt_offset = (isset($_REQUEST['gmt_offset']) ? $_REQUEST['gmt_offset'] : '');
		$wppt_timezone_string = (isset($_REQUEST['timezone_string']) ? $_REQUEST['timezone_string'] : '');
		
		// If we support fancy timezones but the user gave us a UTC+/-
		// we need to convert it into gmt_offset as WP would do normally.
		if (substr(strtoupper($wppt_timezone_string), 0, 3) == 'UTC') {
			$wppt_gmt_offset = intval(trim(substr($wppt_timezone_string, 3), '+'));
			$wppt_timezone_string = '';
		}
		
		add_filter('pre_option_timezone_string', 'wppt_pre_option_timezone_string');
		add_filter('pre_option_gmt_offset', 'wppt_pre_option_gmt_offset');
	}
	
}

function wppt_pre_option_timezone_string($wppt_timezone_string) {
	global $wppt_timezone_string;	
	return $wppt_timezone_string;
}

function wppt_pre_option_gmt_offset($gmt_offset) {
	global $wppt_gmt_offset;
	return $wppt_gmt_offset;
}

function wppt_meta_box_html() {
	
	?>
	<p><label for="use_custom_post_tz"><input type="checkbox" name="use_custom_post_tz" value="1" id="use_custom_post_tz" /> <?php _e('Set a custom timezone for this post.', 'post-timezone'); ?></label></p>
<table>
	<?php
	// Copied from wp-admin/options-general.php line 17-18 as at r
	// http://core.trac.wordpress.org/browser/tags/3.0.1/wp-admin/options-general.php#L17
	
/* translators: date and time format for exact current time, mainly about timezones, see http://php.net/date */
$timezone_format = _x('Y-m-d G:i:s', 'timezone date format');

	// Copied from wp-admin/options-general.php line 128-246 as at r
	// http://core.trac.wordpress.org/browser/tags/3.0.1/wp-admin/options-general.php#L128
	?>
<tr>
<?php
if ( !wp_timezone_supported() ) : // no magic timezone support here
?>
<th scope="row"><label for="gmt_offset"><?php _e('Timezone') ?> </label></th>
<td>
<select name="gmt_offset" id="gmt_offset">
<?php
$current_offset = get_option('gmt_offset');
$offset_range = array (-12, -11.5, -11, -10.5, -10, -9.5, -9, -8.5, -8, -7.5, -7, -6.5, -6, -5.5, -5, -4.5, -4, -3.5, -3, -2.5, -2, -1.5, -1, -0.5,
	0, 0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5, 5.5, 5.75, 6, 6.5, 7, 7.5, 8, 8.5, 8.75, 9, 9.5, 10, 10.5, 11, 11.5, 12, 12.75, 13, 13.75, 14);
foreach ( $offset_range as $offset ) {
	if ( 0 < $offset )
		$offset_name = '+' . $offset;
	elseif ( 0 == $offset )
		$offset_name = '';
	else
		$offset_name = (string) $offset;

	$offset_name = str_replace(array('.25','.5','.75'), array(':15',':30',':45'), $offset_name);

	$selected = '';
	if ( $current_offset == $offset ) {
		$selected = " selected='selected'";
		$current_offset_name = $offset_name;
	}
	echo "<option value=\"" . esc_attr($offset) . "\"$selected>" . sprintf(__('UTC %s'), $offset_name) . '</option>';
}
?>
</select>
<?php _e('hours'); ?>
<span id="utc-time"><?php printf(__('<abbr title="Coordinated Universal Time">UTC</abbr> time is <code>%s</code>'), date_i18n( $time_format, false, 'gmt')); ?></span>
<?php if ($current_offset) : ?>
	<span id="local-time"><?php printf(__('UTC %1$s is <code>%2$s</code>'), $current_offset_name, date_i18n($time_format)); ?></span>
<?php endif; ?>
<br />
<span class="description"><?php _e('Unfortunately, you have to manually update this for daylight saving time. The PHP Date/Time library is not supported by your web host.'); ?></span>
</td>
<?php
else: // looks like we can do nice timezone selection!
$current_offset = get_option('gmt_offset');
$tzstring = get_option('timezone_string');

$check_zone_info = true;

// Remove old Etc mappings.  Fallback to gmt_offset.
if ( false !== strpos($tzstring,'Etc/GMT') )
	$tzstring = '';

if ( empty($tzstring) ) { // Create a UTC+- zone if no timezone string exists
	$check_zone_info = false;
	if ( 0 == $current_offset )
		$tzstring = 'UTC+0';
	elseif ($current_offset < 0)
		$tzstring = 'UTC' . $current_offset;
	else
		$tzstring = 'UTC+' . $current_offset;
}

?>
<th scope="row"><label for="timezone_string"><?php _e('Timezone') ?></label></th>
<td>

<select id="timezone_string" name="timezone_string">
<?php echo wp_timezone_choice($tzstring); ?>
</select>

    <span id="utc-time"><?php printf(__('<abbr title="Coordinated Universal Time">UTC</abbr> time is <code>%s</code>'), date_i18n($timezone_format, false, 'gmt')); ?></span>
<?php if ( get_option('timezone_string') || !empty($current_offset) ) : ?>
	<span id="local-time"><?php printf(__('Local time is <code>%1$s</code>'), date_i18n($timezone_format)); ?></span>
<?php endif; ?>
<br />
<span class="description"><?php _e('Choose a city in the same timezone as you.'); ?></span>
<?php if ($check_zone_info && $tzstring) : ?>
<br />
<span>
	<?php
	// Set TZ so localtime works.
	date_default_timezone_set($tzstring);
	$now = localtime(time(), true);
	if ( $now['tm_isdst'] )
		_e('This timezone is currently in daylight saving time.');
	else
		_e('This timezone is currently in standard time.');
	?>
	<br />
	<?php
	if ( function_exists('timezone_transitions_get') ) {
		$found = false;
		$date_time_zone_selected = new DateTimeZone($tzstring);
		$tz_offset = timezone_offset_get($date_time_zone_selected, date_create());
		$right_now = time();
		foreach ( timezone_transitions_get($date_time_zone_selected) as $tr) {
			if ( $tr['ts'] > $right_now ) {
			    $found = true;
				break;
			}
		}

		if ( $found ) {
			echo ' ';
			$message = $tr['isdst'] ?
				__('Daylight saving time begins on: <code>%s</code>.') :
				__('Standard time begins  on: <code>%s</code>.');
			// Add the difference between the current offset and the new offset to ts to get the correct transition time from date_i18n().
			printf( $message, date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $tr['ts'] + ($tz_offset - $tr['offset']) ) );
		} else {
			_e('This timezone does not observe daylight saving time.');
		}
	}
	// Set back to UTC.
	date_default_timezone_set('UTC');
	?>
	</span>
<?php endif; ?>
</td>

<?php endif; ?>
</tr>
</table>

<p><?php _e('If you edit the date of this post in the future, you will need to re-enter the timezone manually here.', 'post-timezone'); ?></p>
	<?php
}

