<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\ContactMailer;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    #[Route('/contact.html', name: 'app_contact_legacy', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContactMailer $contactMailer,
        LoggerInterface $logger
    ): Response {
        $user = $this->getUser();

        $data = [
            'name' => $user instanceof User
                ? trim($user->getFirstName() . ' ' . $user->getLastName())
                : '',
            'email' => $user instanceof User ? $user->getEmail() : '',
            'phone' => $user instanceof User ? ($user->getPhone() ?? '') : '',
            'subject' => 'reservation',
            'message' => '',
        ];
        $errors = [];

        if ($request->isMethod('POST')) {
            foreach (array_keys($data) as $field) {
                $data[$field] = trim($request->request->getString($field));
            }

            if (!$this->isCsrfTokenValid(
                'contact_message',
                $request->request->getString('_token')
            )) {
                $errors[] = 'Le formulaire a expiré. Veuillez réessayer.';
            }

            if ($data['name'] === '' || mb_strlen($data['name']) > 120) {
                $errors[] = 'Veuillez indiquer un nom valide.';
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Veuillez indiquer une adresse email valide.';
            }

            if (!in_array(
                $data['subject'],
                ['reservation', 'quote', 'business', 'other'],
                true
            )) {
                $errors[] = 'Veuillez sélectionner un sujet valide.';
            }

            if (
                mb_strlen($data['message']) < 10
                || mb_strlen($data['message']) > 3000
            ) {
                $errors[] = 'Votre message doit contenir entre 10 et 3 000 caractères.';
            }

            // Champ invisible : rempli uniquement par la plupart des robots.
            $isBot = $request->request->getString('website') !== '';

            if ($errors === [] && !$isBot) {
                try {
                    $contactMailer->sendToOwner($data);
                } catch (\Throwable $exception) {
                    $logger->error('Contact message could not be sent.', [
                        'contactEmail' => $data['email'],
                        'exception' => $exception,
                    ]);

                    $errors[] = 'Votre message n’a pas pu être envoyé. Veuillez réessayer ou nous appeler.';
                }

                if ($errors === []) {
                    try {
                        $contactMailer->sendAcknowledgement($data);
                    } catch (\Throwable $exception) {
                        $logger->error(
                            'Contact acknowledgement could not be sent.',
                            [
                                'contactEmail' => $data['email'],
                                'exception' => $exception,
                            ]
                        );
                    }
                }

                if ($errors === []) {
                    $this->addFlash(
                        'contact_success',
                        'Votre message a bien été envoyé. Notre équipe vous répondra dans les meilleurs délais.'
                    );

                    return $this->redirectToRoute('app_contact');
                }
            }

            if ($errors === [] && $isBot) {
                return $this->redirectToRoute('app_contact');
            }
        }

        return $this->render('contact/index.html.twig', [
            'contact' => $data,
            'errors' => $errors,
        ]);
    }
}
