# How to develop this bundle?

Use docker for local tasks
```bash
docker run -it -v $(pwd)/:/var/www hgiesenow/php:7.4 sh
# Install dependencies
composer install
# Run behat tests
vendor/bin/phpunit
# Fix styling
vendor/bin/php-cs-fixer fix --diff src
vendor/bin/php-cs-fixer fix --diff tests
# Check code
vendor/bin/psalm
```
