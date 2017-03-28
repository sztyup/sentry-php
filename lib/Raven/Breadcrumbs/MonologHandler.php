<?php

namespace Raven\Breadcrumbs;

use \Monolog\Logger;

class MonologHandler extends \Monolog\Handler\AbstractProcessingHandler
{
    /**
     * Translates Monolog log levels to Raven log levels.
     */
    protected $logLevels = array(
        Logger::DEBUG     => \Raven\Client::DEBUG,
        Logger::INFO      => \Raven\Client::INFO,
        Logger::NOTICE    => \Raven\Client::INFO,
        Logger::WARNING   => \Raven\Client::WARNING,
        Logger::ERROR     => \Raven\Client::ERROR,
        Logger::CRITICAL  => \Raven\Client::FATAL,
        Logger::ALERT     => \Raven\Client::FATAL,
        Logger::EMERGENCY => \Raven\Client::FATAL,
    );

    protected $excMatch = '/^exception \'([^\']+)\' with message \'(.+)\' in .+$/s';

    /**
     * @var \Raven\Client the client object that sends the message to the server
     */
    protected $ravenClient;

    /**
     * @param \Raven\Client $ravenClient
     * @param int           $level  The minimum logging level at which this handler will be triggered
     * @param bool          $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(\Raven\Client $ravenClient, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->ravenClient = $ravenClient;
    }

    /**
     * @param string $message
     * @return array|null
     */
    protected function parseException($message)
    {
        if (preg_match($this->excMatch, $message, $matches)) {
            return array($matches[1], $matches[2]);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        // sentry uses the 'nobreadcrumb' attribute to skip reporting
        if (!empty($record['context']['nobreadcrumb'])) {
            return;
        }

        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Exception) {
            /**
             * @var \Exception $exc
             */
            $exc = $record['context']['exception'];
            $crumb = array(
                'type' => 'error',
                'level' => $this->logLevels[$record['level']],
                'category' => $record['channel'],
                'data' => array(
                    'type' => get_class($exc),
                    'value' => $exc->getMessage(),
                ),
            );
        } else {
            // TODO(dcramer): parse exceptions out of messages and format as above
            if ($error = $this->parseException($record['message'])) {
                $crumb = array(
                    'type' => 'error',
                    'level' => $this->logLevels[$record['level']],
                    'category' => $record['channel'],
                    'data' => array(
                        'type' => $error[0],
                        'value' => $error[1],
                    ),
                );
            } else {
                $crumb = array(
                    'level' => $this->logLevels[$record['level']],
                    'category' => $record['channel'],
                    'message' => $record['message'],
                );
            }
        }

        $this->ravenClient->breadcrumbs->record($crumb);
    }
}
