<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // ponytail: TelescopeServiceProvider is deliberately absent. Telescope is
    // a require-dev package, so `composer install --no-dev` leaves the class
    // it extends behind; listed here, package:discover died on the missing
    // parent and the production image could not be built at all.
    // AppServiceProvider::register() registers it when Telescope is installed.
];
