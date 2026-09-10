<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationType;
use App\Enum\VehicleType;
use App\Service\LoyaltyDiscountService;
use App\Service\ReservationConfirmationMailer;
use App\Service\ReservationPricingService;
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

        $distanceKm = $this->extractDecimal(
            $this->getValue($request, 'distanceKm', 'Distance')
        );

        $durationMinutes = $this->extractInteger(
            $this->getValue($request, 'durationMinutes', 'Durée')
        );

        if ($distanceKm === null || $durationMinutes === null) {
            return $this->errorResponse(
                'Veuillez calculer votre trajet avant de réserver.'
            );
        }

        $basePrice = $pricingService->calculate(
            $vehicleType,
            (float) $distanceKm,
            $durationMinutes,
            $pickupAddress,
            $dropoffAddress
        );

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
            ->setPassengers($passengers)
            ->setLuggage($luggage)
            ->setDistanceKm($distanceKm)
            ->setDurationMinutes($durationMinutes)
            ->setBasePrice($basePrice)
            ->setFinalPrice($basePrice)
            ->setPriceIsEstimated(true);

        $authenticatedUser = $this->getUser();

        if ($authenticatedUser instanceof User) {
            $reservation->setCustomer($authenticatedUser);
        }

        $loyaltyDiscountService->applyTo($reservation);

        if ($reservation->getDiscountPercentage() > 0) {
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
        return $this->render(
            'reservation/index.html.twig',
            [
                'reservationType' => $reservationType,
                'reservationTypeLabel' => $reservationType->label(),
            ]
        );
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
