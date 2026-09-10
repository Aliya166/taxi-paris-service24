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
        $mode = $request->query->getString('mode', 'autocomplete');

        if (mb_strlen($query) < 3) {
            return $this->json(['features' => []]);
        }

        $normalizedQuery = mb_strtolower($query);

        $airportAliases = [
            'cdg' => 'Aéroport Paris Charles de Gaulle',
            'aeroport cdg' => 'Aéroport Paris Charles de Gaulle',
            'aéroport cdg' => 'Aéroport Paris Charles de Gaulle',
            'roissy' => 'Aéroport Paris Charles de Gaulle',
            'orly' => 'Aéroport de Paris Orly',
            'aeroport orly' => 'Aéroport de Paris Orly',
            'aéroport orly' => 'Aéroport de Paris Orly',
            'beauvais' => 'Aéroport Paris Beauvais',
            'aeroport beauvais' => 'Aéroport Paris Beauvais',
            'aéroport beauvais' => 'Aéroport Paris Beauvais',
        ];

        $searchText = $airportAliases[$normalizedQuery] ?? $query;

        $endpoint = $mode === 'search'
            ? 'search'
            : 'autocomplete';

        try {
            $response = $this->httpClient->request(
                'GET',
                sprintf(
                    'https://api.openrouteservice.org/geocode/%s',
                    $endpoint
                ),
                [
                    'query' => [
                        'api_key' => $this->apiKey,
                        'text' => $searchText,
                        'boundary.country' => 'FR',
                        'focus.point.lon' => 2.3522,
                        'focus.point.lat' => 48.8566,
                        'size' => 10,
                        'lang' => 'fr',
                    ],
                    'timeout' => 10,
                ]
            );

            $data = $response->toArray();

            return $this->json([
                'features' => $data['features'] ?? [],
            ]);
        } catch (\Throwable) {
            return $this->json(
                [
                    'features' => [],
                    'error' => 'Le service de recherche est indisponible.',
                ],
                JsonResponse::HTTP_BAD_GATEWAY
            );
        }
    }
}