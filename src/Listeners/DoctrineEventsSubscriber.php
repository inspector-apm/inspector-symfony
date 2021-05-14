<?php

namespace Inspector\Symfony\Bundle\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;
use Inspector\Inspector;

/**
 * @todo listen to a different connections
 * @todo Symfony can be used without Doctrine, this listener have to be optional
 */
class DoctrineEventsSubscriber implements EventSubscriber
{
    protected const SEGMENT_TYPE = 'doctrine';
    protected const LABEL = 'Doctrine Flush';

    /**
     * @var Inspector
     */
    protected $inspector;

    protected $segments = [];

    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

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
        $this->startSegment(self::LABEL);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->endSegment(self::LABEL);
    }


    /**
     * Workaround method, should be removed after
     * @link https://github.com/inspector-apm/inspector-php/issues/9
     */
    protected function startSegment(string $label): void
    {
        $segment = $this->inspector->startSegment(self::SEGMENT_TYPE, $label);

        $this->segments[$label] = $segment;
    }

    /**
     * Workaround method, should be removed after
     * @link https://github.com/inspector-apm/inspector-php/issues/9
     */
    protected function endSegment(string $label): void
    {
        if (!isset($this->segments[$label])) {
            return;
        }

        $this->segments[$label]->end();

        unset($this->segments[$label]);
    }
}
