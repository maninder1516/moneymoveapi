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
            ['USD', 'INR', 83.10],
            ['GBP', 'INR', 105.50],
            ['EUR', 'INR', 90.40],
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
