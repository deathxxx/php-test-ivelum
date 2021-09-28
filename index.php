<?php


require __DIR__ . '/vendor/autoload.php';

use Laminas\Diactoros\ServerRequestFactory;
use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;

// Create a PSR7 request based on the current browser request.
$request = ServerRequestFactory::fromGlobals();

// Create a guzzle client
$guzzle = new GuzzleHttp\Client();

// Create the proxy instance
$proxy = new Proxy(new GuzzleAdapter($guzzle));

// Add a response filter that removes the encoding headers.
$proxy->filter(new RemoveEncodingFilter());

// Forward the request and get the response.
$url = "https://habr.com/ru/company/yandex/blog/258673/";
$urlStart = "https://habr.com";
//$response = $proxy->forward($request)->to($url);
$urlRequest = $urlStart . $_SERVER['REQUEST_URI'];
$response = $proxy->forward($request)->to($urlStart);


$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$requestUri = $_SERVER['REQUEST_URI'];
//error_log($actual_link);
//error_log($requestUri);
//$time = time();
$time = microtime() . GUID();
//error_log($time);

$responseContent = $response->getBody()->getContents();
//preg_match('#(?<=<!DOCTYPE HTML PUBLIC "-//W3C//DTD )[^/]+#i', $responseContent, $match);
preg_match('<!DOCTYPE html>', $responseContent, $match);
//preg_match('\<\!DOCTYPE\s+(([^\s\>]+)\s+)?(([^\s\>]+)\s*)?(\"([^\/]+)\/\/([^\/]+)\/\/([^\s]+)\s([^\/]+)\/\/([^\"]+)\")?(\s*\"([^\"]+)\")?\>', $responseContent, $match);
//preg_match('\<\!DOCTYPE\s+?\>', $responseContent, $match);
if (sizeof($match) > 0) {
    file_put_contents('regex-' . $time . '.txt', var_export($match, 1));

    //file_put_contents('resp.txt', var_export($response,1));
    file_put_contents('resp-' . $time . '.txt', var_export($response, 1));
    //file_put_contents('resp-body'.$time.'.html', $response->getBody()->getContents());
    file_put_contents('resp-body' . $time . '.html', $responseContent);
    //error_log(var_export($response->getBody()->getContents(),1));

    // find tag article
//    preg_match('/<article class="tm-page-article__content tm-page-article__content_inner"[^>]*>(.*?)<\/article>/is', $responseContent, $matchArticle);
    preg_match('/<article class="tm-page-article__content tm-page-article__content_inner">(.*?)<\/article>/is', $responseContent, $matchArticle);
//    error_log(var_export($matchArticle,1));
//    file_put_contents('regex.txt', var_export($matchArticle,1));
//    error_log(sizeof($matchArticle));

//    preg_match("/<body[^>]*>(.*?)<\/body>/is", $responseContent, $matches);
//    error_log(var_export($matches));

    //find words between tags

//    preg_match('/<(.*?)>(.*?)<\/(.*?)>/is', $matchArticle[0], $matchWords);
    file_put_contents('0.html', $matchArticle[0]);
    file_put_contents('1.html', $matchArticle[0]);
    preg_match('/(\<(.*?)\>)(.*?)(\<\/(.*?)\>)/is', $matchArticle[0], $matchWords);

    file_put_contents('reqex-words.txt', var_export($matchWords, 1));


    libxml_use_internal_errors(true);
    $dom = new DomDocument();
    $dom->loadHTML($responseContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//text()') as $text) {
        if (trim($text->nodeValue)) {
            if(strlen($text->nodeValue) == 6) {
//™
                error_log($text->nodeValue);
//                $text->nodeValue = ucwords($text->nodeValue);
                $text->nodeValue = $text->nodeValue.'™';
            } else {
                error_log('else');
            }
        }
    }

    $t = $dom->saveHTML();

    file_put_contents('domhtml-save.txt', var_export($t, 1));

//    $response->setBody($t);


}


// Output response to the browser.
(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);


function GUID()
{
    if (function_exists('com_create_guid') === true) {
        return trim(com_create_guid(), '{}');
    }

    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}
