<?php 
/*
Plugin Name: Twitter SP2
Plugin URI: http://deceblog.net/2009/04/twitter-sp2/
Description: Trimite pe Twitter postul publicat cu link scurtat prin <a href="http://sp2.ro">sp2.ro</a>. Textul trimis alaturi de link se poate configura foarte usor.
Author: Dan-Lucian Stefancu
Version: 0.4
Author URI: http://deceblog.net/
*/

if (!class_exists('Snoopy')) {
	require_once(ABSPATH . 'wp-includes/class-snoopy.php');
}

define('SP2_API_KEY', 'd3c8'); //sp2 api key
global $wp_version;

add_option('sp2_post_on_twitter', 1); // default value for autoposting on twitter
add_option('sp2_text_to_send', 'post_title'); // default value of the text to send on twitter
add_option('sp2_install_date', current_time('mysql')); //we need some time variable for a later hack. 
/*
sp2_install_date will be compared with post date to find new posts. 
publish_post hook runs even if the user is updating an old published post.
there is no hook that runs only on new posts until wordpress 2.8
*/

add_action('admin_menu', 'add_sp2_api_page'); // adds the options page
add_action('admin_menu', 'add_sp2_add_switch'); //adds an option for the post

add_action('admin_notices', 'sp2_custom_error'); // a new error msg

add_action('save_post', 'sp2_switch_save'); //stores the switch in a custom field
add_action('admin_head', 'sp2_javascript'); //adds some javascript in admin head

// adds the hook if option is 1 and checks for twitter password and username
if (get_option('sp2_post_on_twitter') == 1) {
	if ($wp_version < 2.8) {
		add_action('publish_post', 'sp2_post_on_twitter');
	} else {
		add_action('draft_to_publish', 'sp2_post_on_twitter');
		add_action('future_to_publish', 'sp2_post_on_twitter');
		add_action('pending_to_publish', 'sp2_post_on_twitter');
	}
	
	$twitter_username = get_option('sp2_twitter_username');
	$twitter_password = get_option('sp2_twitter_password');
	
	// displays an error message in dashboard if no username set and autoposting on twitter is enabled
	if (empty($twitter_username)) {
		add_action('admin_notices','sp2_twitter_username_error');
	} 
	
	// displays an error message in dashboard if no password set and autoposting on twitter is enabled
	if (empty($twitter_password)) {
		add_action('admin_notices','sp2_twitter_password_error');
	} 

}

function sp2_javascript() { ?>
<script type="text/javascript">
	function sp2_title_change(){
		var newOpt=document.getElementById("sp2_text_to_send");
		if (newOpt.selectedIndex==3){
			var sel1=document.getElementById("sp2_custom_text_to_send");
			sel1.style.display = "block";
			var sel2=document.getElementById("sp2_custom_title_label");
			sel2.style.display = "block";
			var sel3=document.getElementById("sp2_custom_tags");
			sel3.style.display = "block";
			
			
		} else {
			var sel1=document.getElementById("sp2_custom_text_to_send");
			sel1.style.display = "none";
			var sel2=document.getElementById("sp2_custom_title_label");
			sel2.style.display = "none";
			var sel3=document.getElementById("sp2_custom_tags");
			sel3.style.display = "none";
		}
	}
</script>

<?php
}


function get_custom_excerpt($post_id, $limit = 100) {
	$post = get_post($post_id); //gets the post based on id
	$text = $post->post_content; // gets the contents
	$text = strip_shortcodes($text);
	$text = wptexturize($text);
	$text = rtrim($text, "\s\n\t\r\0\x0B");
	$text = str_replace(']]>', ']]&gt;', $text);
	$text = strip_tags($text);
	$text = substr($text, 0, $limit); //trims the content after 100 characters or the $limit
	$text .= "...";
	
	return $text;
}

function sp2_show_link($id = 0) {
	$sp2_link = get_post_custom_values('sp2_link', $post_id);
	if ($sp2_link)
		echo '<a href="http://twitter.com/?status='.urlencode(stripslashes(sp2_get_text_to_send($id))).'">Trimite pe Twitter</a>';
}

