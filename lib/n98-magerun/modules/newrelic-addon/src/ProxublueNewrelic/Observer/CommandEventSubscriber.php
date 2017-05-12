<?php

namespace ProxublueNewrelic\Observer;

use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class CommandEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array('console.command' => 'setCronTransactionName');
    }

    public function setCronTransactionName(ConsoleEvent $event)
    {
        /** @var \N98\Magento\Command\AbstractMagentoCommand $command */
        $command = $event->getCommand();

        /** @var \N98\Magento\Application $application */
        $application = $command->getApplication();

        if ($command->getName() !== 'sys:cron:run') {
            return;
        }

        if (!extension_loaded('newrelic')) {
            return;
        }

        $application->initMagento();
        $jobCode = end($_SERVER['argv']);
        $cronName = \Mage::getStoreConfig('newrelic/api/cron_appname');
        newrelic_end_transaction();
        newrelic_start_transaction($cronName);
        newrelic_name_transaction('cron/job/' . $jobCode);

    }
}