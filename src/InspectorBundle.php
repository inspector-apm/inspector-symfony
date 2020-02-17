<?php


namespace Inspector\Symfony;


use Inspector\Symfony\DependencyInjection\InspectorExtension;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Contracts\EventDispatcher\Event;

class InspectorBundle extends Bundle
{
    /*private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function boot()
    {
        parent::boot();

        $this->eventDispatcher->addListener('', function (Event $event) {
            //
        });
    }*/

    public function getContainerExtension()
    {
        if ($this->extension === null) {
            $this->extension = new InspectorExtension();
        }

        return $this->extension;
    }
}