// returns the short link + the text to send selected in options
function sp2_get_text_to_send($post_id) {
	
	$sp2_link = get_post_custom_values('sp2_link', $post_id); //checks the custom field for the link
	
	$short_url = $sp2_link[0]; //the variable will be used later if exists
		
	if ( $sp2_link == FALSE ) { // if no link found get one via sp2.ro
		$url = get_permalink($post_id);

		$snoop = new Snoopy;
		$snoop->agent = 'Twitter SP2 http://deceblog.net/2009/04/twitter-sp2/';
		$snoop->submit('http://sp2.ro/api/index/index', array( 'method' => 'getShort', 'key' => SP2_API_KEY, 'url' => $url ));
		/*		
		Returns
		<value>link</value>
		<code>error/success code</code>
		<limit>remaining interrogations</limit>
		<status>true/false</status>
		*/
		
		//simplexml_load_string doesn't work under php 4
		$response = simplexml_load_string($snoop->results); // sores the xml values in an array

		/*		
		Codes
		200 - Invalid API key
		201 - Invalid URL
		202 - API limits exceeded, please contact the admins
		666 - This is the code for a STRANGE error, please contact the admins
		100 – success
		*/
		
		if (($response->code == 100) && ($response->limit > 0)) { //if no error and limit not passed
			$short_url = $response->value; // overrites the variable
			add_post_meta($post_id, 'sp2_link', $short_url); //the shortened link is stored in a custom field
		} else {
			switch ($response->code) {
				case 200:
					$sp2_error = '<strong>SP2.ro service error</strong>: Invalid API key';
					break;
				case 201:
					$sp2_error = '<strong>SP2.ro service error</strong>: Invalid post URL. Maybe you\'re on localhost?';
					break;
				case 202:
					$sp2_error = '<strong>SP2.ro service error</strong>: API limits exceeded, please contact the admins';
					break;
				case 666:
					$sp2_error = '<strong>SP2.ro error</strong>: STRANGE error, please contact the admins';
					break;
				default:
					$sp2_error = '<strong>Wordpress error</strong>: Snoopy not working, contact your blog host administrator';
					break;
			}
			add_post_meta($post_id, 'sp2_error', $sp2_error);
			return false; 
		}
	}
	
	$text_to_send = get_option('sp2_text_to_send'); //get the option
	$custom_text_to_send = get_option('sp2_custom_text_to_send'); //get the custom text set in options if exists
	$title = get_the_title($post_id);
	
	switch ($text_to_send) {
		case 'post_title':
			$return_text = $title . " " . $short_url;
			break;
		case 'post_excerpt':
			$return_text = get_custom_excerpt($post_id) . " " . $short_url;
			break;
		case 'title_excerpt':
			$text = $title . ': ';
			$limit = 110 - strlen($text);
			$text .= get_custom_excerpt($post_id, $limit);
			$return_text = $text . " " . $short_url;
			/*
			140 chars max on twitter
			20 chars the sp2.ro link
			2 chars ": " after the title
			1 char " " after the text
			3 chars "..." after the text - generated by get_custom_excerpt
			---
			114 chars left for the text
			110 used because of the nicer number
			*/
			break;
		case 'custom':
		
			$text = $custom_text_to_send;
			if (strpos($text, "%titlu%") !== FALSE)
	  			$text = str_replace("%titlu%", $title, $text); //replaces %titlu% with the title of the post
	  		
			if (strpos($text, "%link%") !== FALSE)
	  			$text = str_replace("%link%", $short_url, $text); //replaces the %link% with the shortened version of it
			
			if (strpos($text, "%fragment%") !== FALSE) {
				$limit = 145 - strlen($text); // why did I putted 145 here?? note to self: comment your code
				
				$text = str_replace("%fragment%", get_custom_excerpt($post_id, $limit), $text);
				
			}
	  
			$return_text = $text;
			break;
	}
	
	
	
	return $return_text;
}

function add_sp2_add_switch() {
	if( function_exists( 'add_meta_box' )) {
		add_meta_box( 'sp2_switch_id', 'Trimite pe Twitter', 'sp2_switch', 'post', 'side' );
	} 
}
   
