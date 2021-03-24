# Basic Authentication handler
This plugin adds Basic Authentication to a WordPress site.

Note that this plugin requires sending your username and password with every
request, and should only be used for development and testing. We strongly
recommend using the [OAuth 1.0a][oauth] authentication handler for production.

## Installing
1. Download the plugin into your plugins directory
2. Enable in the WordPress admin

## Using
This plugin adds support for Basic Authentication, as specified in [RFC2617][].
Most HTTP clients will allow you to use this authentication natively. Some
examples are listed below.

### cURL

```sh
curl --user admin:password http://example.com/wp-json/
```

### WP_Http

```php
wp_remote_request(
    'http://example.com/wp-json',
     array(
     	'headers' => array(
     		'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
     	),
     )
);
```

## CGI and Fast-CGI Workaround
If you are communicating with a webserver using CGI or Fast-CGI (FCGI) then the HTTP Authorization header is blocked by default, which prevents this plugin from successfully authenticating your requests. 

On this fork of the WP API Basic Auth plugin, you have two choices to authenticate in this case: add a line in .htaccess file o pass the "Authorization" data in a query string variable.

### Workaround 1: .htaccess
Add the following line in your .htaccess file, just below the `RewriteEngine On` added by WordPress:

```sh
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
```

You are done, the plugin take care of the rest.

### Workaround 2: 
Pass the "Authorization" data in the query string variable `_authorization`.

Here are some examples of how to use this version of the Basic Auth plugin authenticating through the query string.

#### cURL

```sh
curl --user admin:password http://example.com/wp-json/?_authorization=Basic base64encodedusernameandpassword
```
where base64encodedusernameandpassword is the user's username, a colon, and their password concatenated together and theenn base 64 encoded.

#### WP_Http

```php
wp_remote_request(
    'http://example.com/wp-json?_authorization=Basic ' . base64_encode( $username . ':' . $password ),
     array()
);
```
