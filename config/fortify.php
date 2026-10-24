<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'passwords' => 'users', 'username' => 'email', 'email' => 'email',
    'lowercase_usernames' => true, 'home' => '/portal', 'prefix' => '', 'domain' => null,
    'middleware' => ['web'], 'auth_middleware' => 'auth', 'views' => true,
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    'features' => [
        Features::registration(), Features::resetPasswords(), Features::emailVerification(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],
];
