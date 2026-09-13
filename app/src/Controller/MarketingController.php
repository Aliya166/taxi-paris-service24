<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\MarketingUnsubscribeTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MarketingController extends AbstractController
{
    #[Route(
        '/marketing/desinscription/{id}/{token}',
        name: 'app_marketing_unsubscribe',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function unsubscribe(
        User $user,
        string $token,
        MarketingUnsubscribeTokenService $tokenService,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$tokenService->isValid($user, $token)) {
            throw $this->createAccessDeniedException(
                'Lien de désinscription invalide.'
            );
        }

        $user->setEmailMarketingConsent(false);

        $entityManager->flush();

        return $this->render(
            'marketing/unsubscribe.html.twig',
            [
                'user' => $user,
            ]
        );
    }
}