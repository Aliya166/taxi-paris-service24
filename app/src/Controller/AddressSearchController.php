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

#[AsController]
final class AddressSearchController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(OPENROUTESERVICE_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    #[Route(
        '/api/address-suggestions',
        name: 'app_address_suggestions',
        methods: ['GET']
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q'));

        if (mb_strlen($query) < 4) {
            return $this->json(['features' => []]);
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                'https://api.openrouteservice.org/geocode/search',
                [
                    'query' => [
                        'api_key' => $this->apiKey,
                        'text' => $query,
                        'boundary.country' => 'FR',
                        'boundary.rect.min_lon' => 1.45,
                        'boundary.rect.min_lat' => 48.10,
                        'boundary.rect.max_lon' => 3.55,
                        'boundary.rect.max_lat' => 49.25,
                        'focus.point.lon' => 2.3522,
                        'focus.point.lat' => 48.8566,
                        'size' => 7,
                        'lang' => 'fr',
                    ],
                ]
            );

            $data = $response->toArray();

            return $this->json([
                'features' => $data['features'] ?? [],
            ]);
        } catch (\Throwable) {
            return $this->json(
                ['features' => []],
                JsonResponse::HTTP_BAD_GATEWAY
            );
        }
    }
}