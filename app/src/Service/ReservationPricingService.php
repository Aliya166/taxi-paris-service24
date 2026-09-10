<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\VehicleType;

final class ReservationPricingService
{
    private const MINIMUM_PRICE = 39.00;
    private const RESERVATION_FEE = 15.00;
    private const PRICE_PER_MINUTE = 0.30;

    private const VEHICLE_RATES = [
        'eco' => 1.70,
        'berline' => 2.00,
        'van' => 2.50,
    ];

    public function calculate(
        VehicleType $vehicleType,
        float $distanceKm,
        int $durationMinutes,
        string $pickupAddress,
        string $dropoffAddress
    ): string {
        $fixedPrice = $this->getFixedPrice(
            $pickupAddress,
            $dropoffAddress
        );

        if ($fixedPrice !== null) {
            return number_format($fixedPrice, 2, '.', '');
        }

        $rate = self::VEHICLE_RATES[$vehicleType->value];

        $calculatedPrice =
            ($distanceKm * $rate)
            + ($durationMinutes * self::PRICE_PER_MINUTE)
            + self::RESERVATION_FEE;

        $finalPrice = max(
            self::MINIMUM_PRICE,
            round($calculatedPrice, 2)
        );

        return number_format($finalPrice, 2, '.', '');
    }

    private function getFixedPrice(
        string $pickupAddress,
        string $dropoffAddress
    ): ?float {
        $pickup = $this->normalize($pickupAddress);
        $dropoff = $this->normalize($dropoffAddress);

        $pickupIsParis = $this->isParisAddress($pickup);

        if ($pickupIsParis && str_contains($dropoff, 'orly')) {
            return 59.00;
        }

        if (
            $pickupIsParis
            && $this->containsAny(
                $dropoff,
                ['charles de gaulle', 'cdg', 'roissy']
            )
        ) {
            return 69.00;
        }

        if ($pickupIsParis && str_contains($dropoff, 'beauvais')) {
            return 150.00;
        }

        if (
            $pickupIsParis
            && $this->containsAny(
                $dropoff,
                [
                    'disney',
                    'disneyland',
                    'marne-la-vallee',
                    'marne la vallee',
                ]
            )
        ) {
            return 85.00;
        }

        if (
            str_contains($pickup, 'orly')
            && $this->containsAny(
                $dropoff,
                ['charles de gaulle', 'cdg', 'roissy']
            )
        ) {
            return 110.00;
        }

        return null;
    }

    private function isParisAddress(string $address): bool
    {
        if (
            preg_match(
                '/\b750(?:0[1-9]|1[0-9]|20)\b/',
                $address
            ) === 1
        ) {
            return true;
        }

        $addressParts = array_map(
            'trim',
            explode(',', $address)
        );

        return in_array('paris', $addressParts, true);
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(
        string $value,
        array $needles
    ): bool {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr(
            $value,
            [
                'à' => 'a',
                'â' => 'a',
                'ä' => 'a',
                'ç' => 'c',
                'é' => 'e',
                'è' => 'e',
                'ê' => 'e',
                'ë' => 'e',
                'î' => 'i',
                'ï' => 'i',
                'ô' => 'o',
                'ö' => 'o',
                'ù' => 'u',
                'û' => 'u',
                'ü' => 'u',
                'œ' => 'oe',
            ]
        );
    }
}