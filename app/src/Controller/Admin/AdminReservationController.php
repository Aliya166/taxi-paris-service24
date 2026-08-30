<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}