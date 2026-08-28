# Subdomain deployment

Example final URL:

    https://salesposts.YOURDOMAIN.com/

DNS A record:

    salesposts.YOURDOMAIN.com -> 144.126.218.94

For current IP/path testing keep:

    APP_BASE_PATH=/sales-posts
    ENFORCE_APP_HOST=0

For the dedicated subdomain use:

    APP_BASE_PATH=
    APP_HOST=salesposts.YOURDOMAIN.com
    ENFORCE_APP_HOST=1

The parent portal hands off `uid`, `sales_id`, `name`, `role`, `ts`, `nonce`, and `sig`. `role` may be `admin` or `sales`, but is accepted only after HMAC verification. The user/role is then written to MySQL and every later authorization check reads it from the database.
