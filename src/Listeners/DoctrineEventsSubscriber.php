<?php

namespace Inspector\Symfony\Bundle\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * @todo listen to a different connections
 * @todo Symfony can be used without Doctrine, this listener have to be optional
 */
class DoctrineEventsSubscriber implements EventSubscriber
{
    // this method can only return the event names; you cannot define a
    // custom method name to execute when each event triggers
    public function getSubscribedEvents(): array
    {
        return [
            Events::preFlush,
            Events::postFlush,
        ];
    }

    // callback methods must be called exactly like the events they listen to;
    // they receive an argument of type LifecycleEventArgs, which gives you access
    // to both the entity object of the event and the entity manager itself

    public function preFlush(PreFlushEventArgs $args): void
    {
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
    }
}
