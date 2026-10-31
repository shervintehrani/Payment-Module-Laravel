#!/bin/sh
# Boots the demo application: installs the mounted package, prepares the
# database and starts the development server.
set -e

cd /var/www/demo

# Install the package from the bind-mounted source (symlinked, so edits on the
# host are picked up immediately). Only done once per container.
if ! grep -q '"shervintehrani/payment-module-laravel"' composer.json; then
    composer config repositories.payment-module \
        '{"type": "path", "url": "/package", "options": {"symlink": true}}' --json
    composer require "shervintehrani/payment-module-laravel:@dev" \
        --no-interaction --no-progress
fi

# The package migration is loaded automatically by its service provider.
php artisan migrate --force

# Seed sample payments only the first time.
if [ ! -f storage/.payments-seeded ]; then
    php artisan db:seed --class='PaymentModule\Database\Seeders\PaymentSeeder' --force
    touch storage/.payments-seeded
fi

echo ""
echo "Payment Module demo is ready:  http://localhost:8000/payment"
echo ""

exec php artisan serve --host=0.0.0.0 --port=8000
