<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Service\MarketingMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:marketing:send',
    description: 'Envoie un email marketing à un utilisateur ayant donné son consentement.'
)]
final class SendMarketingEmailCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MarketingMailer $marketingMailer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Adresse email du client'
            )
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'Type: welcome ou offer'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $email = strtolower(
            trim((string) $input->getArgument('email'))
        );

        $type = strtolower(
            trim((string) $input->getArgument('type'))
        );

        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy([
                'email' => $email,
            ]);

        if (!$user instanceof User) {
            $output->writeln(
                '<error>Utilisateur introuvable.</error>'
            );

            return Command::FAILURE;
        }

        if (!$user->hasEmailMarketingConsent()) {
            $output->writeln(
                '<error>Le client n’a pas donné son consentement marketing.</error>'
            );

            return Command::FAILURE;
        }

        $sent = match ($type) {
            'welcome' =>
                $this->marketingMailer->sendWelcomeEmail($user),

            'offer' =>
                $this->marketingMailer->sendOfferEmail($user),

            default => false,
        };

        if (!$sent) {
            $output->writeln(
                '<error>Email non envoyé.</error>'
            );

            return Command::FAILURE;
        }

        if (!in_array($type, ['welcome', 'offer'], true)) {
            $output->writeln(
                '<error>Type invalide. Utilisez welcome ou offer.</error>'
            );

            return Command::INVALID;
        }

        $output->writeln(
            sprintf(
                '<info>Email "%s" envoyé à %s.</info>',
                $type,
                $email
            )
        );

        return Command::SUCCESS;
    }
}