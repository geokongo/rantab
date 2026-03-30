# Rantab

Rantab is a lightweight PHP payment library with a unified API across multiple payment processors. It supports Stripe and Mpesa out of the box. Designed to be extended with additional processors without changing your application code.

## Requirements

- PHP 7.4 or higher
- cURL extension enabled

## Installation
```bash
composer require glivers/rantab
```

## Supported Processors

- Stripe
- Mpesa (Safaricom Daraja)

## Quick Start

### Stripe
```php
use Rantab\Rantab;

$rantab = new Rantab([
    'processor' => 'stripe',
    'key'       => 'sk_live_xxx',
]);

// Charge
$res = $rantab->charge([
    'amount'         => 1000,       // in cents e.g. $10.00
    'currency'       => 'usd',
    'payment_method' => 'pm_xxx',
    'return_url'     => 'https://example.com/return',
]);

if ($res->ok) {

    echo $res->id;     // transaction ID
    echo $res->status; // 'succeeded'
} 
else {
    echo $res->error;
}

// Refund
$res = $rantab->refund('pi_xxx', 10.00);

// Status
$status = $rantab->status('pi_xxx'); // 'success', 'pending', 'failed'
```

### Mpesa
```php
use Rantab\Rantab;

$rantab = new Rantab([
    'processor' => 'mpesa',
    'key'       => 'consumer_key',
    'secret'    => 'consumer_secret',
    'shortcode' => '174379',
    'passkey'   => 'mpesa_passkey',
    'callback'  => 'https://example.com/mpesa/callback',
]);

// Charge — sends STK push to customer phone
$res = $rantab->charge([
    'amount'      => 100,
    'phone'       => '2547XXXXXXXX',
    'ref'         => 'ORDER-001',
    'description' => 'Payment for order 001',
]);

if ($res->ok) {

    echo $res->id;     // CheckoutRequestID
    echo $res->status; // 'pending' — Mpesa is async
} 
else {
    echo $res->error;
}

// Refund
$res = $rantab->refund('MPESA-TXN-ID', 100.00);

// Status
$status = $rantab->status('ws_CO_xxx'); // 'success', 'pending', 'failed'
```

## Response Object

Every `charge()` and `refund()` call returns a `Response` object with these properties:

| Property    | Type         | Description                              |
|-------------|--------------|------------------------------------------|
| `$ok`       | bool         | Whether the operation succeeded          |
| `$id`       | string\|null | Transaction ID from the processor        |
| `$amt`      | float\|null  | Transaction amount                       |
| `$currency` | string\|null | Currency code e.g. 'usd', 'KES'         |
| `$status`   | string\|null | Transaction status                       |
| `$error`    | string\|null | Error message if operation failed        |
| `$raw`      | array\|null  | Full raw response from the processor     |

## Error Handling

All errors throw a `RantabException` which extends PHP's base `Exception` class.
The exception carries a `$processor` property identifying which processor threw it.
```php
use Rantab\RantabException;

try {
    $res = $rantab->charge($data);
} 
catch (RantabException $e) {
    
    echo $e->getMessage();   // error message
    echo $e->processor;      // 'stripe' or 'mpesa'
}
```

## Adding a New Processor

Create a new folder under `src/` and implement the `Processor` interface:
```php
use Rantab\Processor;
use Rantab\Response;

class MyProcessor implements Processor
{
    public function charge(array $data): Response {}
    public function refund(string $id, float $amt): Response {}
    public function status(string $id): string {}
}
```

Then register it in `Rantab.php` inside the `resolve()` switch statement.

## Testing
```bash
composer install
./vendor/bin/phpunit tests/
```

## License

MIT