<?php
if(class_exists('Aoe_Scheduler_Model_Observer')) {
    class ParentClass extends Aoe_Scheduler_Model_Observer {}
}
else {
    class ParentClass extends Mage_Cron_Model_Observer {}
}


class ProxiBlue_NewRelic_Model_Cron_Observer extends ParentClass
{
    /**
     * @var string
     */
    protected $cronName;

    /**
     * ProxiBlue_NewRelic_Model_Cron_Observer constructor.
     */
    public function __construct()
    {
        $this->cronName = Mage::getStoreConfig('newrelic/api/cron_appname');
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function dispatch($observer)
    {
        if (extension_loaded('newrelic')) {
            newrelic_set_appname($this->cronName);
        }
        parent::dispatch($observer);

        if (extension_loaded('newrelic')) {
            newrelic_end_transaction();
            newrelic_start_transaction($this->cronName);
        }
    }

    /**
     * Hook into Aeo_Scheduler cron processing
     *
     * @param Varien_Event_Observer $observer
     */
    public function aoeJobBefore(Varien_Event_Observer $observer)
    {
        /** @var Aoe_Scheduler_Model_Schedule $schedule */
        $schedule = $observer->getSchedule();
        if (extension_loaded('newrelic')) {
            newrelic_end_transaction();
            newrelic_start_transaction($this->cronName);
            newrelic_name_transaction('cron/job/' . $schedule->getJobCode());
        }
    }

    /**
     * Name each job when processed by default magento cron class
     *
     * @param Mage_Cron_Model_Schedule $schedule
     * @param $jobConfig
     * @param bool $isAlways
     * @return Mage_Cron_Model_Observer
     */
    protected function _processJob($schedule, $jobConfig, $isAlways = false)
    {
        if (Mage::registry('current_cron_job')) {
            Mage::unregister('current_cron_job');
        }
        Mage::register('current_cron_job', $schedule->getJobCode());

        if (extension_loaded('newrelic')) {
            newrelic_start_transaction($this->cronName);
            newrelic_name_transaction('cron/job/' . $schedule->getJobCode());
        }

        parent::_processJob($schedule, $jobConfig, $isAlways);

        return $this;
    }
}
