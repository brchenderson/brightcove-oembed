<?php
/*
Plugin Name: Brightcove embed for DFM
Plugin URI: http://twincities.com
Description: Embed Brightcove videos in Wordpress posts.
Version: 1.0
Author: Brian Henderson
Author URI: http://www.twincities.com
License: GPL2
*/

/* Search for Brightcove links to convert to embed code */

wp_embed_register_handler('brightcoveblogsMCEmbed', '#https?://(www\.)?bcove.me/*#i', 'wp_embed_handler_brightcoveblogsMC' );


	
function wp_embed_handler_brightcoveblogsMC( $matches, $attr, $url, $rawattr ) {


	/* Follow the short RL back to Brightcove to get the video ID and player key*/
	$cr = curl_init($url); 
	curl_setopt($cr, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($cr, CURLOPT_FOLLOWLOCATION, 1); 	 
	curl_exec($cr); 
	$info = curl_getinfo($cr);
	//echo "url=".$info["url"] . "<br>";

	$BCquery = parse_url($info["url"], PHP_URL_QUERY);
	$BCpath = parse_url($info["url"], PHP_URL_PATH);
	$BCquery = explode("=" , $BCquery);
	$BCpath = explode("/services/player/bcpid" , $BCpath);
	$playerkey = $BCquery[1];
	$vidid = $BCquery[2];
	
	/* Does the post have a thumbnail? If not, read the Brightcove permalink and attach the video still to the Wordpress post */
	if (!has_post_thumbnail()) {

		$sites_html = file_get_contents($info["url"]);
		$patt = '/og\:image[\'\"]\s*content=[\'\"](http:\/\/.+)[\'\"]/';
		$matches = array();
		preg_match($patt, $sites_html, $matches);
		$videoThumb = $matches[1]; // get the og:image image

		if ($videoThumb) {

			$videoThumb = preg_replace('/\?.*$/', '', $videoThumb); // image url can include a query string that messes up the file name
			$wp_filetype = wp_check_filetype($videoThumb, null);
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => $videoThumb,
				'post_content' => '',
				'post_status' => 'inherit'
			);

			function curl_fetch_image($url) {
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					$image = curl_exec($ch);
					curl_close($ch);
					return $image;
				}

			$videoStill = curl_fetch_image($videoThumb);
			$post_id = get_the_ID();
			$uploads = wp_upload_dir();
			$filename = wp_unique_filename( $uploads['path'], basename($videoThumb ), $unique_filename_callback = null );
			$fullpathfilename = $uploads['path'] . "/" . $filename;
			$fileSaved = file_put_contents($uploads['path'] . "/" . $filename, $videoStill);
							if ( !$fileSaved ) {
								throw new Exception("The file cannot be saved.");
							}

			$attach_id = wp_insert_attachment( $attachment, $fullpathfilename, $post_id );

			// you must first include the image.php file
			// for the function wp_generate_attachment_metadata() to work
			require_once(ABSPATH . 'wp-admin/includes/image.php');
			$attach_data = wp_generate_attachment_metadata( $attach_id, $fullpathfilename);
			wp_update_attachment_metadata( $attach_id, $attach_data );

			// add featured image to post
			add_post_meta($post_id, '_thumbnail_id', $attach_id);

		}

	}

?>


<div class="clear"></div>		
<div class="videobox">
<!-- Start of Brightcove Player -->

<div style="display:none">

</div>

<!--
By use of this code snippet, I agree to the Brightcove Publisher T and C 
found at https://accounts.brightcove.com/en/terms-and-conditions/. 
-->

<script language="JavaScript" type="text/javascript" src="http://admin.brightcove.com/js/BrightcoveExperiences.js"></script>

<object id="myExperience<?php echo $vidid?>" class="BrightcoveExperience">
  <param name="bgcolor" value="#FFFFFF" />
  <param name="width" value="600" />
  <param name="height" value="335" />
  <param name="wmode" value="transparent" />
  <param name="playerID" value="<?php echo $BCpath[1] ?>" />
  <param name="playerKey" value="<?php echo $playerkey ?>" />
  <param name="isVid" value="true" />
  <param name="isUI" value="true" />
  <param name="dynamicStreaming" value="true" />
  <param name="@videoPlayer" value="<?php echo $vidid?>" />
</object>

<?php


?>

<!-- 
This script tag will cause the Brightcove Players defined above it to be created as soon
as the line is read by the browser. If you wish to have the player instantiated only after
the rest of the HTML is processed and the page load is complete, remove the line.
-->
<script type="text/javascript">brightcove.createExperiences();</script>

<!-- End of Brightcove Player -->

</div>



<?php 



$embed = "";

wp_embed_unregister_handler('brightcoveblogsMCEmbed'); // Only want the embed to occur in body of post
return apply_filters( 'embed_brightcoveblogsMC', $embed, $matches, $attr, $url, $rawattr );

}

?>