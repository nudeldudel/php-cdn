php-cdn
=======



`dynamic` `file` `caching` `pseudo` `cdn`
 
 
* cdn root path   : http://cdn.com/
* cdn example url : http://cdn.com/path/to/resource.css?d=12345
* maps the uri    : /path/to/resource.css?d=12345
* to the origin   : http://yoursite.com/path/to/resource.css?d=12345
* caches file to  : ./cache/[base64-encoded-uri].css
* caches gzipped file to : ./cache/[base64-encoded-uri].gzip
* saves server headers at : ./cache/[base64-encoded-uri].header (For origin control)
* returns local cached copy, gzipped copy or issues 304 not modified