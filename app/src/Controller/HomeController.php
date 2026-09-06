<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    #[Route('/index.html', name: 'app_home_legacy', methods: ['GET'])]
    public function index(
        ReservationRepository $reservationRepository
    ): Response {
        $user = $this->getUser();

        $loyaltyProgress = null;
        $ridesUntilDiscount = null;

        if ($user instanceof User) {
            $completedRides = $reservationRepository
                ->countCompletedInCurrentLoyaltyCycleByCustomer($user);

            $loyaltyProgress = min(5, $completedRides);
            $ridesUntilDiscount = max(0, 5 - $loyaltyProgress);
        }

        return $this->render('home/index.html.twig', [
            'loyaltyProgress' => $loyaltyProgress,
            'ridesUntilDiscount' => $ridesUntilDiscount,
        ]);
    }
}