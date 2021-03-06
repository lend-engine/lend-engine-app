<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReservationRemindersCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this->setName('reservation_reminders');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var \AppBundle\Services\Schedule\EmailReservationReminders $scheduleHandler */
        $scheduleHandler = $this->getContainer()->get('service.schedule_reservation_reminders');
        $results = $scheduleHandler->processReservationReminders();
        die($results);
    }

}