<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Inspectable\Twig;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Twig\Extension\AbstractExtension;
use Twig\Profiler\NodeVisitor\ProfilerNodeVisitor;
use Twig\Profiler\Profile;

final class InspectableTwigExtension extends AbstractExtension
{
    /**
     * @var Inspector
     */
    protected $inspector;

    /**
     * @var Segment[]
     */
    protected $segments = [];

    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * This method is called before the execution of a block, a macro or a
     * template.
     *
     * @param Profile $profile The profiling data
     */
    public function enter(Profile $profile): void
    {
        if (!$this->inspector->canAddSegments()) {
            return;
        }

        $profile->enter();

        $label = $this->getLabelTitle($profile);

        if ($profile->isRoot() || $profile->isTemplate()) {
            $this->segments[$profile->getTemplate()] = $this->inspector->startSegment('twig', $label);
        }
    }

    /**
     * This method is called when the execution of a block, a macro or a
     * template is finished.
     *
     * @param Profile $profile The profiling data
     */
    public function leave(Profile $profile): void
    {
        $profile->leave();

        if (!isset($this->segments[$profile->getTemplate()])) {
            return;
        }

        $label = $this->getLabelTitle($profile);

        $this->segments[$profile->getTemplate()]->addContext($label, [
            'template' => $profile->getTemplate(),
            'type' => $profile->getType(),
            'name' => $profile->getName(),
            'duration' => $profile->getDuration(),
            'memory_usage' => $profile->getMemoryUsage(),
            'peak_memory_usage' => $profile->getPeakMemoryUsage(),
        ]);

        $this->segments[$profile->getTemplate()]->end();

        unset($this->segments[$label]);
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeVisitors(): array
    {
        return [new ProfilerNodeVisitor(self::class)];
    }

    /**
     * Gets a short description for the segment.
     *
     * @param Profile $profile The profiling data
     */
    private function getLabelTitle(Profile $profile): string
    {
        switch (true) {
            case $profile->isRoot():
                return $profile->getName();

            case $profile->isTemplate():
                return $profile->getTemplate();

            default:
                return sprintf('%s::%s(%s)', $profile->getTemplate(), $profile->getType(), $profile->getName());
        }
    }
}
