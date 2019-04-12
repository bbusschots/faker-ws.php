# faker-ws.php
A simple web service wrapper around the PHP version of Faker.

## Requirements
* PHP 5.4 or later
* PHP Internationalisation Module (PECL intl) installed

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

This will allow URLs of the forms:
* `http://DOMAIN.TLD/fakerWS/FORMATTER`, e.g. `http://mydomain.tld/fakerWS/name` to get a single value from the `name` formatter.
* `http://DOMAIN.TLD/fakerWS/FORMATTER/N`, e.g. `http://mydomain.tld/fakerWS/name/5` to get 5 values from the `name` formatter.
* `http://DOMAIN.TLD/fakerWS/FORMATTER/N/TYPE`, e.g. `http://mydomain.tld/fakerWS/name/5/text` to get 5 values from the `name` formatter in plain text.