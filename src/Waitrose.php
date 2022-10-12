<?php

declare(strict_types=1);

namespace ProductTrap\Waitrose;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;
use ProductTrap\Contracts\Driver;
use ProductTrap\DTOs\Brand;
use ProductTrap\DTOs\Price;
use ProductTrap\DTOs\Product;
use ProductTrap\DTOs\Results;
use ProductTrap\DTOs\UnitAmount;
use ProductTrap\DTOs\UnitPrice;
use ProductTrap\Enums\Currency;
use ProductTrap\Enums\Status;
use ProductTrap\Enums\Unit;
use ProductTrap\Exceptions\ProductTrapDriverException;
use ProductTrap\Traits\DriverCache;
use ProductTrap\Traits\DriverCrawler;
use ProductTrap\Waitrose\Exceptions\InvalidResponseException;

class Waitrose implements Driver
{
    use DriverCache;
    use DriverCrawler;

    public const IDENTIFIER = 'waitrose';

    public const BASE_URI = 'https://waitrose.com';

    public function __construct(CacheRepository $cache)
    {
        $this->cache = $cache;
    }

    public function getName(): string
    {
        return 'Waitrose';
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ProductTrapDriverException
     */
    public function find(string $identifier, array $parameters = []): Product
    {
        $html = $this->remember($identifier, now()->addDay(), fn () => $this->scrape($this->url($identifier)));

        $crawler = $this->crawl($html);
        preg_match_all(
            '/<script nonce(=".*?")?>window\.__PRELOADED_STATE__ = (?<data>.*?)<\/script>/',
            $crawler->html(),
            $matches
        );

        // Extract product JSON as possible source of information
        $json = null;
        foreach ($matches['data'] ?? [] as $scrapedJson) {
            $scrapedJson = (array) json_decode(trim($scrapedJson), true);
            /** @var array{entities?: array{products?: array}} $scrapedJson */
            if (isset($scrapedJson['entities']['products'])) {
                $json = array_shift($scrapedJson['entities']['products']);

                break;
            }
        }

        if ($json === null) {
            throw new InvalidResponseException('The data could not be found for this product');
        }

        /** @var array{
         *     name: string,
         *     summary: string,
         *     brandName?: string,
         *     productImageUrls?: array<string>,
         *     contents: array{ingredients?: string},
         *     size?: string,
         *     currentSaleUnitPrice: array{price?: array{amount?: string, currencyCode?: string}},
         *  } $json
         */

        // Title
        $title = $json['name'];

        // Description
        $description = $json['summary'];

        // Brand
        $brand = isset($json['brandName']) ? new Brand(
            name: $json['brandName'],
            identifier: $json['brandName'],
        ) : null;

        // Currency
        $currency = Currency::tryFrom(
            $json['currentSaleUnitPrice']['price']['currencyCode'] ?? 'GBP',
        );

        // Price
        $price = isset($json['currentSaleUnitPrice']['price']['amount'])
            ? new Price(
                amount: $json['currentSaleUnitPrice']['price']['amount'],
                currency: $currency,
            )
            : null;

        // Images
        $images = array_values(array_unique($json['productImageUrls'] ?? []));

        // Status
        $status = Status::Available;

        // Ingredients
        $ingredients = isset($json['contents']['ingredients'])
            ? Str::of(
                (string) $json['contents']['ingredients']
            )->trim()->toString()
            : null;

        // Unit Amount (e.g. 85g or 1kg)
        $unitAmount = UnitAmount::parse($json['size'] ?? 'each');

        // Unit Price (e.g. $2 per kg)
        $unitPrice = UnitPrice::determine(
            price: $price,
            unitAmount: $unitAmount,
        );

        return new Product(
            identifier: $identifier,
            sku: $identifier,
            name: $title,
            description: $description,
            url: $this->url($identifier),
            price: $price,
            status: $status,
            brand: $brand,
            unitAmount: $unitAmount,
            unitPrice: $unitPrice,
            ingredients: $ingredients,
            images: $images,
            raw: [
                'html' => $html,
            ],
        );
    }

    public function url(string $identifier): string
    {
        return sprintf('%s/ecom/products/_/%s', self::BASE_URI, $identifier);
    }

    /**
     * @param  array<string, mixed>  $parameters
     *
     * @throws ProductTrapDriverException
     */
    public function search(string $keywords, array $parameters = []): Results
    {
        return new Results();
    }
}
