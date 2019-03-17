<?php
    
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


/*
 *  Part One 
 *  These files need to be included as this script resides outside of WordPress
 */
define('WP_USE_THEMES', false);
require_once( __DIR__ . '/wp-blog-header.php' );

// https://github.com/saulwiggin/albright/blob/master/WebScrape/3-xpath-scraping.php
// https://gist.github.com/luckyshot/5395600

function curlGet($url) {
    
	$user_agent = [
		'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0',
		'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.80 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.71 Safari/537.36',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11) AppleWebKit/601.1.56 (KHTML, like Gecko) Version/9.0 Safari/601.1.56',
		'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.80 Safari/537.36',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.7 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.7',
		'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko',
		'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko',
		'Mozilla/5.0 (compatible, MSIE 11, Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
		'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/5.0)',
	];

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
	curl_setopt($ch, CURLOPT_TIMEOUT, 120);
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent[ array_rand($user_agent, 1) ]);
	curl_setopt($ch, CURLOPT_URL, $url);
	$results = curl_exec($ch);
	curl_close($ch);
	return $results;
}


function returnXPathObject($item) {
	$xmlPageDom = new DomDocument();
	@$xmlPageDom->loadHTML($item);
	$xmlPageXPath = new DOMXPath($xmlPageDom);
   
	return $xmlPageXPath;
}

$getPage = curlGet('https://www.checkatrade.com/AndertonElectrics/Reviews.aspx?sort=0&page=2#results');
$pageObject = returnXPathObject($getPage);

// Get review ID to dedupe

$reviewID = $pageObject->query('//div[@class="feedback-list__title summary"]/a');
$reviewDescription = $pageObject->query('//p[@class="feedback-list__description description"]');
$reviewLocation = $pageObject->query('//span[@class="feedback-list__customer"]');
$reviewRating = $pageObject->query('//div[@class="feedback-list__overall"]/span');

// to hold our review content
$reviews = array();

$counter = 0;
foreach($reviewID as $result)
{
    $reviews[$counter]['id'] = $result->getAttribute("name");
    $reviews[$counter]['title'] = $result->textContent;
    $counter++;
}

$counter = 0;
foreach($reviewDescription as $result)
{
    $reviews[$counter]['description'] = $result->textContent;
    $counter++;
}

$counter = 0;
foreach($reviewLocation as $result)
{
    $location = substr($result->textContent, 0, strpos($result->textContent, "("));
    
    if(($pos = strpos($result->textContent, ')')) !== false) {
       $new_str = trim(substr($result->textContent, $pos + 1) . ' 13:30:30');

    } else  {
       $new_str = trim(get_last_word($result->textContent) . ' 13:30:30');
     
    }
    
    $time = strtotime($new_str);
    $newformat = date('Y-m-d H:i:s',$time);
    $reviews[$counter]['date'] = $newformat;
    
    $location = str_replace(array('– '), '',$location);
    $reviews[$counter]['location'] = $location;
    $counter++;
}

$counter = 0;
foreach($reviewRating as $result)
{
    $reviews[$counter]['rating'] = (float)$result->textContent;
    $counter++;
}


// Get array of current log (ref) numbers from our animal posts
$query_scrub_reviews = get_posts(array(
    'numberposts'	=> -1,
    'post_type'		=> 'testimonials',
    'post_status'		=> 'publish',
));

$existing_ids = array();

foreach ( $query_scrub_reviews as $scrub_list ) {
    
    $existing_ids[] = get_field('checkatrade_id', $scrub_list->ID);

}


foreach ($reviews as $v1) {


    if ( in_array( $v1['id'], $existing_ids ) ) {
        
        echo 'found ' . $v1['id'];
        
        return false;
        
    } else {
        
        // Create a new post - $postdate = '2010-02-23 18:57:33';
        $my_post = array(
            'post_title'	=> $v1['title'],
            'post_type'		=> 'testimonials',
            'post_date'     => $v1['date'],
            'post_status'	=> 'publish',
            'post_content'	=> $v1['description'],
        );

        // insert the post into the database to get a $post_id so...
        $post_id = wp_insert_post( $my_post );
        
        update_field( 'field_5c8cbd28fe1c4', $v1['location'], $post_id );
        update_field( 'field_5c8cbd50fe1c5', $v1['rating'], $post_id );
        update_field( 'field_5c8cc0a4a1d97', $v1['id'], $post_id );
        echo $v1['id'] . ' - Added' . PHP_EOL;
    }
    
}
