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


    $t = updateContent($responseContent);

}


// Output response to the browser.
(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);

function updateContent($responseContent){
    libxml_use_internal_errors(true);
    $dom = new DomDocument();
    $dom->loadHTML($responseContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);

    foreach ($xpath->query('//text()') as $text) {
        if (trim($text->nodeValue)) {
            if(strlen($text->nodeValue) == 6) {
                $text->nodeValue = $text->nodeValue.'™';
            } else {
            }
        }
    }

    return $dom->saveHTML();
}



/**
 * Forward the request to the target url and return the response.
 *
 * @param  string $target
 * @throws UnexpectedValueException
 * @return ResponseInterface
 */
function replaceText($target, $request, $filters)
{
    if ($request === null) {
        throw new UnexpectedValueException('Missing request instance.');
    }

    $target = new Uri($target);

    // Overwrite target scheme, host and port.
    $uri = $request->getUri()
        ->withScheme($target->getScheme())
        ->withHost($target->getHost())
        ->withPort($target->getPort());

    // Check for subdirectory.
    if ($path = $target->getPath()) {
        $uri = $uri->withPath(rtrim($path, '/') . '/' . ltrim($uri->getPath(), '/'));
    }

    $request = $request->withUri($uri);

    $stack = $filters;

    $stack[] = function (RequestInterface $request, ResponseInterface $response, callable $next) {
        $response = $this->adapter->send($request);

        return $next($request, $response);
    };

    $relay = (new RelayBuilder)->newInstance($stack);

    return $relay($request, new Response);
}