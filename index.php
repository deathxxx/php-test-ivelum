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
$adapter = new GuzzleAdapter($guzzle);

// Create the proxy instance
$proxy = new Proxy($adapter);

// Add a response filter that removes the encoding headers.
$proxy->filter(new RemoveEncodingFilter());

// Forward the request and get the response.
$urlStart = "https://habr.com";
$urlRequest = $urlStart . $_SERVER['REQUEST_URI'];

// Get original response
try {
    $response = $proxy->forward($request)->to($urlStart);
} catch (\GuzzleHttp\Exception\ClientException $e) {
    if ($e->hasResponse()) {
        (new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($e->getResponse());
    } else {
        echo "No data";
    }
    exit(10);
} catch (Exception $e) {
    if ($response) {
        (new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);
    } else {
        var_dump($e);
    }
    exit(20);
}

$headers = $response->getHeaders();
$content_type = implode(";", $response->getHeader('Content-Type'));

if (substr($content_type, 0, 9) === "text/html") {
    // get content
    $responseContent = $response->getBody()->getContents();

    $body = updateContent($responseContent);
    $stream = bodyHtmlToStream($body);

    $responseOut = new Response($stream, 200, $response->getHeaders());
} else {
    // non-HTML files
    $responseOut = $response;
}

// Output response to the browser.
(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($responseOut);


function updateContent($responseContent)
{
    // Inject UTF-8 meta tag to keep cyrillic letters
    $sample = '<title>';
    $replacement = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . PHP_EOL . $sample;
    $pos = strpos($responseContent, $sample);
    if (false !== $pos) {
        $responseContent = substr_replace($responseContent, $replacement, $pos, strlen($sample));
    }

    libxml_use_internal_errors(true);
    $dom = new DomDocument();
    $dom->loadHTML($responseContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $xpath = new DOMXPath($dom);

    $query = '// body//* [not (self :: script ) ]/text()';
    $search = $xpath->query($query);

    foreach ($search as $text) {
        if (trim($text->nodeValue)) {

            if(mb_strlen(trim($text->nodeValue)) == 6) {
                $text->nodeValue = $text->nodeValue.'™';
            } else {
                $text->nodeValue = mb_ereg_replace('(\b\S{6,6}\b)','\\1™', $text->nodeValue);
            }
        }
    }

    return $dom->saveHTML();
}


function bodyHtmlToStream($html)
{
    $string = $html;

    $stream = fopen('php://memory','r+');
    fwrite($stream, $string);
    rewind($stream);

    return $stream;
}
