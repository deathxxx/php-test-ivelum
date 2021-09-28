<?php


require __DIR__ . '/vendor/autoload.php';

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Uri;
use Proxy\Exception\UnexpectedValueException;
use Proxy\Proxy;
use Proxy\Adapter\Guzzle\GuzzleAdapter;
use Proxy\Filter\RemoveEncodingFilter;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Relay\RelayBuilder;

// Create a PSR7 request based on the current browser request.
$request = ServerRequestFactory::fromGlobals();

// Create a guzzle client
$guzzle = new GuzzleHttp\Client();

//Create Adapter
$adapterL = new  GuzzleAdapter($guzzle);

// Create the proxy instance
$proxy = new Proxy($adapterL);

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


//ge content
$responseContent = $response->getBody()->getContents();
preg_match('<!DOCTYPE html>', $responseContent, $match);
//get only html body
if (sizeof($match) > 0) {
    $body = updateContent($responseContent);
    $stream = bodyHtmlToStream($body);
    $responseOut = new Response($stream, 200, $response->getHeaders());
}



// Output response to the browser.
//(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);
if(isset($responseOut)) {
    (new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($responseOut);
} else {
    (new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);
}

function updateContent($responseContent){
    libxml_use_internal_errors(true);
    $dom = new DomDocument();
    $dom->loadHTML($responseContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//text()') as $text) {
        if (trim($text->nodeValue)) {

            if(strlen(trim($text->nodeValue)) == 6) {
                $text->nodeValue = $text->nodeValue.'™';
            } else {
                $text->nodeValue = preg_replace('/(\b\S{6,6}\b)/','$1™', $text->nodeValue);
            }
        }
    }

    return $dom->saveHTML();
}

function bodyHtmlToStream($html) {
    $string = $html;

    $stream = fopen('php://memory','r+');
    fwrite($stream, $string);
    rewind($stream);

    return $stream;
}