<?php

class ProxiBlue_NewRelic_Model_Cron_Observer extends Mage_Cron_Model_Observer
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
    protected function _processJob($schedule, $jobConfig, $isAlways = false)
    {
        if (Mage::registry('current_cron_job')) {
            Mage::unregister('current_cron_job');
        }
        Mage::register('current_cron_job', $schedule->getJobCode());

        if (extension_loaded('newrelic')) {
            newrelic_end_transaction();
            newrelic_start_transaction($this->cronName);
            newrelic_name_transaction('cron/job/' . $schedule->getJobCode());
        }

        return parent::_processJob($schedule, $jobConfig, $isAlways);
    }
}
