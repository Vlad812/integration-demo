<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Message\SendOrderToBrokerMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

final class BrokerOrderPublisher
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.broker')]
        private SenderInterface $brokerTransport,
    ) {
    }

    public function publish(SendOrderToBrokerMessage $message): void
    {
        $this->brokerTransport->send(new Envelope($message, [
            new AmqpStamp('send_order_to_broker'),
        ]));
    }
}
