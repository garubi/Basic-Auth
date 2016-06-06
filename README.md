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
$args = array(
	'headers' => array(
		'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password ),
	),
);
```

##CGI and Fast-CGI Workaround
If your webserver is setup to use CGI or Fast-CGI (FCGI) then the HTTP Authorization header is blocked by default, which prevents this plugin from successfully authenticating your requests. To workaround this, you must edit your `.htaccess` file in the wordpress root folder (in the same folder as your wp-config.php file). Find the line that says

`RewriteRule ^index\.php$ - [L]`

and replace it with 

`RewriteRule ^index\.php$ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]`

This will re-add the HTTP Authorization header and allow this plugin to authenticate your requests.

(Note: this workaround only works on this version of the Basic Auth plugin. The WP-API/Basic-Auth plugin doesn't support this workaround)
[oauth]: https://github.com/WP-API/OAuth1
[RFC2617]: https://tools.ietf.org/html/rfc2617