function sp2_switch() { 
	global $post;
	
	if (get_option('sp2_install_date') > $post->post_date) {
		delete_post_meta($post->ID, 'sp2_disable_updates');
		add_post_meta($post->ID, 'sp2_disable_updates', 1);
	}	
	$sp2_disable_updates = get_post_custom_values('sp2_disable_updates', $post->ID);
?>
	<label for="myplugin_new_field">Nu trimite acest post pe Twitter</label>
	<input type="checkbox" name="sp2_disable_updates" value="1" <?php if ($sp2_disable_updates[0] == 1) { ?>checked="checked"<?php } ?> />

<?php }

//save the shitch option in a custom field
function sp2_switch_save($post_id) {
	if (isset($_POST['sp2_disable_updates'])) {
		delete_post_meta($post_id, 'sp2_disable_updates');
		add_post_meta($post_id, 'sp2_disable_updates', $_POST['sp2_disable_updates']);
	} else {
		delete_post_meta($post_id, 'sp2_disable_updates');
		add_post_meta($post_id, 'sp2_disable_updates', 0);
	}
}


function sp2_post_on_twitter($post_id) {
	global $twitter_username, $twitter_password;
	
	$sp2_disable_updates = get_post_custom_values('sp2_disable_updates', $post_id);
	if ($sp2_disable_updates[0] == 1) { //if twitter updates disabled for this post
		return;
	}
	
	
	$sp2_tweet_sent = get_post_custom_values('sp2_tweet_sent', $post_id);
	if ($sp2_tweet_sent[0] == 1) { //if twitter update already sent
		return;
	}
	
	$post = get_post($post_id);
	
	if ($post->post_status == 'private') { //if post is published and not private
		return;
	}
	
	if ( empty($twitter_username) || empty($twitter_password)) {
		
		return false;
	}
	
	$tweet = sp2_get_text_to_send($post_id); // get the text + link to send
	
	if ($tweet == FALSE) { //if errors occured getting the text + link break

		return false;
	}
	
	$snoop = new Snoopy;
	$snoop->agent = 'Twitter SP2 http://deceblog.net/2009/04/twitter-sp2/';
	$snoop->rawheaders = array(
		'X-Twitter-Client' => 'Twitter SP2',
		'X-Twitter-Client-Version' => '0.1',
		'X-Twitter-Client-URL' => 'http://deceblog.net/twitter-sp2.xml'
	);
	$snoop->user = $twitter_username;
	$snoop->pass = $twitter_password;
	$snoop->submit( 'http://twitter.com/statuses/update.json', array( 'status' => $tweet, 'source' => 'Twitter SP2') );
	$count = 1;
	
	if (strpos($snoop->response_code, '408')) { //if twitter says timeout, try again up to 4 times
		while ($count <= 3) {
			$snoop->submit( 'http://twitter.com/statuses/update.json', array( 'status' => $tweet, 'source' => 'Twitter SP2') );
			$count++;
		}
	}
	
	// add_post_meta($post_id, 'sp2_rezultat', $snoop->results); //stores the whole json result in a custom field - for debugging purposes only

	if (strpos($snoop->response_code, '200')) { //if success
		add_post_meta($post_id, 'sp2_tweet_sent', '1'); // stores a "sent" variable in a custom field
		delete_post_meta($post_id, 'sp2_error');
		add_post_meta($post_id, 'sp2_msg_not_shown', '1');
		add_post_meta($post_id, 'sp2_times_tried', $count); // for debugging purposes only
		return true;
	}  else {
		add_post_meta($post_id, 'sp2_error', '<strong>Twitter service error</strong>: ' . $snoop->response_code); // stores the twitter response code in a custom field sp2_error
		add_post_meta($post_id, 'sp2_times_tried', $count); // for debugging purposes only
		return false;
	}
	
	return false;
}

function sp2_custom_error() {
	global $post, $parent_file, $editing;

	if ($parent_file == 'edit.php' && $editing == true) {
		$success = get_post_custom_values('sp2_tweet_sent', $post->ID);
		$message_not_shown = get_post_custom_values('sp2_msg_not_shown', $post->ID);
		$the_error = get_post_custom_values('sp2_error', $post->ID); 
		if ($the_error) 
			echo "<div class='error fade'><p>".$the_error[0]."</p></div>";
		if ($success && $message_not_shown) {
			echo "<div class='updated fade' style='color:#33CCFF; font-weight:bold;'><p>Postul este pe Twitter</p></div>";
			delete_post_meta($post_id, 'sp2_not_shown');
		}
	}
}

