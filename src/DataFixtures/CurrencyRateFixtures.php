<?php

namespace App\DataFixtures;

use App\Entity\CurrencyRate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CurrencyRateFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $seedRates = [
            // Major currency pairs with INR
            ['USD', 'INR', 83.10],
            ['GBP', 'INR', 105.50],
            ['EUR', 'INR', 90.40],
            
            // Cross currency pairs
            ['USD', 'EUR', 0.92],     // 1 USD = 0.92 EUR
            ['EUR', 'USD', 1.09],     // 1 EUR = 1.09 USD
            ['USD', 'GBP', 0.79],     // 1 USD = 0.79 GBP
            ['GBP', 'USD', 1.27],     // 1 GBP = 1.27 USD
            ['EUR', 'GBP', 0.86],     // 1 EUR = 0.86 GBP
            ['GBP', 'EUR', 1.16],     // 1 GBP = 1.16 EUR
        ];

        foreach ($seedRates as [$base, $target, $rate]) {
            $currencyRate = new CurrencyRate();
            $currencyRate->setBaseCurrency($base);
            $currencyRate->setTargetCurrency($target);
            $currencyRate->setRate($rate);

            $manager->persist($currencyRate);
        }

        $manager->flush();
    }
}
