<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use App\Repository\ReservationRepository;
use App\Service\ReservationCancellationMailer;
use App\Service\ReservationStatusMailer;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use DateTimeImmutable;
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
        Request $request,
        ReservationRepository $reservationRepository
    ): Response {
        $search = trim($request->query->getString('q'));
        $statusValue = $request->query->getString('status');
        $dateFromValue = $request->query->getString('date_from');
        $dateToValue = $request->query->getString('date_to');

        $selectedStatus = ReservationStatus::tryFrom($statusValue);
        $dateFrom = $this->parseDate($dateFromValue);
        $dateTo = $this->parseDate($dateToValue);

        $invalidDateRange = $dateFrom !== null
            && $dateTo !== null
            && $dateFrom > $dateTo;

        if ($invalidDateRange) {
            $this->addFlash(
                'warning',
                'La date de début doit être antérieure à la date de fin.'
            );

            $reservations = [];
        } else {
            $reservations = $reservationRepository->findForAdmin(
                $search,
                $selectedStatus,
                $dateFrom,
                $dateTo?->modify('+1 day')
            );
        }

        return $this->render(
            'admin/reservation/index.html.twig',
            [
                'reservations' => $reservations,
                'search' => $search,
                'selectedStatus' => $selectedStatus?->value,
                'selectedDateFrom' => $dateFromValue,
                'selectedDateTo' => $dateToValue,
                'statuses' => ReservationStatus::cases(),
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

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                $errors !== false
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
        ) {
            return null;
        }

        return $date;
    }
}
