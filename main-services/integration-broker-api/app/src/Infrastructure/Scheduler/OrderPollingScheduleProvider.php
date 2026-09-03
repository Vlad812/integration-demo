<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use App\Application\Message\PollOrderStatusesMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('order_polling')]
final class OrderPollingScheduleProvider implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function __construct(
        #[Autowire(service: 'cache.scheduler_order_polling')]
        private CacheInterface $cache,
        private LockFactory $lockFactory,
        #[Autowire('%env(int:POLL_INTERVAL_SECONDS)%')]
        private int $intervalSeconds,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->add(RecurringMessage::every(
                sprintf('%d seconds', max(1, $this->intervalSeconds)),
                new PollOrderStatusesMessage(),
            ))
            ->stateful($this->cache)
            ->lock($this->lockFactory->createLock('scheduler-order-polling'))
            ->processOnlyLastMissedRun(true);
    }
}
