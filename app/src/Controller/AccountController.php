<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Form\ProfileFormType;
use App\Enum\PricingMode;
use App\Form\ReservationEditFormType;
use App\Service\ReservationPricingService;
use App\Service\RouteCalculationService;
use App\Repository\ReservationRepository;
use App\Security\ReservationAccessChecker;
use App\Service\ReservationCancellationMailer;
use App\Service\ReservationModificationMailer;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/mon-compte', name: 'app_account', methods: ['GET'])]
    public function index(
        ReservationRepository $reservationRepository
    ): Response {
        $user = $this->getAuthenticatedUser();

        $reservations = $reservationRepository
            ->findForCustomerAccount($user);

        $completedRidesInCurrentCycle = $reservationRepository
            ->countCompletedInCurrentLoyaltyCycleByCustomer($user);

        $loyaltyProgress = min(5, $completedRidesInCurrentCycle);


        return $this->render('account/index.html.twig', [
            'user' => $user,
            'reservations' => $reservations,
            'completedRides' => $loyaltyProgress,
            'ridesUntilDiscount' => max(
                0,
                5 - $loyaltyProgress
            ),
        ]);
    }

    #[Route(
        '/mon-compte/modifier',
        name: 'app_account_edit',
        methods: ['GET', 'POST']
    )]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $this->getAuthenticatedUser();

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Vos informations ont été mises à jour avec succès.'
            );

            return $this->redirectToRoute('app_account');
        }

        return $this->render('account/edit.html.twig', [
            'profileForm' => $form,
            'user' => $user,
        ]);
    }

    #[Route(
        '/mon-compte/reservations/{id}/modifier',
        name: 'app_account_reservation_edit',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST']
    )]
    public function editReservation(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        ReservationAccessChecker $accessChecker,
        ReservationPricingService $pricingService,
        RouteCalculationService $routeCalculationService,
        ReservationModificationMailer $modificationMailer,
        LoggerInterface $logger
    ): Response {
        $user = $this->getAuthenticatedUser();

        if (!$accessChecker->canManage($user, $reservation)) {
            throw $this->createAccessDeniedException(
                'Vous ne pouvez pas modifier cette réservation.'
            );
        }

        if ($reservation->getStatus() !== ReservationStatus::PENDING) {
            $this->addFlash(
                'warning',
                'Cette réservation ne peut plus être modifiée.'
            );

            return $this->redirectToRoute('app_account');
        }

        $originalPickupAddress = $reservation->getPickupAddress();
        $originalDropoffAddress = $reservation->getDropoffAddress();
        $originalVehicleType = $reservation->getVehicleType();

        $form = $this->createForm(
            ReservationEditFormType::class,
            $reservation
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $vehicleType = $reservation->getVehicleType();

            if ($reservation->getPassengers() > $vehicleType->maxPassengers()) {
                $this->addFlash(
                    'warning',
                    sprintf(
                        'Le véhicule %s accepte au maximum %d passager(s).',
                        $vehicleType->label(),
                        $vehicleType->maxPassengers()
                    )
                );

                return $this->render(
                    'account/reservation_edit.html.twig',
                    [
                        'reservation' => $reservation,
                        'reservationEditForm' => $form,
                    ]
                );
            }

            if ($reservation->getLuggage() > $vehicleType->maxLuggage()) {
                $this->addFlash(
                    'warning',
                    sprintf(
                        'Le véhicule %s accepte au maximum %d bagage(s).',
                        $vehicleType->label(),
                        $vehicleType->maxLuggage()
                    )
                );

                return $this->render(
                    'account/reservation_edit.html.twig',
                    [
                        'reservation' => $reservation,
                        'reservationEditForm' => $form,
                    ]
                );
            }

            $addressesChanged =
                $reservation->getPickupAddress() !== $originalPickupAddress
                || $reservation->getDropoffAddress() !== $originalDropoffAddress;

            $vehicleChanged =
                $reservation->getVehicleType() !== $originalVehicleType;

            if ($reservation->getType()->value === 'standard') {
                $distanceKm = $reservation->getDistanceKm();
                $durationMinutes = $reservation->getDurationMinutes();

                if ($addressesChanged) {
                    $pickupLongitude = $form
                        ->get('pickupLongitude')
                        ->getData();

                    $pickupLatitude = $form
                        ->get('pickupLatitude')
                        ->getData();

                    $dropoffLongitude = $form
                        ->get('dropoffLongitude')
                        ->getData();

                    $dropoffLatitude = $form
                        ->get('dropoffLatitude')
                        ->getData();

                    if (
                        !is_numeric($pickupLongitude)
                        || !is_numeric($pickupLatitude)
                        || !is_numeric($dropoffLongitude)
                        || !is_numeric($dropoffLatitude)
                    ) {
                        $this->addFlash(
                            'warning',
                            'Veuillez sélectionner les nouvelles adresses dans les suggestions proposées.'
                        );

                        return $this->render(
                            'account/reservation_edit.html.twig',
                            [
                                'reservation' => $reservation,
                                'reservationEditForm' => $form,
                            ]
                        );
                    }

                    try {
                        $routeData = $routeCalculationService->calculate([
                            [
                                (float) $pickupLongitude,
                                (float) $pickupLatitude,
                            ],
                            [
                                (float) $dropoffLongitude,
                                (float) $dropoffLatitude,
                            ],
                        ]);

                        $summary =
                            $routeData['features'][0]['properties']['summary']
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
                            (int) round(
                                (float) $summary['duration'] / 60
                            )
                        );

                        $reservation
                            ->setDistanceKm($distanceKm)
                            ->setDurationMinutes($durationMinutes);
                    } catch (\Throwable $exception) {
                        $logger->error(
                            'Reservation edit route recalculation failed.',
                            [
                                'reservationReference' =>
                                $reservation->getReference(),
                                'exception' => $exception,
                            ]
                        );

                        $this->addFlash(
                            'warning',
                            'Le nouveau trajet n’a pas pu être calculé. Veuillez réessayer.'
                        );

                        return $this->render(
                            'account/reservation_edit.html.twig',
                            [
                                'reservation' => $reservation,
                                'reservationEditForm' => $form,
                            ]
                        );
                    }
                }

                if ($addressesChanged || $vehicleChanged) {
                    if ($distanceKm === null || $durationMinutes === null) {
                        $this->addFlash(
                            'warning',
                            'Les informations du trajet sont incomplètes. Veuillez réessayer.'
                        );

                        return $this->render(
                            'account/reservation_edit.html.twig',
                            [
                                'reservation' => $reservation,
                                'reservationEditForm' => $form,
                            ]
                        );
                    }

                    $basePrice = $pricingService->calculate(
                        $reservation->getVehicleType(),
                        (float) $distanceKm,
                        $durationMinutes,
                        (string) $reservation->getPickupAddress(),
                        (string) $reservation->getDropoffAddress()
                    );

                    $reservation
                        ->setBasePrice($basePrice)
                        ->setPricingMode(PricingMode::DISTANCE_TIME)
                        ->setPriceIsEstimated(true);

                    $discountPercentage =
                        $reservation->getDiscountPercentage();

                    if ($discountPercentage > 0) {
                        $discountAmount = round(
                            (float) $basePrice
                                * $discountPercentage
                                / 100,
                            2
                        );

                        $finalPrice = round(
                            (float) $basePrice - $discountAmount,
                            2
                        );

                        $reservation
                            ->setDiscountAmount(
                                number_format(
                                    $discountAmount,
                                    2,
                                    '.',
                                    ''
                                )
                            )
                            ->setFinalPrice(
                                number_format(
                                    $finalPrice,
                                    2,
                                    '.',
                                    ''
                                )
                            );
                    } else {
                        $reservation
                            ->setDiscountAmount('0.00')
                            ->setFinalPrice($basePrice);
                    }
                }
            }

            $entityManager->flush();

            try {
                $modificationMailer->sendToCustomer(
                    $reservation
                );
            } catch (\Throwable $exception) {
                $logger->error(
                    'Reservation modification customer email could not be sent.',
                    [
                        'reservationReference' =>
                        $reservation->getReference(),
                        'exception' => $exception,
                    ]
                );
            }

            try {
                $modificationMailer->sendToOwner(
                    $reservation
                );
            } catch (\Throwable $exception) {
                $logger->error(
                    'Reservation modification owner notification could not be sent.',
                    [
                        'reservationReference' =>
                        $reservation->getReference(),
                        'exception' => $exception,
                    ]
                );
            }

            $this->addFlash(
                'success',
                'Votre réservation a été modifiée avec succès.'
            );

            return $this->redirectToRoute('app_account');
        }

        return $this->render(
            'account/reservation_edit.html.twig',
            [
                'reservation' => $reservation,
                'reservationEditForm' => $form,
            ]
        );
    }

    #[Route(
        '/mon-compte/reservations/{id}/annuler',
        name: 'app_account_reservation_cancel',
        methods: ['POST']
    )]
    public function cancelReservation(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        ReservationAccessChecker $accessChecker,
        ReservationCancellationMailer $cancellationMailer,
        LoggerInterface $logger
    ): Response {
        $user = $this->getAuthenticatedUser();

        if (!$accessChecker->canManage($user, $reservation)) {
            throw $this->createAccessDeniedException(
                'Vous ne pouvez pas modifier cette réservation.'
            );
        }

        $csrfToken = $request->request->getString('_token');

        if (
            !$this->isCsrfTokenValid(
                'cancel_reservation_' . $reservation->getId(),
                $csrfToken
            )
        ) {
            throw $this->createAccessDeniedException(
                'Le jeton de sécurité est invalide.'
            );
        }

        if (
            !in_array(
                $reservation->getStatus(),
                [
                    ReservationStatus::PENDING,
                    ReservationStatus::CONFIRMED,
                ],
                true
            )
        ) {
            $this->addFlash(
                'warning',
                'Cette réservation ne peut plus être annulée.'
            );

            return $this->redirectToRoute('app_account');
        }

        $reservation->cancel(
            'Annulation demandée par le client.'
        );

        $entityManager->flush();

        try {
            $cancellationMailer->sendToCustomer($reservation);
        } catch (\Throwable $exception) {
            $logger->error(
                'Reservation cancellation email could not be sent.',
                [
                    'reservationReference' => $reservation->getReference(),
                    'exception' => $exception,
                ]
            );
        }

        try {
            $cancellationMailer->sendToOwner($reservation);
        } catch (\Throwable $exception) {
            $logger->error(
                'Reservation cancellation owner notification could not be sent.',
                [
                    'reservationReference' => $reservation->getReference(),
                    'exception' => $exception,
                ]
            );
        }

        $this->addFlash(
            'success',
            'Votre réservation a été annulée avec succès.'
        );

        return $this->redirectToRoute('app_account');
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
