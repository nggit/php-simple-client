# PHP Simple Client
A Simple and Fast HTTP Client, designed to be out of the box. There is also a [simpleclient](https://github.com/nggit/simpleclient) written in Python.
## Quick Start
There are two versions, using cURL or native PHP Stream.
### 1. cURL version
```php
require 'src/Curl.php';
$client = new Nggit\PHPSimpleClient\Curl();
```
### 2. stream_socket_client version (fsockopen variant)
```php
require 'src/Stream.php';
$client = new Nggit\PHPSimpleClient\Stream();
```
You can just use a stand-alone PHP file like that, or use a [wrapper class](src/Client.php). At first, install it via composer:
```
composer require nggit/php-simple-client
```
Then, you can do something like this:
```php
require __DIR__ . '/vendor/autoload.php';
use Nggit\PHPSimpleClient\Client;

$client = Client::create(); // default backend is 'stream'
$client = Client::create('curl'); // if you want to use the 'curl' backend
// alternative way:
$client = (new Client())->stream();
// you can also use the options:
$client = (new Client(['debug' => true, 'timeout' => 60]))->curl();
$client = (new Client())->curl(true, -1, 60); // debug, maxredirs, timeout
```
## Example
### Simple GET
```php
$client->setUrl('https://www.google.com/') // required to set an url
       ->send();

echo $client->getHeader();
# HTTP/1.1 200 OK
# Date: Mon, 11 Feb 2019 07:18:28 GMT
# Expires: -1
# Cache-Control: private, max-age=0
# Content-Type: text/html; charset=UTF-8
# ...

echo $client->getHeader(0);
# HTTP/1.1 200 OK

echo $client->getHeader('Content-Type');
# text/html; charset=UTF-8

print_r($client->getHeaders()); // array

echo $client->getBody();
# <!doctype html>...</html>
```
### Custom Request Method (Optional)
The fastest method to get only header is to use HEAD request.
```php
$client->setUrl('https://www.google.com/') // required to set an url
       ->request('HEAD')
       ->send();
```
### Setting Headers To Be Sent
Setting Headers must be done before calling `request()` and `send()`.
```php
$headers = array(
    'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:78.0) Gecko/20100101 Firefox/78.0',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.5'
);

$client->setHeaders($headers);
```
### Common
```php
echo $client->getProtocol();        # HTTP
echo $client->getProtocolVersion(); # 1.1
echo $client->getStatusCode();      # 200
echo $client->getReasonPhrase();    # OK
```
### Page Redirects
PHP Simple Client has its own mechanism for handling page redirects without relying on *php.ini*.
You can control max allowed redirection via `$client->setMaxRedirs($number)`.
$number = **0** means don't allow redirects and **-1** means unlimited redirects (default).
### Cookies
No worries. Look at the example below to log in to Facebook.
```php
$url = 'https://mbasic.facebook.com/login/device-based/regular/login/?next=https%3A%2F%2Fmbasic.facebook.com%2Fmessages';
$client->setUrl($url);
$client->setHeaders($headers); // you can use headers above
$client->request('POST', array('email' => 'YOUR_USER_NAME_OR_EMAIL', 'pass' => 'YOUR_PASSWORD'));
$client->send(); // send the request

echo $client->getBody(); // display the last page of redirects
```
## Advanced
There are some hidden features to hack like `getResponse()` to view history of redirection.
