<?php
/**
 * @package gembed
 * @version 1.0
 */
/*
Plugin Name: gDocs Embedder
Description: You can manage your page content directly from Google Docs and you don't lose on SEO!
Plugin URI: https://github.com/stamat/gembed
Author: Stamat
Version: 1.0
Author URI: http://stamat.info
*/

function gembed_init() {

}
add_action( 'init', 'gembed_init', 0 );


function get_webpage($url) {

    $useragent = 'Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $hcontent = curl_exec( $curl );
    $herr = curl_errno( $curl );
    $herrmsg = curl_error( $curl );
    $hheader = curl_getinfo( $curl );

    curl_close( $curl );

    if ($hheader['http_code'] !=  200 || $herrno != 0) {
        return;
    }

    return $hcontent;
}

function DOMinnerHTML(DOMNode $element) {
    $innerHTML = "";
    $children  = $element->childNodes;

    foreach ($children as $child) {
        $innerHTML .= $element->ownerDocument->saveHTML($child);
    }

    return $innerHTML;
}

class ErrorTrap {
  protected $callback;
  protected $errors = array();
  function __construct($callback) {
    $this->callback = $callback;
  }
  function call() {
    $result = null;
    set_error_handler(array($this, 'onError'));
    try {
      $result = call_user_func_array($this->callback, func_get_args());
    } catch (Exception $ex) {
      restore_error_handler();
      throw $ex;
    }
    restore_error_handler();
    return $result;
  }
  function onError($errno, $errstr, $errfile, $errline) {
    $this->errors[] = array($errno, $errstr, $errfile, $errline);
  }
  function ok() {
    return count($this->errors) === 0;
  }
  function errors() {
    return $this->errors;
  }
}

function removeElemById($doc, $body, $id) {
    try {
        $elem  = $doc->getElementById($id);

        if (!empty($elem)) {
            $body->removeChild($elem);
        }
    } catch (DOMException $e) {
        //nothing
    }
}

function parse_gembed($html) {
    $doc = new DOMDocument();
    $caller = new ErrorTrap(array($doc, 'loadHTML'));
    $caller->call('<?xml encoding="UTF-8">' .$html);

    $body = $doc->getElementsByTagName('body')[0];

    try {
        $elem  = $doc->getElementById('contents');

        if (!empty($elem)) {
            return $doc->saveHTML($elem);
        }
    } catch (DOMException $e) {
        //nothing
    }

    try {
        $elem  = $doc->getElementById('sheets-viewport');

        if (!empty($elem)) {

            $elem = $elem->getElementsByTagName('table');
            if (!empty($elem)) {
                return $doc->saveHTML($elem[0]);
            }
        }
    } catch (DOMException $e) {
        //nothing
    }

    removeElemById($doc, $body, 'header');
    removeElemById($doc, $body, 'top-bar');
    removeElemById($doc, $body, 'footer');

    try {
        $scripts = $doc->getElementsByTagName('script');
        if (!empty($scripts)) {
            foreach ($scripts as $child) {
                if (!empty($child)) {
                    $body->removeChild($child);
                }
            }
        }
    } catch (DOMException $e) {
        //echo $e;
    }

    return DOMinnerHTML($body);
}

function gembed_func( $atts ) {
	$atts = shortcode_atts(
		array(
			'link' => '',
            'trim' => false
		), $atts, 'gembed' );
        echo parse_gembed(get_webpage($atts['link']));
}
add_shortcode( 'gembed', 'gembed_func' );


//TODO: Caching functionality. Frontend pulls the new page content then compares hashes, if the hash differs the page is recached
function get_website_callback() {
	global $post;
	$url = $_POST['url'];
    if (empty($url)) {
        echo 0;
        die();
    }
    echo parse_gembed($url);
}

add_action( 'wp_ajax_nopriv_get_website', 'get_website_callback' );
add_action( 'wp_ajax_get_website', 'get_website_callback' );
