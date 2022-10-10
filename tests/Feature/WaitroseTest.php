<?php

declare(strict_types=1);

use ProductTrap\Contracts\Factory;
use ProductTrap\DTOs\Product;
use ProductTrap\Enums\Status;
use ProductTrap\Exceptions\ApiConnectionFailedException;
use ProductTrap\Facades\ProductTrap as FacadesProductTrap;
use ProductTrap\ProductTrap;
use ProductTrap\Spider;
use ProductTrap\waitrose\Waitrose;

function getMockWaitrose($app, string $response): void
{
    Spider::fake([
        '*' => $response,
    ]);
}

it('can add the Waitrose driver to ProductTrap', function () {
    /** @var ProductTrap $client */
    $client = $this->app->make(Factory::class);

    $client->extend('waitrose_other', fn () => new Waitrose(
        cache: $this->app->make('cache.store'),
    ));

    expect($client)->driver(Waitrose::IDENTIFIER)->toBeInstanceOf(Waitrose::class)
        ->and($client)->driver('waitrose_other')->toBeInstanceOf(Waitrose::class);
});

it('can call the ProductTrap facade', function () {
    expect(FacadesProductTrap::driver(Waitrose::IDENTIFIER)->getName())->toBe(Waitrose::IDENTIFIER);
});

it('can retrieve the Waitrose driver from ProductTrap', function () {
    expect($this->app->make(Factory::class)->driver(Waitrose::IDENTIFIER))->toBeInstanceOf(Waitrose::class);
});

it('can call `find` on the Waitrose driver and handle failed connection', function () {
    getMockWaitrose($this->app, '');

    $this->app->make(Factory::class)->driver(Waitrose::IDENTIFIER)->find('756176-631547-631548');
})->throws(ApiConnectionFailedException::class, 'The connection to https://waitrose.com/ecom/products/756176-631547-631548 has failed for the Waitrose driver');

it('can call `find` on the waitrose driver and handle a successful response', function () {
    $html = file_get_contents(__DIR__.'/../fixtures/successful_response.html');
    getMockWaitrose($this->app, $html);

    $data = $this->app->make(Factory::class)->driver(Waitrose::IDENTIFIER)->find('756176-631547-631548');
    unset($data->raw);

    expect($this->app->make(Factory::class)->driver(Waitrose::IDENTIFIER)->find('756176-631547-631548'))
        ->toBeInstanceOf(Product::class)
        ->identifier->toBe('756176-631547-631548')
        ->status->toEqual(Status::Available)
        ->name->toBe('John West Fridge Pot Tuna Steak in Olive Oil MSC')
        ->description->toBe('Tuna Steak with a little Olive Oil.')
        ->ingredients->toBe('<STRONG>Tuna</STRONG> (93%), Olive Oil, Salt')
        ->price->amount->toBe(2.5)
        ->unitAmount->unit->value->toBe('g')
        ->unitAmount->amount->toBe(110.0)
        ->unitPrice->unitAmount->unit->value->toBe('kg')
        ->unitPrice->unitAmount->amount->toBe(1.0)
        ->unitPrice->price->amount->toBe(22.72727272727273)
        ->brand->name->toBe('John West')
        ->images->toBe([
            'https://ecom-su-static-prod.wtrecom.com/images/products/9/LN_756176_BP_9.jpg',
            'https://ecom-su-static-prod.wtrecom.com/images/products/3/LN_756176_BP_3.jpg',
            'https://ecom-su-static-prod.wtrecom.com/images/products/11/LN_756176_BP_11.jpg',
            'https://ecom-su-static-prod.wtrecom.com/images/products/4/LN_756176_BP_4.jpg',
        ]);
});
