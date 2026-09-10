<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Beneficiary alias
    |--------------------------------------------------------------------------
    |
    | The payment address your institution issued you, 36 characters. It is the
    | only alias type an interoperable QR may carry: a phone number alias works
    | everywhere else on the rail and is refused here.
    |
    | Not a secret. An alias exists precisely so you can publish it without
    | exposing account details, which is why it belongs in config rather than
    | in the environment. Set it per environment if you have more than one.
    |
    */

    'alias' => env('PI_SPI_ALIAS'),

    /*
    |--------------------------------------------------------------------------
    | Country
    |--------------------------------------------------------------------------
    |
    | ISO 3166-1 alpha-2, and one of the eight member states: BJ, BF, CI, GW,
    | ML, NE, SN, TG. Anything else is reported by the validator rather than
    | silently encoded, because a QR naming a country outside the union is not
    | something the rail will settle.
    |
    | There is deliberately no default. Any one of the eight would be right for
    | some readers of this file and quietly wrong for the other seven, and a
    | wrong country produces a QR that scans, looks correct, and is refused.
    |
    */

    'country' => env('PI_SPI_COUNTRY'),

    /*
    |--------------------------------------------------------------------------
    | Merchant identity
    |--------------------------------------------------------------------------
    |
    | Shown to the payer in their banking app. EMV counts these fields in
    | characters, so they must be ASCII: "Café" is four characters and five
    | bytes, and the mismatch produces a payload no reader can split. Write
    | "Cafe de la Gare" rather than letting the encoder refuse it at runtime.
    |
    */

    'merchant' => [
        'name' => env('PI_SPI_MERCHANT_NAME', 'X'),
        'city' => env('PI_SPI_MERCHANT_CITY', 'X'),

        // Fixed at 0000 by the PI-SPI profile. Present so you can see it,
        // not because there is currently anything else to set it to.
        'category' => '0000',
    ],

];
