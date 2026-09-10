<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ContactMailer;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

final class ContactMailerTest extends TestCase
{
    /**
     * @return array{name: string, email: string, phone: string, subject: string, message: string}
     */
    private function contactData(): array
    {
        return [
            'name' => 'Client Test',
            'email' => 'client@example.com',
            'phone' => '0612345678',
            'subject' => 'quote',
            'message' => 'Je souhaite recevoir un devis pour mon trajet.',
        ];
    }

    public function testItSendsContactMessageToOwner(): void
    {
        $data = $this->contactData();
        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (mixed $message): bool {
                self::assertInstanceOf(TemplatedEmail::class, $message);
                self::assertSame(
                    'owner@example.com',
                    $message->getTo()[0]->getAddress()
                );
                self::assertSame(
                    'client@example.com',
                    $message->getReplyTo()[0]->getAddress()
                );
                self::assertSame(
                    'emails/contact_notification.html.twig',
                    $message->getHtmlTemplate()
                );

                return true;
            }));

        (new ContactMailer($mailer, 'owner@example.com'))
            ->sendToOwner($data);
    }

    public function testItSendsAcknowledgementToCustomer(): void
    {
        $data = $this->contactData();
        $mailer = $this->createMock(MailerInterface::class);

        $mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (mixed $message): bool {
                self::assertInstanceOf(TemplatedEmail::class, $message);
                self::assertSame(
                    'client@example.com',
                    $message->getTo()[0]->getAddress()
                );
                self::assertSame(
                    'owner@example.com',
                    $message->getReplyTo()[0]->getAddress()
                );
                self::assertSame(
                    'emails/contact_acknowledgement.html.twig',
                    $message->getHtmlTemplate()
                );

                return true;
            }));

        (new ContactMailer($mailer, 'owner@example.com'))
            ->sendAcknowledgement($data);
    }
}
