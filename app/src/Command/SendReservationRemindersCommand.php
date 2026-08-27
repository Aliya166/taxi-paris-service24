<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReservationRepository;
use App\Service\ReservationReminderMailer;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:send-reminders',
    description: 'Envoie les rappels des trajets prévus dans trois jours.'
)]
final class SendReservationRemindersCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationReminderMailer $reminderMailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'date',
            null,
            InputOption::VALUE_REQUIRED,
            'Date de référence au format YYYY-MM-DD'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);
        $timezone = new DateTimeZone('Europe/Paris');

        $referenceDate = $this->createReferenceDate(
            $input->getOption('date'),
            $timezone
        );

        if ($referenceDate === null) {
            $io->error(
                'La date doit respecter le format YYYY-MM-DD.'
            );

            return Command::INVALID;
        }

        $from = $referenceDate
            ->modify('+3 days')
            ->setTime(0, 0);

        $until = $from->modify('+1 day');

        $reservations = $this->reservationRepository
            ->findReservationsAwaitingReminder($from, $until);

        if ($reservations === []) {
            $io->success(
                'Aucun rappel à envoyer pour les trajets du '
                . $from->format('d/m/Y')
                . '.'
            );

            return Command::SUCCESS;
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($reservations as $reservation) {
            try {
                $this->reminderMailer->send($reservation);
            } catch (\Throwable $exception) {
                ++$failedCount;

                $this->logger->error(
                    'Reservation reminder could not be queued.',
                    [
                        'reservationReference' =>
                            $reservation->getReference(),
                        'exception' => $exception,
                    ]
                );

                continue;
            }

            $reservation->markReminderAsSent(
                new DateTimeImmutable('now', $timezone)
            );

            $this->entityManager->flush();

            ++$sentCount;
        }

        $io->success(sprintf(
            '%d rappel(s) ajouté(s) à la file. %d échec(s).',
            $sentCount,
            $failedCount
        ));

        return $failedCount > 0
            ? Command::FAILURE
            : Command::SUCCESS;
    }

    private function createReferenceDate(
        mixed $dateOption,
        DateTimeZone $timezone
    ): ?DateTimeImmutable {
        if ($dateOption === null) {
            return new DateTimeImmutable('today', $timezone);
        }

        if (
            !is_string($dateOption)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOption)
        ) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $dateOption,
            $timezone
        );

        if (
            $date === false
            || $date->format('Y-m-d') !== $dateOption
        ) {
            return null;
        }

        return $date;
    }
}