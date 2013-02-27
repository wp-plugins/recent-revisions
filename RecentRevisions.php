<?php
/***************************************************************************

Plugin Name:  Recent Revisions
Plugin URI:   http://sarahbird.org/recent-revisions
Version:      1.1.1
Author:       Sarah Bird, Benjamin Pick
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Author URI:   http://www.sarahbird.org/
Description:  Shows an overview of your recent post revisions on your administration dashboard.
Ideal for sites with content that gets updated by multiple authors where you want to keep an eye
on the changes that have been made. Number of revisions displayed is configurable as well whether
you want the author and the date shown. Posts can be displayed in GMT to enable effective
collaboration across timezones.

**************************************************************************/

/***************************************************************************

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
The license is also available at http://www.gnu.org/copyleft/gpl.html

**************************************************************************/


// Load up the localization file if we're using WordPress in a different language
// Place it in this plugin's folder and name it "recent-revisions-[value in wp-config].mo"
load_plugin_textdomain( 'recent-revisions', false, 'recent-revisions/' );

function RecentRevisions() {

	global $wpdb;

	$widget_options = RecentRevisions_Options();

	$request = 	"SELECT $wpdb->posts.*, display_name as authorname " .
  				"FROM $wpdb->posts LEFT JOIN $wpdb->users ON $wpdb->posts.post_author=$wpdb->users.ID " .
  				"WHERE (post_type='post' OR (post_type ='revision' AND post_name NOT LIKE '%-autosave')) " .
				"AND post_status IN('publish', 'inherit')";
	$request .= 	"ORDER BY post_modified_gmt DESC " .
  				"LIMIT ".$widget_options['items'];
	$posts = $wpdb->get_results($request);

	// Use Date format of WP Preferences
	$date_format = get_option('date_format') . ' ' . get_option('time_format');
	if ( $posts ) {
		echo "				<ul id='recent-revisions-list'>\n";

		foreach ( $posts as $post ) {
			$post_id = ($post->post_type == 'post') ? $post->ID : $post->post_parent;

			//make the line
			$title = get_the_title($post_id);
			$post_meta = sprintf('%s', '<a href="post.php?post=' . $post_id . '&action=edit">' . ($title ? $title : __('(no title)')) . '</a> ' );

			if($widget_options['showauthor']) {
				$post_meta.= sprintf( __('by %s', 'recent-revisions'),'<strong>'. $post->authorname .'</strong> ' );
			}

			if($widget_options['showdatetime']) {
				//	$post_meta .= $post->post_modified_gmt;
				$post_meta.= sprintf( __('&#8212; %s', 'recent-revisions'),'' . get_post_modified_time($date_format, $widget_options['tz_gmt'], $post->ID, true));
				if ($widget_options['tz_gmt'])
				$post_meta .= ' GMT';
			}

			if ($widget_options['showdiff']) {
				$request = "SELECT ID FROM $wpdb->posts WHERE post_type='revision' AND post_parent=" . $post_id . ' AND post_modified_gmt < "' . $post->post_modified_gmt . '" ORDER BY post_modified_gmt DESC LIMIT 1 ';
				$rev = $wpdb->get_row($request);
				if (is_null($rev)) // No previous revision found
				$post_meta .= ' (' . __('new', 'recent-revisions') . ')';
				else
				{
					$prevId = $rev->ID;
					$post_meta .= ' (<a href="revision.php?action=diff&post_type=page&right=' . $post->ID . '&left=' . $prevId . '">' . __('diff', 'recent-revisions') . '</a>)';
				}
			}
				
			//print it out
			?>
<li class='post-meta'><?php echo $post_meta; ?>
</li>
			<?php
		}
		//end the line
		echo "</ul>\n";

		//but if you got no lines....
	} else {
		echo '<p>' . __( "You don't have any revisions! Are revisions enabled in wp-config.php? Check that WP_POST_REVISIONS is true or >0. ", 'recent-revisions' ) . "</p>\n";
		//echo "<p> You don't have any revisions! Are revisions enabled in wp-config.php? Check that WP_POST_REVISIONS is true or >0. </p>\n";
	}

}


function RecentRevisions_Init() {
	wp_add_dashboard_widget( 'RecentRevisions', __( 'Recent Revisions', 'recent-revisions' ), 'RecentRevisions', 'RecentRevisions_Setup');
}

function RecentRevisions_Options() {
	$defaults = array( 'items' => 25, 'showdatetime' => 1, 'showauthor' => 1, 'tz_gmt' => 0, 'showdiff' => 0);
	if ( ( !$options = get_option( 'RecentRevisions' ) ) || !is_array($options) )
	$options = array();
	return array_merge( $defaults, $options );
}

function RecentRevisions_Setup() {

	$options = RecentRevisions_Options();


	if ( 'post' == strtolower($_SERVER['REQUEST_METHOD']) && isset( $_POST['widget_id'] ) && 'RecentRevisions' == $_POST['widget_id'] ) {
		foreach ( array( 'items', 'showdatetime', 'showauthor', 'tz_gmt', 'showdiff' ) as $key )
		$options[$key] = (int) @$_POST[$key];
		update_option( 'RecentRevisions', $options );
	}

	?>
<p>
	<label for="items"><?php _e('How many recent posts would you like to display?', 'recent-revisions' ); ?>
		<select id="items" name="items">
		<?php
		for ( $i = 5; $i <= 50; $i = $i + 5 )
		echo "<option value='$i'" . ( $options['items'] == $i ? " selected='selected'" : '' ) . ">$i</option>";
		?>
	</select> </label>
</p>

<p>
	<label for="showauthor"> <input id="showauthor" name="showauthor"
		type="checkbox" value="1"
		<?php if ( 1 == $options['showauthor'] ) echo ' checked="checked"'; ?> />
		<?php _e('Show revision author?', 'recent-revisions' ); ?> </label>
</p>

<p>
	<label for="showdatetime"> <input id="showdatetime" name="showdatetime"
		type="checkbox" value="1"
		<?php if ( 1 == $options['showdatetime'] ) echo ' checked="checked"'; ?> />
		<?php _e('Show revision date and time?', 'recent-revisions' ); ?> </label>
</p>
<p>
	<label for="tz_gmt"> <input id="tz_gmt" name="tz_gmt" type="checkbox"
		value="1"
		<?php if ( 1 == $options['tz_gmt'] ) echo ' checked="checked"'; ?> />
		<?php _e('Show date/time in GMT, not local timezone?', 'recent-revisions' ); ?>
	</label>
</p>
<p>
	<label for="showdiff"> <input id="showdiff" name="showdiff"
		type="checkbox" value="1"
		<?php if ( 1 == $options['showdiff'] ) echo ' checked="checked"'; ?> />
		<?php _e('Show link to view differences?', 'recent-revisions' ); ?> </label>
</p>

		<?php
}
//integrate into dashboard
add_action('wp_dashboard_setup', 'RecentRevisions_Init');
?>
