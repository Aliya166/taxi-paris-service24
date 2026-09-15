<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\VehicleType;
use App\Service\ReservationPricingService;
use PHPUnit\Framework\TestCase;

final class ReservationPricingServiceTest extends TestCase
{
    private ReservationPricingService $pricingService;

    protected function setUp(): void
    {
        $this->pricingService = new ReservationPricingService();
    }

    public function testMinimumPriceIsApplied(): void
    {
        $price = $this->pricingService->calculate(
            VehicleType::ECO,
            8.2,
            16,
            'Noisy-le-Sec, France',
            'Clignancourt, Paris, France'
        );

        self::assertSame('39.00', $price);
    }

    public function testDynamicEcoPriceIsCalculated(): void
    {
        $price = $this->pricingService->calculate(
            VehicleType::ECO,
            16.1,
            29,
            'Les Lilas, France',
            'Bois-Colombes, France'
        );

        self::assertSame('51.07', $price);
    }

    public function testParisToOrlyUsesFixedPrice(): void
    {
        $price = $this->pricingService->calculate(
            VehicleType::ECO,
            25,
            40,
            '10 rue de Rivoli, 75001 Paris, France',
            'Aéroport de Paris-Orly, France'
        );

        self::assertSame('59.00', $price);
    }

    public function testParisToBeauvaisUsesFixedPrice(): void
    {
        $price = $this->pricingService->calculate(
            VehicleType::ECO,
            90,
            80,
            'Clignancourt, Paris, France',
            'Aéroport Paris Beauvais, Tillé, France'
        );

        self::assertSame('150.00', $price);
    }

    public function testOrlyIsNotMistakenForParis(): void
    {
        $price = $this->pricingService->calculate(
            VehicleType::ECO,
            117.8,
            87,
            'Aéroport de Paris-Orly, Paray-Vieille-Poste, France',
            'Aéroport Paris Beauvais, Tillé, France'
        );

        self::assertSame('241.36', $price);
    }

    public function testVehicleRatesAreDifferent(): void
    {
        $ecoPrice = $this->pricingService->calculate(
            VehicleType::ECO,
            30,
            40,
            'Colombes, France',
            'Versailles, France'
        );

        $berlinePrice = $this->pricingService->calculate(
            VehicleType::BERLINE,
            30,
            40,
            'Colombes, France',
            'Versailles, France'
        );

        $vanPrice = $this->pricingService->calculate(
            VehicleType::VAN,
            30,
            40,
            'Colombes, France',
            'Versailles, France'
        );

        self::assertSame('78.00', $ecoPrice);
        self::assertSame('87.00', $berlinePrice);
        self::assertSame('102.00', $vanPrice);
    }

    public function testVanMinimumPriceIsSixtyFiveEuros(): void
    {
        $service = new ReservationPricingService();

        $price = $service->calculate(
            VehicleType::VAN,
            1.0,
            1,
            '10 rue de la Paix, Colombes',
            '20 rue Victor Hugo, Colombes'
        );

        self::assertSame('65.00', $price);
    }
}
