<?php

namespace AppBundle\Services\Schedule;

use AppBundle\Entity\Membership;
use AppBundle\Entity\Note;
use AppBundle\Services\SettingsService;
use Doctrine\ORM\EntityManager;
use Postmark\PostmarkClient;
use Postmark\Models\PostmarkException;
use Doctrine\DBAL\Driver\PDOException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

class EmailOverdueLoans
{
    /** @var \Twig_Environment  */
    private $twig;

    /** @var Container  */
    private $container;

    /** @var \AppBundle\Services\SettingsService */
    private $settings;

    /** @var EntityManager */
    private $em;

    private $serverName;

    private $logger;

    public function __construct(\Twig_Environment $twig, Container $container, SettingsService $settings, EntityManager $em, LoggerInterface $logger)
    {
        $this->twig = $twig;
        $this->container = $container;
        $this->settings = $settings;
        $this->em = $em;
        $this->logger = $logger;

        if (!$this->serverName = getenv('LE_SERVER_NAME')) {
            throw new \Exception("LE_SERVER_NAME is not defined");
        }
    }

    /**
     * Send an email to each member where a loan contains overdue items
     * @return string
     */
    public function processOverdueEmails()
    {
        /** @var \AppBundle\Services\TenantService $tenantService */
        $tenantService = $this->container->get('service.tenant');

        $startTime = microtime(true);

        $resultString = '';

        $repo = $this->em->getRepository('AppBundle:Tenant');
        $tenants = $repo->findBy(['server' => $this->serverName, 'status' => 'LIVE']);

        $resultString .= 'Number of tenants = '.count($tenants).PHP_EOL;

        foreach ($tenants AS $tenant) {

            /** @var $tenant \AppBundle\Entity\Tenant */
            $tenantDbSchema = $tenant->getDbSchema();
            $tenantStatus   = $tenant->getStatus();
            $tenantPlan     = $tenant->getPlan();

            $resultString .= '  '.$tenant->getName().', '.$tenantStatus;

            if ($tenantPlan == 'free') {
                $resultString .= '    ... skipping (free plan)'.PHP_EOL;
                continue;
            }

            $resultString .= PHP_EOL;

            // Connect to the tenant to get overdue loan rows
            try {

                $tenantEntityManager = $this->getTenantEntityManager($tenantDbSchema);

                // Set the settings class to get data from the right DB
                $this->settings->setTenant($tenant, $tenantEntityManager);

                $senderName     = $tenantService->getCompanyName();
                $fromEmail      = $tenantService->getSenderEmail();
                $replyToEmail   = $tenantService->getReplyToEmail();
                $postmarkApiKey = $tenantService->getSetting('postmark_api_key');

                $overdueDays = $this->settings->getSettingValue('automate_email_overdue_days');
                if ($overdueDays == null || $overdueDays == 0) {
                    $resultString .= '    ... skipping : overdue reminders not activated'.PHP_EOL;
                    continue;
                }

                $resultString .= " ... finding items {$overdueDays} days overdue".PHP_EOL;

                try {

                    /** @var $loanRowRepo \AppBundle\Repository\LoanRowRepository */
                    $loanRowRepo = $tenantEntityManager->getRepository('AppBundle:LoanRow');

                    if ($overdueLoanRows = $loanRowRepo->getOverdueItems($overdueDays)) {

                        foreach ($overdueLoanRows AS $loanRow) {

                            /** @var $loanRow \AppBundle\Entity\LoanRow */
                            $loan    = $loanRow->getLoan();
                            $contact = $loan->getContact();

                            $resultString .= '  Loan: '.$loan->getId().' : '.$contact->getEmail(). PHP_EOL;
                            $resultString .= '  Due: '.$loanRow->getDueInAt()->format("Y-m-d").PHP_EOL;

                            try {
                                $toEmail = $contact->getEmail();
                                $client = new PostmarkClient($postmarkApiKey);

                                // Save and switch locale for sending the email
                                $sessionLocale = $this->container->get('translator')->getLocale();
                                $this->container->get('translator')->setLocale($contact->getLocale());

                                $message = $this->twig->render(
                                    'emails/overdue_reminder.html.twig',
                                    array(
                                        'loanId'   => $loan->getId(),
                                        'loanRows' => $loan->getLoanRows(),
                                        'tenant'   => $tenant
                                    )
                                );

                                $subject = $this->container->get('translator')->trans('le_email.overdue.subject',
                                    ['loanId' => $loan->getId()],
                                    'emails', $contact->getLocale()
                                );

                                $client->sendEmail(
                                    "{$senderName} <{$fromEmail}>",
                                    $toEmail,
                                    $subject,
                                    $message,
                                    null,
                                    null,
                                    true,
                                    $replyToEmail
                                );

                                $note = new Note();
                                $note->setContact($contact);
                                $note->setText("Automation : sent overdue email");
                                $tenantEntityManager->persist($note);

                                // Revert locale for the UI
                                $this->container->get('translator')->setLocale($sessionLocale);

                            } catch (\Exception $generalException) {
                                $resultString .= "ERROR: Failed to send email : " . PHP_EOL . $generalException->getMessage();
                            }

                        }
                    }

                } catch(\PDOException $ex) {
                    $resultString .= "ERROR: Failed to query" . PHP_EOL;
                }

                $tenantEntityManager->flush();
                $tenantEntityManager->getConnection()->close();

            } catch(\PDOException $ex) {
                echo "ERROR: Couldn't connect to database {$tenantDbSchema}" . PHP_EOL;
            }

            $tenantConnection = null;

            $timeElapsed = number_format(microtime(true) - $startTime, 4);
            $resultString .= '  T: '.$timeElapsed.PHP_EOL;

        }

        $timeElapsed = number_format(microtime(true) - $startTime, 4);
        $resultString .= '  Total T: '.$timeElapsed.PHP_EOL;

        // And then finally send a log.
        $client = new PostmarkClient(getenv('SYMFONY__POSTMARK_API_KEY'));
        $client->sendEmail(
            "hello@lend-engine.com",
            'chris@lend-engine.com',
            "Overdue emails log / {$timeElapsed} sec.",
            nl2br($resultString)
        );

        return $resultString;

    }

    /**
     * @param $dbName
     * @return EntityManager
     * @throws \Doctrine\ORM\ORMException
     */
    private function getTenantEntityManager($dbName)
    {

        if ($url = getenv('RDS_URL')) {
            $dbparts = parse_url($url);
            $server   = $dbparts['host'];
            $username = $dbparts['user'];
            $password = $dbparts['pass'];
        } else {
            $server = '127.0.0.1';
            $username = getenv('DEV_DB_USER');
            $password = getenv('DEV_DB_PASS');
        }

        $conn = array(
            'driver'   => 'pdo_mysql',
            'port'     => 3306,
            'host'     => $server,
            'user'     => $username,
            'password' => $password,
            'dbname'   => $dbName
        );

        $em = EntityManager::create(
            $conn,
            $this->em->getConfiguration(),
            $this->em->getEventManager()
        );

        return $em;
    }

}