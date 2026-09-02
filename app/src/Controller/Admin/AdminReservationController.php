<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\ReservationCancellationMailer;
use App\Service\ReservationStatusMailer;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminReservationController extends AbstractController
{
    #[Route(
        '/admin/reservations',
        name: 'app_admin_reservations',
        methods: ['GET']
    )]
    public function index(
        ReservationRepository $reservationRepository
    ): Response {
        $reservations = $reservationRepository->findBy(
            [],
            [
                'scheduledAt' => 'DESC',
                'createdAt' => 'DESC',
            ]
        );

        return $this->render(
            'admin/reservation/index.html.twig',
            [
                'reservations' => $reservations,
            ]
        );
    }

    #[Route(
        '/admin/reservations/{id}',
        name: 'app_admin_reservation_show',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function show(Reservation $reservation): Response
    {
        return $this->render(
            'admin/reservation/show.html.twig',
            [
                'reservation' => $reservation,
            ]
        );
    }

    #[Route(
        '/admin/reservations/{id}/statut/{action}',
        name: 'app_admin_reservation_status',
        requirements: [
            'id' => '\d+',
            'action' => 'confirm|complete|cancel',
        ],
        methods: ['POST']
    )]
    public function changeStatus(
        Reservation $reservation,
        string $action,
        Request $request,
        EntityManagerInterface $entityManager,
        ReservationStatusMailer $statusMailer,
        ReservationCancellationMailer $cancellationMailer,
        LoggerInterface $logger
    ): Response {
        $csrfToken = $request->request->getString('_token');

        if (
            !$this->isCsrfTokenValid(
                'admin_reservation_status_'
                    . $reservation->getId()
                    . '_'
                    . $action,
                $csrfToken
            )
        ) {
            throw $this->createAccessDeniedException(
                'Le jeton de sécurité est invalide.'
            );
        }

        try {
            match ($action) {
                'confirm' => $reservation->confirm(),
                'complete' => $reservation->complete(),
                'cancel' => $reservation->cancel(
                    'Annulation effectuée par l’administrateur.'
                ),
                default => throw $this->createNotFoundException(),
            };
        } catch (DomainException $exception) {
            $this->addFlash(
                'warning',
                $exception->getMessage()
            );

            return $this->redirectToRoute(
                'app_admin_reservations'
            );
        }

        $entityManager->flush();

        try {
            match ($action) {
                'confirm' => $statusMailer->sendConfirmed(
                    $reservation
                ),
                'complete' => $statusMailer->sendCompleted(
                    $reservation
                ),
                'cancel' => $cancellationMailer->sendToCustomer(
                    $reservation
                ),
                default => null,
            };
        } catch (\Throwable $exception) {
            $logger->error(
                'Reservation status email could not be sent.',
                [
                    'reservationReference' =>
                    $reservation->getReference(),
                    'action' => $action,
                    'exception' => $exception,
                ]
            );
        }

        $this->addFlash(
            'success',
            sprintf(
                'Le statut de la réservation %s a été mis à jour.',
                $reservation->getReference()
            )
        );

        return $this->redirectToRoute(
            'app_admin_reservations'
        );
    }
}
