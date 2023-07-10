<?php 

// This will sync a podcast to your system. 
// This is designed to be run as a cronjob that reads from a the json file - feeds.json.
// The content of each XML feed that is being parsed is temporarily saved locally to parse.

// Requires php5.6-cli, php5.6-xml, php5.6-curl, system version of curl, and simplepie.
// It requires PHP 5.6 because its fine and I don't care for this use case.  
// SimplePie breaks in PHP 8, with this version, and I don't care to fix it yet.

// This was written very very quickly, and made to work, and that's about it.  
// Nearly all modern software is terrible, and none of the podcast software was simply
// downloading podcasts.  So, whatever.  I'll do it myself. 


error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DOWNLOAD_DIRECTORY', sprintf(
    "%s/Downloads",
    getcwd()
));



$feed_array = generate_feed_array('feeds.json');
foreach ($feed_array as $this_feed) {
    do_the_sync($this_feed);
    //die;
}

exit;

// ----------------------------------------------------------------------------------------------------------------


function generate_feed_array($filename) {
    $feed_file_string = file_get_contents($filename);
    $feed_array = json_decode($feed_file_string, true);

    if (false === $feed_array) {
        var_dump('Feed List Error - JSON related.  ', json_last_error_msg());
    }

    return $feed_array;
}




function do_the_sync($this_feed) {
    $feed_url = $this_feed['url'];
    $feed_title = $this_feed['title'];

    require_valid_url($feed_url);
    figure_downloads_directory();

    $feed_object = get_feed_object($feed_url);  
    iterate_sync($feed_object);
}

function require_valid_url($feed_url) {
    // Require that the string passed to the command is a valid URL.
    if (false === filter_var($feed_url, FILTER_VALIDATE_URL)) {
        echo "The URL passed does not seem to be a valid URL." . PHP_EOL . "Exiting..." . PHP_EOL;
        exit;
    }
}

function get_feed_object($feed_url) {
    include_once('simplepie/autoloader.php');

    $feed = new SimplePie();
    $feed_string = curl_get_contents($feed_url, "feed_being_processed.xml");

    $feed->set_feed_url('feed_being_processed.xml');
    $feed->init();
    $feed->handle_content_type();

    return $feed;
}

function figure_downloads_directory() {
    $download_directory = DOWNLOAD_DIRECTORY;
    if (false === file_exists($download_directory)) {
        echo PHP_EOL . "Creating directory for feed, as it does not exist.  \"{$download_directory}\"";
        var_dump(mkdir($download_directory, 0777, true));
    }
}

function figure_destination_directory($feed_title) {
    $this_directory = sprintf(
        "%s/%s",
        DOWNLOAD_DIRECTORY,
        sanitize_file_name($feed_title, false, false)
    );

    if (false === file_exists($this_directory)) {
        echo PHP_EOL . "Creating directory for feed, as it does not exist.  \"{$this_directory}\"";
        var_dump(mkdir($this_directory, 0777, true));
    }
}

// I am fairly sure this was from somewhere on stack overflow, because I don't think I'd come up with this syntax.
// However, I am not able to find it anywhere in this form.  So, whoever wrote this, thanks.
function sanitize_file_name($string, $force_lowercase = true, $pregreplace = false) {
    $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                   "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                   "â€”", "â€“", ",", "<", ".", ">", "/", "?");
    $clean = trim(str_replace($strip, "", strip_tags($string)));
    $clean = preg_replace('/\s+/', "-", $clean);
    $clean = ($pregreplace) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;
    return ($force_lowercase) ?
        (function_exists('mb_strtolower')) ?
            mb_strtolower($clean, 'UTF-8') :
            strtolower($clean) :
        $clean;
}

function iterate_sync($feed) {

    $feed_title = $feed->get_title();
    $total_item_number = $feed->get_item_quantity();
    $current_item_number = 1;

    echo PHP_EOL . "Downloading " . $total_item_number . " items.";

    foreach($feed->get_items() as $item) {

        $item_title = $item->get_title();
        $mp3_link = $item->get_enclosure()->link;

        figure_destination_directory($feed_title);

        $destination_filename = sprintf(
            "%s/%s/%s.mp3", 
            DOWNLOAD_DIRECTORY,
            sanitize_file_name($feed_title, false, false),
            sanitize_file_name($item_title, false, false)
        );

        echo PHP_EOL . "(" . $current_item_number . "/" . $total_item_number . ") Downloading " . $item_title . PHP_EOL . "    (" . $mp3_link . ")...";

        if (false === file_exists($destination_filename)) {

            $results = curl_get_contents($mp3_link, $destination_filename);

            if (true === $results) {
                echo "[SUCCESS]";
            } else {
                echo "[ERROR - {$results}]";
            }
        } else {
            echo "[Skipping - File Exists]" . PHP_EOL;
        }

        $current_item_number++;
    }
}

function curl_get_contents($mp3_link, $destination_filename) {

    $options = array(
        CURLOPT_FILE => is_resource($destination_filename) ? $destination_filename : fopen($destination_filename, 'w'),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_URL => $mp3_link,
        CURLOPT_FAILONERROR => true, // HTTP code > 400 will throw curl error
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36'
      );
  
      $ch = curl_init();
      curl_setopt_array($ch, $options);
      $return = curl_exec($ch);
  
      if ($return === false) {
        return curl_error($ch);
      } else {
        return true;
      }
}
