<?php

declare(strict_types=1);

namespace Inspector\Symfony\Bundle\Inspectable\Twig;

use Inspector\Inspector;
use Inspector\Models\Segment;
use Twig\Extension\AbstractExtension;
use Twig\Profiler\NodeVisitor\ProfilerNodeVisitor;
use Twig\Profiler\Profile;
use SplObjectStorage;

final class InspectableTwigExtension extends AbstractExtension
{
    const SEGMENT_TYPE = 'twig';

    /**
     * @var Inspector
     */
    protected $inspector;

    /**
     * @var SplObjectStorage<object, Segment> The currently active spans
     */
    private $segments;

    public function __construct(Inspector $inspector)
    {
        $this->inspector = $inspector;
        $this->segments = new SplObjectStorage();
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

        $this->segments[$profile] = $this->inspector->startSegment(self::SEGMENT_TYPE, $this->getSpanDescription($profile));
    }

    /**
     * This method is called when the execution of a block, a macro or a
     * template is finished.
     *
     * @param Profile $profile The profiling data
     */
    public function leave(Profile $profile): void
    {
        if (!isset($this->segments[$profile])) {
            return;
        }

        $this->segments[$profile]->finish();

        unset($this->segments[$profile]);
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeVisitors(): array
    {
        return [
            new ProfilerNodeVisitor(self::class),
        ];
    }

    /**
     * Gets a short description for the segment.
     *
     * @param Profile $profile The profiling data
     */
    private function getSpanDescription(Profile $profile): string
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
