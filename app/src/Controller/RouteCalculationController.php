<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

#[AsController]
final class RouteCalculationController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(OPENROUTESERVICE_API_KEY)%')]
        private readonly string $apiKey,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route(
        '/api/route',
        name: 'app_route_calculation',
        methods: ['POST']
    )]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
            $coordinates = $payload['coordinates'] ?? null;

            if (
                !is_array($coordinates)
                || count($coordinates) !== 2
                || !$this->isCoordinatePair($coordinates[0] ?? null)
                || !$this->isCoordinatePair($coordinates[1] ?? null)
            ) {
                return $this->json(
                    ['error' => 'Invalid coordinates.'],
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

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
                ]
            );

            return $this->json($response->toArray());
        } catch (\Throwable $exception) {
            $this->logger->error(
                'OpenRouteService route calculation failed.',
                ['exception' => $exception]
            );

            return $this->json(
                ['error' => 'Route calculation unavailable.'],
                JsonResponse::HTTP_BAD_GATEWAY
            );
        }
    }

    private function isCoordinatePair(mixed $coordinates): bool
    {
        if (
            !is_array($coordinates)
            || count($coordinates) !== 2
            || !is_numeric($coordinates[0] ?? null)
            || !is_numeric($coordinates[1] ?? null)
        ) {
            return false;
        }

        $longitude = (float) $coordinates[0];
        $latitude = (float) $coordinates[1];

        return $longitude >= -180
            && $longitude <= 180
            && $latitude >= -90
            && $latitude <= 90;
    }
}