//displays dashboard error on empty username
function sp2_twitter_username_error() {
	echo "<div id='update-nag'>Nu uita <a href='options-general.php?page=twitter-sp2/twitter-sp2.php'>sa introduci</a> utilizatorul de Twitter.</div>";
}

//displays dashboard error on empty password
function sp2_twitter_password_error() {
	echo "<div id='update-nag'>Nu uita <a href='options-general.php?page=twitter-sp2/twitter-sp2.php'>sa introduci</a> parola de Twitter.</div>";
}

//the function that actually adds the options page
function add_sp2_api_page() {
	if (function_exists('add_options_page')) {
		add_options_page('Optiuni Twitter SP2', 'Twitter SP2', 8, __FILE__, 'sp2_api_page');
	}
}

// the options page itself
function sp2_api_page() { ?>
	<div class="wrap">
	<h2>Optiuni Twitter SP2</h2>
	
	<form method="post" action="options.php">
		<?php wp_nonce_field('update-options'); ?>
		
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="sp2_post_on_twitter">Trimite posturile noi pe Twitter?</label></th>
				<td><input type="checkbox" name="sp2_post_on_twitter" id="sp2_post_on_twitter" value="1" <?php if (get_option('sp2_post_on_twitter') == 1) { ?>checked="checked"<?php } ?> /></td>
			</tr>
			
			<tr valign="top">
				<th scope="row"><label for="sp2_twitter_username">Utilizator Twitter</label></th>
				<td><input type="text" name="sp2_twitter_username" id="sp2_twitter_username" value="<?php echo get_option('sp2_twitter_username'); ?>" /></td>
			</tr>
			
			<tr valign="top">
				<th scope="row"><label for="sp2_twitter_password">Parola Twitter</label></th>
				<td><input type="password" name="sp2_twitter_password" id="sp2_twitter_password" value="<?php echo get_option('sp2_twitter_password'); ?>" /></td>
				
			</tr>
			

			
			<tr valign="top">
				<th scope="row"><label for="sp2_text_to_send">Ce text sa fie trimis pe Twitter?</label></th>
				<td><select name="sp2_text_to_send" id="sp2_text_to_send" onchange="sp2_title_change()">							
						<option <?php if (get_option('sp2_text_to_send') == 'post_title') { ?> selected="selected" <?php } ?> value="post_title">Titlul postului + link</option>
						<option <?php if (get_option('sp2_text_to_send') == 'post_excerpt') { ?> selected="selected" <?php } ?> value="post_excerpt">Fragment din post + link</option>
						<option <?php if (get_option('sp2_text_to_send') == 'title_excerpt') { ?> selected="selected" <?php } ?> value="title_excerpt">Titlu: fragment din post + link</option>
						<option <?php if (get_option('sp2_text_to_send') == 'custom') { ?> selected="selected" <?php } ?> value="custom">Un text la alegere</option>
					</select>
				</td>
			</tr>
			
			<tr valign="top">
				<th scope="row"><label for="sp2_custom_text_to_send" id="sp2_custom_title_label" <?php if (get_option('sp2_text_to_send') != 'custom') { ?> style="display:none;" <?php } ?>>Configurare text</label></th>
				<td><input class="regular-text" type="text" <?php if (get_option('sp2_text_to_send') != 'custom') { ?> style="display:none;"<?php } ?> name="sp2_custom_text_to_send" id="sp2_custom_text_to_send" value="<?php echo get_option('sp2_custom_text_to_send'); ?>" /></td>
			</tr>
			
			<tr valign="top">
				<th></th><td><span id="sp2_custom_tags" <?php if (get_option('sp2_text_to_send') != 'custom') { ?> style="display:none;"<?php } ?>>Se pot folosi variabilele: <code>%titlu%</code>, <code>%fragment%</code> si <code>%link%</code></span></td>
			</tr>
			
		</table>
		
		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="page_options" value="sp2_post_on_twitter,sp2_twitter_username,sp2_twitter_password,sp2_text_to_send,sp2_custom_text_to_send" />
		
		<?php // fields that have to be updated by wordpress ?>
		
		<p class="submit">
		<input type="submit" class="button-primary" value="Salveaza" />
		</p>
	</form>
	</div><!-- //wrap -->
	
<?php } ?>