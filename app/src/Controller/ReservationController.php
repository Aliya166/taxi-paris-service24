<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\PricingMode;
use App\Enum\ReservationType;
use App\Enum\VehicleType;
use App\Service\LoyaltyDiscountService;
use App\Service\ReservationConfirmationMailer;
use App\Service\ReservationPricingService;
use App\Service\RouteCalculationService;
use App\Service\ReservationNotificationMailer;
use Psr\Log\LoggerInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReservationController extends AbstractController
{
    #[Route(
        '/reservation.html',
        name: 'app_reservation',
        methods: ['GET']
    )]
    public function index(): Response
    {
        return $this->renderReservationPage(
            ReservationType::STANDARD
        );
    }

    #[Route(
        '/reservation/aeroport',
        name: 'app_reservation_airport',
        methods: ['GET']
    )]
    public function airport(): Response
    {
        return $this->renderReservationPage(
            ReservationType::AIRPORT
        );
    }

    #[Route(
        '/reservation/gare',
        name: 'app_reservation_station',
        methods: ['GET']
    )]
    public function station(): Response
    {
        return $this->renderReservationPage(
            ReservationType::STATION
        );
    }

    #[Route(
        '/reservation/professionnelle',
        name: 'app_reservation_business',
        methods: ['GET']
    )]
    public function business(): Response
    {
        return $this->renderReservationPage(
            ReservationType::BUSINESS
        );
    }

    #[Route(
        '/reservation/longue-distance',
        name: 'app_reservation_long_distance',
        methods: ['GET']
    )]
    public function longDistance(): Response
    {
        return $this->renderReservationPage(
            ReservationType::LONG_DISTANCE
        );
    }
    #[Route(
        '/api/reservations',
        name: 'app_reservation_create',
        methods: ['POST']
    )]

    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        LoyaltyDiscountService $loyaltyDiscountService,
        ReservationConfirmationMailer $confirmationMailer,
        ReservationNotificationMailer $notificationMailer,
        ReservationPricingService $pricingService,
        RouteCalculationService $routeCalculationService,
        LoggerInterface $logger
    ): JsonResponse {
        $fullName = $this->getValue($request, 'name');
        $email = mb_strtolower($this->getValue($request, 'email'));
        $phone = $this->getValue($request, 'phone');

        $pickupAddress = $this->getValue(
            $request,
            'pickupAddress',
            'Adresse de départ'
        );

        $dropoffAddress = $this->getValue(
            $request,
            'dropoffAddress',
            'Adresse d’arrivée',
            "Adresse d'arrivée"
        );

        if ($fullName === '') {
            return $this->errorResponse(
                'Veuillez indiquer votre nom complet.'
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse(
                'Veuillez indiquer une adresse email valide.'
            );
        }

        if ($phone === '') {
            return $this->errorResponse(
                'Veuillez indiquer votre numéro de téléphone.'
            );
        }

        if ($pickupAddress === '' || $dropoffAddress === '') {
            return $this->errorResponse(
                'Veuillez indiquer les adresses de départ et d’arrivée.'
            );
        }

        $nameParts = preg_split('/\s+/', $fullName, 2);

        $firstName = $nameParts[0] ?? $fullName;
        $lastName = $nameParts[1] ?? '-';

        $scheduledAt = $this->createScheduledDate($request);

        if ($scheduledAt === null) {
            return $this->errorResponse(
                'La date ou l’heure sélectionnée est invalide.'
            );
        }

        $vehicleValue = strtolower(
            $this->getValue($request, 'vehicle')
        );

        $vehicleType = VehicleType::tryFrom($vehicleValue)
            ?? VehicleType::ECO;

        $reservationTypeValue = strtolower(
            $this->getValue($request, 'reservation_type')
        );

        $reservationType = ReservationType::tryFrom(
            $reservationTypeValue
        ) ?? ReservationType::STANDARD;

        $passengers = max(
            1,
            min(7, (int) $this->getValue($request, 'passengers'))
        );

        $luggage = max(
            0,
            min(6, (int) $this->getValue($request, 'luggage'))
        );

        $distanceKm = null;
        $durationMinutes = null;
        $basePrice = null;
        $pricingMode = PricingMode::MANUAL_QUOTE;

        if ($reservationType === ReservationType::STANDARD) {
            $pickupLongitudeValue = $this->getValue(
                $request,
                'pickupLongitude'
            );
            $pickupLatitudeValue = $this->getValue(
                $request,
                'pickupLatitude'
            );
            $dropoffLongitudeValue = $this->getValue(
                $request,
                'dropoffLongitude'
            );
            $dropoffLatitudeValue = $this->getValue(
                $request,
                'dropoffLatitude'
            );

            if (
                !is_numeric($pickupLongitudeValue)
                || !is_numeric($pickupLatitudeValue)
                || !is_numeric($dropoffLongitudeValue)
                || !is_numeric($dropoffLatitudeValue)
            ) {
                return $this->errorResponse(
                    'Veuillez calculer votre trajet avant de réserver.'
                );
            }

            $pickupLongitude = (float) $pickupLongitudeValue;
            $pickupLatitude = (float) $pickupLatitudeValue;
            $dropoffLongitude = (float) $dropoffLongitudeValue;
            $dropoffLatitude = (float) $dropoffLatitudeValue;

            if (
                $pickupLongitude < -180
                || $pickupLongitude > 180
                || $dropoffLongitude < -180
                || $dropoffLongitude > 180
                || $pickupLatitude < -90
                || $pickupLatitude > 90
                || $dropoffLatitude < -90
                || $dropoffLatitude > 90
            ) {
                return $this->errorResponse(
                    'Les coordonnées du trajet sont invalides.'
                );
            }

            try {
                $routeData = $routeCalculationService->calculate([
                    [$pickupLongitude, $pickupLatitude],
                    [$dropoffLongitude, $dropoffLatitude],
                ]);

                $summary = $routeData['features'][0]['properties']['summary']
                    ?? null;

                if (
                    !is_array($summary)
                    || !is_numeric($summary['distance'] ?? null)
                    || !is_numeric($summary['duration'] ?? null)
                ) {
                    throw new \RuntimeException(
                        'OpenRouteService returned an invalid route.'
                    );
                }

                $distanceKm = number_format(
                    (float) $summary['distance'] / 1000,
                    2,
                    '.',
                    ''
                );

                $durationMinutes = max(
                    1,
                    (int) round((float) $summary['duration'] / 60)
                );
            } catch (\Throwable $exception) {
                $logger->error(
                    'Reservation route recalculation failed.',
                    ['exception' => $exception]
                );

                return $this->errorResponse(
                    'Le calcul du trajet est temporairement indisponible.'
                );
            }

            $basePrice = $pricingService->calculate(
                $vehicleType,
                (float) $distanceKm,
                $durationMinutes,
                $pickupAddress,
                $dropoffAddress
            );

            $pricingMode = PricingMode::DISTANCE_TIME;
        }

        $childSeatRequested = $reservationType === ReservationType::LONG_DISTANCE
            && $request->request->getBoolean('child_seat');

        $reservation = (new Reservation())
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setPhone($phone)
            ->setPickupAddress($pickupAddress)
            ->setDropoffAddress($dropoffAddress)
            ->setScheduledAt($scheduledAt)
            ->setType($reservationType)
            ->setVehicleType($vehicleType)
            ->setPricingMode($pricingMode)
            ->setPassengers($passengers)
            ->setLuggage($luggage)
            ->setChildSeatRequested($childSeatRequested)
            ->setDistanceKm($distanceKm)
            ->setDurationMinutes($durationMinutes)
            ->setBasePrice($basePrice)
            ->setFinalPrice($basePrice)
            ->setPriceIsEstimated($basePrice !== null);

        $authenticatedUser = $this->getUser();

        if ($authenticatedUser instanceof User) {
            $reservation->setCustomer($authenticatedUser);
        }

        $loyaltyDiscountService->applyTo($reservation);

        if (
            $basePrice !== null
            && $reservation->getDiscountPercentage() > 0
        ) {
            $basePriceAmount = (float) $basePrice;
            $percentage = $reservation->getDiscountPercentage();

            $discountAmount = round(
                $basePriceAmount * $percentage / 100,
                2
            );

            $finalPrice = round(
                $basePriceAmount - $discountAmount,
                2
            );

            $reservation
                ->setDiscountAmount(
                    number_format($discountAmount, 2, '.', '')
                )
                ->setFinalPrice(
                    number_format($finalPrice, 2, '.', '')
                );
        }

        $entityManager->persist($reservation);
        $entityManager->flush();

        try {
            $confirmationMailer->send($reservation);
        } catch (\Throwable $exception) {
            $logger->error(
                'Reservation confirmation email could not be sent.',
                [
                    'reservationReference' => $reservation->getReference(),
                    'exception' => $exception,
                ]
            );
        }

        try {
            $notificationMailer->send($reservation);
        } catch (\Throwable $exception) {
            $logger->error(
                'Reservation owner notification could not be sent.',
                [
                    'reservationReference' => $reservation->getReference(),
                    'exception' => $exception,
                ]
            );
        }

        return $this->json(
            [
                'success' => true,
                'reference' => $reservation->getReference(),
                'message' => 'Votre réservation a bien été enregistrée.',
                'redirect' => '/confirmation.html',
            ],
            Response::HTTP_CREATED
        );
    }

    private function renderReservationPage(
        ReservationType $reservationType
    ): Response {
        if ($reservationType !== ReservationType::STANDARD) {
            return $this->render(
                'reservation/specialized.html.twig',
                [
                    'reservationType' => $reservationType,
                    'page' => $this->specializedPageConfig(
                        $reservationType
                    ),
                ]
            );
        }

        return $this->render(
            'reservation/index.html.twig',
            [
                'reservationType' => $reservationType,
                'reservationTypeLabel' => $reservationType->label(),
            ]
        );
    }

    /**
     * @return array{
     *     label: string,
     *     title: string,
     *     accent: string,
     *     description: string,
     *     image: string,
     *     imageAlt: string,
     *     pickupPlaceholder: string,
     *     dropoffPlaceholder: string,
     *     quickDestinations: list<array{label: string, address: string}>,
     *     childSeat: bool
     * }
     */
    private function specializedPageConfig(
        ReservationType $reservationType
    ): array {
        return match ($reservationType) {
            ReservationType::AIRPORT => [
                'label' => 'Transferts aéroports',
                'title' => 'Votre transfert',
                'accent' => 'sans stress.',
                'description' => 'Réservez votre chauffeur pour Orly, Charles-de-Gaulle ou Beauvais. Nous suivons votre demande et organisons une prise en charge ponctuelle.',
                'image' => '/images/reservation/airport-transfer.png',
                'imageAlt' => 'Chauffeur privé accueillant une cliente à l’aéroport',
                'pickupPlaceholder' => 'Votre adresse de départ',
                'dropoffPlaceholder' => 'Votre aéroport ou adresse d’arrivée',
                'quickDestinations' => [
                    [
                        'label' => 'Paris-CDG',
                        'address' => 'Aéroport Paris-Charles de Gaulle (CDG)',
                    ],
                    [
                        'label' => 'Paris-Orly',
                        'address' => 'Aéroport de Paris-Orly (ORY)',
                    ],
                    [
                        'label' => 'Beauvais',
                        'address' => 'Aéroport Paris-Beauvais (BVA)',
                    ],
                ],
                'childSeat' => false,
            ],
            ReservationType::STATION => [
                'label' => 'Gares parisiennes',
                'title' => 'Votre gare',
                'accent' => 'à l’heure.',
                'description' => 'Un chauffeur ponctuel pour vos départs et arrivées dans les principales gares parisiennes.',
                'image' => '/images/reservation/station-transfer.png',
                'imageAlt' => 'Chauffeur privé devant une grande gare parisienne',
                'pickupPlaceholder' => 'Votre adresse de départ',
                'dropoffPlaceholder' => 'Votre gare ou adresse d’arrivée',
                'quickDestinations' => [
                    [
                        'label' => 'Gare du Nord',
                        'address' => 'Gare du Nord, 18 rue de Dunkerque, Paris',
                    ],
                    [
                        'label' => 'Gare de Lyon',
                        'address' => 'Gare de Lyon, Place Louis-Armand, Paris',
                    ],
                    [
                        'label' => 'Montparnasse',
                        'address' => 'Gare Montparnasse, 17 boulevard de Vaugirard, Paris',
                    ],
                ],
                'childSeat' => false,
            ],
            ReservationType::BUSINESS => [
                'label' => 'Service professionnel',
                'title' => 'Vos rendez-vous',
                'accent' => 'sans compromis.',
                'description' => 'Un service discret et ponctuel pour vos rendez-vous, séminaires, événements et déplacements professionnels.',
                'image' => '/images/reservation/business-transfer.png',
                'imageAlt' => 'Chauffeur privé accueillant une cliente professionnelle',
                'pickupPlaceholder' => 'Entreprise, hôtel ou adresse de départ',
                'dropoffPlaceholder' => 'Lieu du rendez-vous ou destination',
                'quickDestinations' => [],
                'childSeat' => false,
            ],
            ReservationType::LONG_DISTANCE => [
                'label' => 'France et Europe',
                'title' => 'Voyagez loin.',
                'accent' => 'Restez serein.',
                'description' => 'Partez de Paris ou d’Île-de-France vers la destination de votre choix, partout en France et en Europe.',
                'image' => '/images/reservation/long-distance.png',
                'imageAlt' => 'Véhicule premium équipé pour une longue distance',
                'pickupPlaceholder' => 'Votre adresse de départ',
                'dropoffPlaceholder' => 'Ville ou adresse de destination',
                'quickDestinations' => [],
                'childSeat' => true,
            ],
            ReservationType::STANDARD => throw new \LogicException(
                'La course standard utilise la page avec calcul du trajet.'
            ),
        };
    }

    private function createScheduledDate(
        Request $request
    ): ?DateTimeImmutable {
        $date = $this->getValue($request, 'date');
        $time = $this->getValue($request, 'heure', 'time');

        if ($date === '') {
            return new DateTimeImmutable();
        }

        if ($time === '') {
            $time = '00:00';
        }

        $scheduledAt = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $date . ' ' . $time
        );

        return $scheduledAt ?: null;
    }

    private function getValue(
        Request $request,
        string ...$names
    ): string {
        foreach ($names as $name) {
            $value = $request->request->get($name);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function extractDecimal(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        $normalized = preg_replace(
            '/[^0-9.\-]/',
            '',
            $normalized
        );

        if (
            $normalized === null
            || $normalized === ''
            || !is_numeric($normalized)
        ) {
            return null;
        }

        return number_format(
            (float) $normalized,
            2,
            '.',
            ''
        );
    }

    private function extractInteger(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (!preg_match('/\d+/', $value, $matches)) {
            return null;
        }

        return (int) $matches[0];
    }

    private function errorResponse(
        string $message
    ): JsonResponse {
        return $this->json(
            [
                'success' => false,
                'message' => $message,
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
