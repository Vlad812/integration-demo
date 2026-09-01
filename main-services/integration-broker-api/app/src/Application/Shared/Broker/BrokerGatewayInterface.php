<?php

declare(strict_types=1);

namespace App\Application\Shared\Broker;

use App\Domain\Entity\Order;

interface BrokerGatewayInterface
{
    public function createOrder(Order $order): BrokerCreateOrderResult;
}
