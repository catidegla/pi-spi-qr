<?php

declare(strict_types=1);

namespace Catidegla\PiSpiQr;

/**
 * The values BCEAO fixes, gathered in one place so they are checkable.
 *
 * Everything here comes from the published PI-SPI QR specification rather than
 * from EMV in general. A generic EMV encoder will happily produce a payload
 * with the wrong scheme identifier or a currency that is not the franc; what
 * makes a payload a PI-SPI payload is this profile.
 */
final class Spec
{
    /**
     * The scheme identifier, carried in sub-field 36-00.
     *
     * The reversed PI url. The specification names this the first thing a
     * reader must check, before the checksum and before anything else, because
     * it is what distinguishes a PI-SPI QR from any other EMV QR that happens
     * to scan.
     */
    public const GUI = 'int.bceao.pi';

    /** ISO 4217 numeric for the CFA franc. PI-SPI settles in nothing else. */
    public const CURRENCY = '952';

    /**
     * XOF has no minor unit, so an amount is a whole number of francs.
     *
     * "54041000" in the specification's own example is one thousand francs,
     * not ten. Treating it as cents multiplies every amount by a hundred, and
     * nothing in the payload would reveal the error.
     */
    public const CURRENCY_EXPONENT = 0;

    public const PAYLOAD_FORMAT_INDICATOR = '01';

    /** Fixed at 0000 in the profile: PI-SPI does not use merchant categories. */
    public const DEFAULT_MERCHANT_CATEGORY = '0000';

    /** Reference Label, 62-05, capped by the specification's own guidance. */
    public const MAX_TRANSACTION_ID = 25;

    /**
     * A payment address alias, the only kind allowed in an interoperable QR.
     *
     * The specification calls it "alphanumeric, 36 characters" and then gives
     * UUID examples, which contain hyphens and are therefore not alphanumeric.
     * Length is the rule both halves agree on, so length is enforced and the
     * character set is left permissive.
     */
    public const ALIAS_LENGTH = 36;

    /** The eight member states of the union, the only valid country codes. */
    public const COUNTRIES = ['BJ', 'BF', 'CI', 'GW', 'ML', 'NE', 'SN', 'TG'];

    /* --------------------------------------------------------------- fields */

    public const ID_PAYLOAD_FORMAT = '00';
    public const ID_MERCHANT_ACCOUNT = '36';
    public const ID_MERCHANT_CATEGORY = '52';
    public const ID_CURRENCY = '53';
    public const ID_AMOUNT = '54';
    public const ID_COUNTRY = '58';
    public const ID_MERCHANT_NAME = '59';
    public const ID_MERCHANT_CITY = '60';
    public const ID_ADDITIONAL_DATA = '62';
    public const ID_CRC = '63';

    public const SUB_GUI = '00';
    public const SUB_ALIAS = '01';
    public const SUB_REFERENCE_LABEL = '05';
    public const SUB_MERCHANT_CHANNEL = '11';
}
