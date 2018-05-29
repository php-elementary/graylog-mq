<?php

namespace elementary\logger\graylog\stomp;

use elementary\logger\traits\LoggerGetInterface;
use elementary\logger\traits\LoggerTrait;
use elementary\monitoring\Timer;
use Gelf\Logger;
use Gelf\Publisher;
use Gelf\PublisherInterface;
use Gelf\Transport\StompTransport;
use Gelf\Transport\TransportInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class GraylogStomp extends AbstractLogger implements LoggerGetInterface, LoggerAwareInterface
{
    use LoggerTrait;

    /**
     * @param string $facility
     * @param string $queue
     * @param string $host
     * @param string $port
     * @param string $login
     * @param string $password
     *
     * @throws \Exception
     * @throws \StompException
     */
    public function __construct($facility, $queue = 'graylog', $host = 'localhost', $port = '61613', $login = 'guest', $password = 'guest')
    {
        $connection= $this->getStompConnection($host, $port, $login, $password);
        $transport = $this->getGelfStompTransport($connection, $queue);
        $publisher = $this->getGelfPublisher($transport);
        $logger    = $this->getGelfLogger($publisher, $facility);

        $this->setLogger($logger);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $this->getLogger()->log($level, $message, $this->convertContext($context));
    }

    /**
     * @param array $context
     *
     * @return array
     */
    public function convertContext(array $context)
    {
        foreach ($context as $key=>$val) {
            if (is_array($val)) {
                $context[$key] = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
        }

        return $context;
    }

    /**
     * @param \Stomp $stomp
     * @param string $queue
     *
     * @return TransportInterface
     */
    public function getGelfStompTransport(\Stomp $stomp, string $queue)
    {
        return new StompTransport($stomp, $queue);
    }

    /**
     * @param string $host
     * @param string $port
     * @param string $login
     * @param string $password
     *
     * @return \Stomp
     * @throws \Exception
     * @throws \StompException
     */
    public function getStompConnection($host, $port, $login, $password)
    {
        try {
            Timer::me()->start(['group' => 'graylogstomp', 'operation' => 'connect']);
            $returnValue = new \Stomp('tcp://'. $host .':'. $port, $login, $password);
            Timer::me()->stop();
        } catch(\StompException $e) {
            Timer::me()->stop();

            throw $e;
        }

        return $returnValue;
    }

    /**
     * @param TransportInterface $transport
     *
     * @return PublisherInterface
     */
    public function getGelfPublisher(TransportInterface $transport)
    {
        $publisher = new Publisher();
        $publisher->addTransport($transport);

        return $publisher;
    }

    /**
     * @param PublisherInterface $publisher
     * @param string             $facility
     *
     * @return LoggerInterface
     */
    public function getGelfLogger(PublisherInterface $publisher, $facility)
    {
        return new Logger($publisher, $facility);
    }
}