<div align="center">

# PI-SPI QR for PHP

Build, parse and validate the interoperable QR payloads of the BCEAO instant payment platform.

Eight countries, one franc, no dependencies.

[![CI](https://github.com/catidegla/pi-spi-qr/actions/workflows/ci.yml/badge.svg)](https://github.com/catidegla/pi-spi-qr/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-%E2%89%A58.2-777bb4)](composer.json)
[![Dependencies](https://img.shields.io/badge/dependencies-0-brightgreen)](composer.json)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

</div>

---

BCEAO launched PI-SPI on 30 September 2025, and publishes official SDKs for it in JavaScript, Python, Flutter and Java, plus reference apps in React, Angular, Flutter and Django.

There is no PHP one. That is the whole reason this exists.

```bash
composer require catidegla/pi-spi-qr
```

## Generate

```php
use Catidegla\PiSpiQr\QrCode;

// A printed QR at the counter. The payer types the amount.
echo QrCode::static('341c3e1b-4312-49ec-b75e-4c8c74c10fd7', 'BJ')
    ->merchantName('Chez Fatou')
    ->merchantCity('Cotonou')
    ->toPayload();

// One sale at the till. The transaction id is required, not optional.
echo QrCode::dynamic($alias, 'CI', 1500, 'TILL-1-000842')->toPayload();
```

Four builders for the four cases the specification describes: `static()`, `staticWithAmount()`, `dynamic()` and `transfer()`. Feed the result to any QR library you already have. This package does not draw the image, because drawing a QR is a solved problem in PHP and the payload profile is not.

**Amounts are whole francs.** XOF has no minor unit, so 1500 is one thousand five hundred francs. If you are carrying a money object from elsewhere, convert before you get here, and never through a float.

## Read

```php
use Catidegla\PiSpiQr\Payload;

$payload = Payload::parse($scanned);

$payload->alias();          // 341c3e1b-4312-49ec-b75e-4c8c74c10fd7
$payload->amount();         // 1500, or null when the payer enters it
$payload->transactionId();  // TILL-1-000842
$payload->country();        // CI
```

## Validate, which is the part that matters

The specification names two checks to run before processing anything: the scheme identifier, which says the QR is meant for this rail at all, and the checksum, which says nobody altered it since it was printed. `problems()` runs those first and then the rest of the profile.

```php
foreach ($payload->problems() as $problem) {
    echo $problem, PHP_EOL;
}
```

```
scheme identifier is "br.gov.bcb.pix", expected "int.bceao.pi", so this QR belongs to another scheme
```

```
checksum does not match: the payload carries 0000, its contents give 9618
```

```
alias is 12 characters, and only a 36 character payment address may appear in an interoperable QR
```

It returns every problem rather than stopping at the first, because whoever is holding a QR that will not scan wants the whole list, not one round trip per fault.

What gets checked: the scheme identifier, the checksum, the alias length, the payload format indicator, the currency, that the country is one of the eight member states, the merchant channel, the transaction id length, that a dynamic QR carries a transaction id at all, and that the amount is a whole number of francs.

### Why a generic EMV library is not enough

The encoding is ordinary EMV and several PHP libraries already do it. What they cannot know is the profile: that the scheme identifier must be `int.bceao.pi`, that the currency is always 952, that only a 36 character payment address may appear in a QR while a phone number alias is valid everywhere else on the rail, and that the country must be a union member state. Those rules are what turns a QR that scans into a payment that settles.

## Conformance

The test suite includes BCEAO's own published worked example, payload and expected checksum both. If it ever fails, this package is wrong about the specification rather than the other way round.

```
the_checksum_matches_the_published_worked_example
the_complete_vector_verifies_against_itself
one_altered_character_breaks_the_checksum
the_vector_decodes_into_the_fields_the_specification_describes
the_official_vector_passes_our_own_validation
```

The checksum is CRC-16/CCITT-FALSE: ISO/IEC 13239, polynomial 0x1021, initial value 0xFFFF, no final XOR, no reflection. Half a dozen CRC-16 variants differ only in those last details and all of them get called "CRC-16" in casual writing. Picking the wrong one produces a payload that looks right and is refused at the till, so the initial value alone is covered by a test that fails if you change it to the other common one.

## Two things the published specification contradicts itself on

Worth knowing before you trust either.

**The merchant channel.** The guide documents three values, `000` static, `400` dynamic, `731` transfer. BCEAO's own worked example carries `500`, which appears nowhere on the portal. So `MerchantChannel` is not an enum: an enum would reject their test vector. Any three digit value is accepted, and the documented ones are constants.

**The alias.** It is described as "an alphanumeric identifier of 36 characters", and every official example is a UUID, which contains hyphens and is therefore not alphanumeric. Length is the rule both halves agree on, so length is enforced and the character set is left alone.

## Scope

This does QR payloads. It does not talk to the PI-SPI API, which needs credentials issued to a registered participant, and it deliberately does not render images.

Per the specification, a PI-SPI QR is Merchant-Presented-Mode only, carries no personal data, and must not be used for online or remote payments.

## The other half: collecting from a phone

A QR covers a customer standing in front of you. It does nothing for one paying from somewhere else, which on these networks means pushing a prompt to their handset and waiting.

[**catidegla/laravel-mobile-money**](https://github.com/catidegla/laravel-mobile-money) does that part: MTN MoMo, Wave and Orange Money behind one Laravel interface, with the driver chosen from the payer's number rather than named by the caller. It writes a row before it calls a provider, so a process that dies mid-call leaves a record rather than a payment nobody knows about, and it reconciles the callbacks that never arrive, which in this region is a normal share of them.

```bash
composer require catidegla/laravel-mobile-money
```

The two are independent and neither requires the other. This one is framework-agnostic and needs no database; that one is Laravel and does.

## Contributing

The specification is public at [developer.pispi.bceao.int](https://developer.pispi.bceao.int). If you find a rule this package gets wrong, an issue quoting the page beats a patch, because the fix usually belongs in the validation messages rather than the encoder.

One caveat on that portal: its "Forum communautaire" link points at `forum.pispi.bceao.int`, which does not resolve. The sandbox contact `pisfn-sandbox@bceao.int` does work.

## License

MIT.
