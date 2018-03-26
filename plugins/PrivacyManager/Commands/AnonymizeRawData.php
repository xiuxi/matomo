<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\PrivacyManager\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Plugin\ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnonymizeRawData extends ConsoleCommand
{
    protected function configure()
    {
        $defaultDate = '2008-01-01,' . Date::now()->toString();

        $this->setName('privacymanager:anonymize-some-raw-data');
        $this->setDescription('Anonymize some of the stored raw data (logs). The reason it only anonymizes "some" data is that personal data can be present in many various data collection points, for example some of your page URLs or page titles may include personal data and these will not be anonymized by this command as it is not possible to detect personal data for example in a URL automatically.');
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date or date range to invalidate log data for (UTC). Either a date like "2015-01-03" or a range like "2015-01-05,2015-02-12". By default, all data including today will be anonymized.', $defaultDate);
        $this->addOption('unset-visit-columns', null, InputOption::VALUE_REQUIRED, 'Comma seperated list of log_visit columns that you want to unset. Each value for that column will be set to its default value. This action cannot be undone.', '');
        $this->addOption('unset-link-visit-action-columns', null, InputOption::VALUE_REQUIRED, 'Comma seperated list of log_link_visit_action columns that you want to unset. Each value for that column will be set to its default value. This action cannot be undone.', '');
        $this->addOption('anonymize-ip', null, InputOption::VALUE_NONE, 'If set, the IP will be anonymized with a mask of at least 2. This action cannot be undone.');
        $this->addOption('anonymize-location', null, InputOption::VALUE_NONE, 'If set, the location will be re-evaluated based on the anonymized IP. This action cannot be undone.');
        $this->addOption('idsites', null, InputOption::VALUE_REQUIRED, 'By default, the data of all idSites will be anonymized or unset. However, you can specify a set of idSites to execute this command only on these idsites.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = $input->getOption('date');
        $visitColumnsToUnset = $input->getOption('unset-visit-columns');
        if (!empty($visitColumnsToUnset)) {
            $visitColumnsToUnset = explode(',', $visitColumnsToUnset);
        }
        $linkVisitActionColumns = $input->getOption('unset-link-visit-action-columns');
        if (!empty($linkVisitActionColumns)) {
            $linkVisitActionColumns = explode(',', $linkVisitActionColumns);
        }

        $idSites = $input->getOption('idsites');
        if (!empty($idSites)) {
            $idSites = explode(',', $idSites);
            $idSites = array_map('intval', $idSites);
        } else {
            $idSites = null;
        }
        $anonymizeIp = $input->getOption('anonymize-ip');
        $anonymizeLocation = $input->getOption('anonymize-location');

        $scheduledLogDataAnonymizer = StaticContainer::get('Piwik\Plugins\PrivacyManager\Model\ScheduledLogDataAnonymization');

        list($startDate, $endDate) = $scheduledLogDataAnonymizer->getStartAndEndDate($date);
        $output->writeln(sprintf('Start date is "%s", end date is "%s"', $startDate, $endDate));

        if (($anonymizeIp || $anonymizeLocation) && !$this->confirmAnonymize($input, $output, 'anonymize visit IP and/or location', $startDate, $endDate)) {
            $anonymizeIp = false;
            $anonymizeLocation = false;
            $output->writeln('<info>SKIPPING anonymizing IP and/or location.</info>');
        }
        if (!empty($visitColumnsToUnset) && !$this->confirmAnonymize($input, $output, 'unset the log_visit columns "' . implode(', ', $visitColumnsToUnset) . '"', $startDate, $endDate)) {
            $visitColumnsToUnset = false;
            $output->writeln('<info>SKIPPING unset log_visit columns.</info>');
        }
        if (!empty($linkVisitActionColumns) && !$this->confirmAnonymize($input, $output, 'unset the log_link_visit_action columns "' . implode(', ', $linkVisitActionColumns) . '"', $startDate, $endDate)) {
            $linkVisitActionColumns = false;
            $output->writeln('<info>SKIPPING unset log_link_visit_action columns.</info>');
        }

        if ($linkVisitActionColumns || $visitColumnsToUnset || $anonymizeLocation || $anonymizeIp) {
            $scheduledLogDataAnonymizer->setCallbackOnOutput(function ($message) use ($output) {
                $output->writeln($message);
            });
            $index = $scheduledLogDataAnonymizer->scheduleEntry('Command line', $idSites, $date, $anonymizeIp, $anonymizeLocation, $visitColumnsToUnset, $linkVisitActionColumns, $isStarted = true);
            $scheduledLogDataAnonymizer->executeScheduledEntry($index);
        }

        $output->writeln('Done');
    }

    private function confirmAnonymize(InputInterface $input, OutputInterface $output, $action, $startDate, $endDate)
    {
        $noInteraction = $input->getOption('no-interaction');
        if ($noInteraction) {
            return true;
        }
        $dialog = $this->getHelperSet()->get('dialog');
        $value = $dialog->ask(
            $output,
            sprintf('<question>Are you sure you want to %s for all visits between "%s" to "%s"? This action cannot be undone. Type "OK" to confirm this section.</question>', $action, $startDate, $endDate),
            false
        );
        if ($value !== 'OK') {
            return false;
        }
        return true;
    }
}
