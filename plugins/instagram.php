<?php
 
/**
 *
 * INSTAGRAM FEED PARSER
 * Copyrights 2018 Rascals Themes
 *
*/


// Get settings
require_once('config.php');

// Cache file
$cache_file = '../cache/instagram.txt';

// Time that the cache was last filled.
$cache_file_created = ((@file_exists($cache_file))) ? @filemtime($cache_file) : 0;

// A flag so we know if the feed was successfully parsed.
$instagram_found = false;

// If keys are empty
if ( $user_id == '' ) {

	$instagram_found = false;


// Show file from cache if still valid.
} else if (time() - $instagram_cachetime < $cache_file_created) {

	$instagram_found = true;

	// Display tweets from the cache.
	@readfile($cache_file);	

} else {

	/* Remove @ from user id */
    $check_char = strpos( $user_id, '@' );
    if ( $check_char !== false ) {
        $user_id = substr( $user_id, $check_char + 1 );
    }

    $url = 'https://www.instagram.com/' . $user_id;
   	$ch = curl_init();
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	//mergem dupa redirecturi
	curl_setopt ($ch,  CURLOPT_FOLLOWLOCATION, true);
	curl_setopt ($ch,  CURLOPT_MAXREDIRS, 3); //max redirects
	curl_setopt ($ch,  CURLOPT_ENCODING, ''); //folosim compresia - daca e empty trimite toate formele de compresie suportate
	//timeout? - 300 sec = 5 min
	curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 180); //Fail if a web server doesn�t respond to a connection within a time limit (seconds).
	curl_setopt($ch, CURLOPT_TIMEOUT, 180); //Fail if a web server doesn�t return the web page within a time limit (seconds).
	curl_setopt($ch, CURLOPT_HEADER, false);
	// misc
	curl_setopt($ch,CURLOPT_AUTOREFERER,true); //The referer is a URL for the web page that linked to the requested web page. When following redirects, set this to true and CURL automatically fills in the URL of the page being redirected away from.
	curl_setopt($ch,CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0');
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$request_output  = curl_exec($ch);
			;
	//error checking
	if ( $request_output === false ) {
		echo 'curl_exec returned FALSE (AKA Error) curl_error:' . curl_error($ch) . ' curl_getinfo attached to this message', curl_getinfo($ch);
		curl_close($ch);
		return false;
	} else {
		curl_close($ch);

			// get the serialized data string present in the page script
        $pattern = '/window\._sharedData = (.*);<\/script>/';
        preg_match( $pattern, $request_output, $matches );

        if ( ! empty( $matches[1] ) ) {
            $request_output = $matches[1];

            $json = json_decode( $request_output, true );

           
	        if ( $json === null and json_last_error() !== JSON_ERROR_NONE ) {
	             echo 'Error decoding the instagram json';
	             return;
	        } 

	       	if ( !isset( $json['entry_data']['ProfilePage'][0]['graphql']['user'] ) ) {
           		echo 'Instagram data is not set, plese check your ID';
           		return;
        	}
            
        } else {
            echo 'Instagram error ID';
            return;
        }
	}

	$instafeed_data['user'] = $json['entry_data']['ProfilePage'][0]['graphql']['user'];

	$save_data = $instafeed_data['user'];
	$output = '';
	$images_nr  = 10;
	// Start output buffering.
	ob_start();
	// Render images
    if ( isset( $save_data['edge_owner_to_timeline_media']['edges'] ) ) {
        $image_count = 0;
        $instagram_found = true;
        $output .= '<div class="instagram-images">';
        foreach ( $save_data['edge_owner_to_timeline_media']['edges'] as $image ) {
            if ( isset( $image['node']['shortcode'] ) && isset( $image['node']['thumbnail_src'] ) ) {
                $output .= '
                    <div class="instagram-image">
                        <a href="https://www.instagram.com/p/' . $image['node']['shortcode'] . '" target="_blank">
                            <img src="' . $image['node']['thumbnail_src'] . '" alt="Instagram image">
                        </a>
                        <div class="meta">
                            <div>
                                <span class="comments"><i class="icon icon-comment-o"></i>' . format_number($image['node']['edge_media_to_comment']['count'] ) . '</span>
                                <span class="likes"><i class="icon icon-heart-o"></i>' . format_number( $image['node']['edge_media_preview_like']['count'] ) . '</span>
                            </div>
                        </div>

                    </div>';
            }

            $image_count ++;
            if ( $image_count == $images_nr ) {
                break;
            }

        }
        $output .= '</div>';
    }

    echo $output;
	
	// Generate a new cache file.
	$file = @fopen($cache_file, 'w');

	// Save the contents of output buffer to the file, and flush the buffer. 
	@fwrite($file, $output); 
	@fclose($file); 
	ob_end_flush();

}

function format_number($num) {

    if ( $num >= 1000000 ) {
        $num = number_format_i18n($num / 1000000, 1) . 'm';
    } elseif ( $num >= 10000 )  {
        $num = number_format_i18n($num / 1000, 1) . 'k';
    } else {
        $num = number_format_i18n($num);
    }
    return $num;
}

function number_format_i18n( $number, $decimals = 0 ) {
 
    $formatted = number_format( $number,  abs( intval( $decimals ) ) );
   return $formatted;
    
}
// In case the RSS feed did not parse or load correctly, show a link to the Twitter account.
if (  ! $instagram_found ){
	echo $tweets = "Oops, our Instagram feed is unavailable at the moment - <a href='http://twitter.com/$user_id/'>Follow me on Instagram!</a>";
}
