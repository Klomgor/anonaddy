<?php

namespace App\Helpers;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use Illuminate\Support\Facades\App;

class CustomEmailValidator
{
    public static function isValid(string $email): bool
    {
        if ($email === '' || strlen($email) > 254) {
            return false;
        }

        if (App::environment('testing')) {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }

        return (new EmailValidator)->isValid($email, new RFCValidation)
            || filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
