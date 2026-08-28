<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Form\ProfileFormType;
use App\Repository\ReservationRepository;
use App\Security\ReservationAccessChecker;
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
        '/mon-compte/reservations/{id}/annuler',
        name: 'app_account_reservation_cancel',
        methods: ['POST']
    )]
    public function cancelReservation(
        Reservation $reservation,
        Request $request,
        EntityManagerInterface $entityManager,
        ReservationAccessChecker $accessChecker
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
