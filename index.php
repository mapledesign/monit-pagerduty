<?php
require __DIR__.'/vendor/autoload.php';

/**
 *  http://mmonit.com/monit/documentation/monit.html
 *  Monit standard envirionment variables
 *  MONIT_EVENT: The event that occurred on the service
 *  MONIT_DESCRIPTION : A description of the error condition
 *  MONIT_SERVICE : The name of the service (from monitrc) on which the event occurred.
 *  MONIT_DATE : The time and date (RFC 822 style) the event occurred
 *  MONIT_HOST : The host the event occurred on
 *
 *  The following environment variables are only available for process service entries:
 *    MONIT_PROCESS_PID: The process pid. This may be 0 if the process was (re)started,
 *    MONIT_PROCESS_MEMORY : Process memory. This may be 0 if the process was (re)started,
 *    MONIT_PROCESS_CHILDREN : Process children. This may be 0 if the process was (re)started,
 *    MONIT_PROCESS_CPU_PERCENT : Process cpu%. This may be 0 if the process was (re)started,
 *
 * The following environment variables are only available for check program start/stop/restart program and exec action context:
 *    MONIT_PROGRAM_STATUS : The program status (exit value).
 */


use PagerDuty\Exceptions\PagerDutyException;
use PagerDuty\TriggerEvent;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;

(new SingleCommandApplication())
    ->setName('monit-pagerduty')
    ->setVersion('1.0.0')
    ->addArgument('subject', InputArgument::OPTIONAL)
    ->addOption('trigger', null, InputOption::VALUE_NONE)
    ->addOption('resolve', null, InputOption::VALUE_NONE)
    ->setCode(
        function (InputInterface $input, OutputInterface $output) {
            //file_put_contents('/tmp/monit-'.date('Ymd-his'), var_export($_SERVER, true));
            $io = new SymfonyStyle($input, $output);
            error_reporting(E_ALL & ~E_NOTICE);
            $ini = parse_ini_file('/etc/monit-pagerduty.conf');
            if (!isset($ini['key']) || $ini['key'] === '') {
                $io->error("key missing in /etc/monit-pagerduty.conf");
                return 1;
            }
            $integrationKey = $ini['key'];
            $subject = $input->getArgument('subject') ?? "{$_SERVER['MONIT_SERVICE']}: {$_SERVER['MONIT_DESCRIPTION']}";
            $source = currenthostname().':'.$subject;
            $deDupKey = md5($source);

            try {
                if ($input->getOption('trigger')!== false) {
                    $event = new TriggerEvent(
                        $integrationKey,
                        $_SERVER['MONIT_DESCRIPTION'] ?
                            "{$_SERVER['MONIT_SERVICE']}: {$_SERVER['MONIT_EVENT']}: {$_SERVER['MONIT_DESCRIPTION']}"
                            : 'Unknown error',
                        $source,
                        TriggerEvent::ERROR
                    );
                    $event
                        //->setPayloadTimestamp("2015-07-17T08:42:58.315+0000")
                        ->setPayloadComponent($_SERVER['MONIT_SERVICE'])
                        //->setPayloadGroup("prod-datapipe")
                        //->setPayloadClass("deploy")
                        ->setPayloadCustomDetails(
                            [
                                'MONIT_EVENT' => $_SERVER['MONIT_EVENT'],
                                'MONIT_DESCRIPTION' => $_SERVER['MONIT_DESCRIPTION'],
                                'MONIT_SERVICE' => $_SERVER['MONIT_SERVICE'],
                                'MONIT_DATE' => $_SERVER['MONIT_DATE'],
                                'MONIT_HOST' => $_SERVER['MONIT_HOST'],
                                'MONIT_PROCESS_PID' => $_SERVER['MONIT_PROCESS_PID'],
                                'MONIT_PROCESS_MEMORY' => $_SERVER['MONIT_PROCESS_MEMORY'],
                                'MONIT_PROCESS_CHILDREN' => $_SERVER['MONIT_PROCESS_CHILDREN'],
                                'MONIT_PROCESS_CPU_PERCENT' => $_SERVER['MONIT_PROCESS_CPU_PERCENT'],
                                'MONIT_PROGRAM_STATUS' => $_SERVER['MONIT_PROGRAM_STATUS'],
                            ]
                        )
                        ->setDeDupKey($deDupKey)
                        //->addLink("https://example.com/", "Link text")
                        //->addImage("https://www.pagerduty.com/wp-content/uploads/2016/05/pagerduty-logo-green.png", "https://example.com/", "Example text"))
                    ;
                } elseif ($input->getOption('resolve') !== false) {
                    $event = new \PagerDuty\ResolveEvent($integrationKey, $deDupKey);
                } else {
                    $io->error("You must pass --trigger or --resolve");

                    return 1;
                }

                $responseCode = $event->send();
                if ($responseCode == 202) {
                    return 0;
                } elseif ($responseCode == 429) {
                    $io->error("Rate limited. Try again in a bit");

                    return 1;
                } else {
                    $io->error("Error. 400 = Invalid JSON; other error = try again later");
                    var_dump($responseCode, $event);

                    return 1;
                }

            } catch (PagerDutyException $exception) {
                var_dump($exception->getErrors());

                return 1;
            }

        }
    )
    ->run();

function currenthostname(): string
{
    $host = $_SERVER['MONIT_HOST'];
    if ( ! $host) {
        $host = gethostname() ?: 'unknown';
    }

    return $host;
}