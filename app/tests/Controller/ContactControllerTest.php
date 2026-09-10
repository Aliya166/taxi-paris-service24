<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ContactControllerTest extends WebTestCase
{
    public function testContactPageIsAvailable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Parlons de');
        self::assertSelectorExists('form.contact-form');
    }

    public function testInvalidContactMessageIsRejected(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/contact', [
            '_token' => $token,
            'name' => '',
            'email' => 'email-invalide',
            'phone' => '',
            'subject' => 'reservation',
            'message' => 'Court',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSelectorExists('.contact-alert--error');
        self::assertEmailCount(0);
    }

    public function testValidContactMessageIsAccepted(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/contact', [
            '_token' => $token,
            'name' => 'Client Test',
            'email' => 'client@example.com',
            'phone' => '0612345678',
            'subject' => 'quote',
            'message' => 'Je souhaite recevoir un devis pour mon trajet.',
        ]);

        self::assertResponseRedirects('/contact');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '.contact-alert--success',
            'Votre message a bien été envoyé.'
        );
    }
}
