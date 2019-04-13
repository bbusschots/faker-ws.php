# faker-ws.php
A simple web service wrapper around the PHP version of Faker.

## Requirements
* PHP 5.4 or later
* PHP Internationalisation Module (PECL intl) installed

## Installation

1. Expand code into folder of your choice.
2. Use [Composer](https://getcomposer.org/) to install the prerequisites (just [Faker](https://github.com/fzaninotto/Faker) & [Mustache](https://github.com/bobthecow/mustache.php/wiki)):
   `composer install`
3. There is no step 3 🙂

## Usage

Once installed the fake data can be accessed in three formats via `index.php`; plain text, JSON, and pretty-printed JSON. The output format is controlled via the `type` parameter (described below).

By default, plain text is returned when a single value is requested, and JSON when multiple values are requested.

The script reads parameters from PHP's `$_REQUEST` super-global variable, so depending on your PHP config, it can accept parameters via the query string, post data, and cookies. See [the PHP documentation for `$_REQUEST`](https://php.net/manual/en/reserved.variables.request.php) for details.

### Required Parameters

* `formatter` (aliased by `f`) — the name of the Faker Formatter to generate the data with, e.g. `name`. You can see the fromatters available via `info.php`.

### Optional Parameters
* `arg1` … `argn` — separate numbered parameters to specify arguments to be passed to the formatter. E.g. `formatter=password&arg1=8&arg2=16` to pass `8` and `16` as the first and second arguments to the `password` formatter. Processing will stop at the first missing argument, so if you pass `arg1` and `arg3` then `arg3` will be ignored because processing will have stopped at the missing `arg2`.
* `args` — arguments to be passed to the formatter as a JSON string representing an array. Because special characters need to be URL-escaped in the query string this parameter is best passed as post data.
* `default` (aliased by `d` & `def`) — the value to use as the default value when the `optional` parameter has a truthy value. The default value is the empty string.
* `locale` (aliased by `l` & `loc`) — the locale to use when constructing the Faker Factory object that will be used to generate the data. The locale can be in any format that is understood by PHP's [`locale_get_primary_language()`](https://php.net/manual/en/locale.getprimarylanguage.php) and [`locale_get_region()`](https://php.net/manual/en/locale.getregion.php) functions. If this parameter is not passed then the `Accept-Language` HTTP Request header tried, and if that's not present then Faker's default locale (`en_US`) is used. You can test your locale via `info.php` — it will process the locale in the same way `index.php` does and display the result.
* `n` — the number of pieces of data to generate. The value must be a positive integer or it will be ignored. The default is `1`.
* `optional` (aliased by `o` & `opt`) — a truthy value for this parameter will enable Faker's `.optional()` modifier. If enabled the modifier will be called with values from the `weight` and `default` parameters.
* `separatpr` (aliased by `s` & `sep`) — the separator to use when separating multiple values when `type=text`. The default separator is the new-line character.
* `type` (aliased by `t` & `want`) — the requested output format, must be one of `text`, `json`, or `jsonText`. The default is `text` for `n=1` and `json` when `n` is greater than 1.
  - `json` — the data is returned as a JSON string represening an array of values. The response will have the MIME-Type `application/json`.
  - `jsonText` — the data is returned as a pretty-printed JSON string representing an array of values. The response will have the MIME-Type `text/plain`.
  - `text` — the data is returned as a single string separated by `separator`. The response will have the MIME-Type `text/plain`.
* `unique` (aliased by `u`) — a truthy value for this parameter will enable Faker's `.unique()` modifier.
* `weight`  (aliased by `w`) — the weigting to use when `optional` has a truthy value. Defaults to `0.5`.


## Suggested NGINX Config

Nicer URLs can be obtained using an NGINX config something like the following:

```
# set up fakerWS                                                                          
location /fakerWS/ {                                                                
  try_files $uri $uri/ $uri.php?$query_string;                                            
  rewrite ^/fakerWS/(\w+)/?$ /utils/fakerWS/?formatter=$1&$args;                    
  rewrite ^/fakerWS/(\w+)/(\d+)/?$ /utils/fakerWS/?formatter=$1&n=$2&$args;         
  rewrite ^/fakerWS/(\w+)/(\d+)/(json|text|jsonText)/?$ /utils/fakerWS/?formatter=$1&n=$2&type=$3&$args;
} 
```

The `try_files` line makes the info page available at `http://DOMAIN.TLD/fakerWS/info`.

The three re-write lines allow URLs of the forms:
1. `http://DOMAIN.TLD/fakerWS/FORMATTER`, e.g. `http://mydomain.tld/fakerWS/name` to get a single value from the `name` formatter.
2. `http://DOMAIN.TLD/fakerWS/FORMATTER/N`, e.g. `http://mydomain.tld/fakerWS/name/5` to get 5 values from the `name` formatter.
3. `http://DOMAIN.TLD/fakerWS/FORMATTER/N/TYPE`, e.g. `http://mydomain.tld/fakerWS/name/5/text` to get 5 values from the `name` formatter in plain text.