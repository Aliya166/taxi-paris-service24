<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TelegramReservationNotifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:telegram:test',
    description: 'Envoie un message de test sur Telegram.'
)]
final class TestTelegramCommand extends Command
{
    public function __construct(
        private readonly TelegramReservationNotifier $telegramNotifier
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $this->telegramNotifier->sendTestMessage();

        $output->writeln(
            '<info>Message Telegram envoyé.</info>'
        );

        return Command::SUCCESS;
    }
}