<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RouteCalculationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(OPENROUTESERVICE_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    /**
     * @param array<int, array<int, float|int>> $coordinates
     *
     * @return array<string, mixed>
     */
    public function calculate(array $coordinates): array
    {
        $response = $this->httpClient->request(
            'POST',
            'https://api.openrouteservice.org/v2/directions/driving-car/geojson',
            [
                'headers' => [
                    'Authorization' => $this->apiKey,
                    'Accept' => 'application/geo+json',
                ],
                'json' => [
                    'coordinates' => $coordinates,
                ],
                'timeout' => 15,
            ]
        );

        return $response->toArray();
    }
}