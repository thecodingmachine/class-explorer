<?php


namespace TheCodingMachine\ClassExplorer\Events;


use Symfony\Component\EventDispatcher\Event;

class IdentifierNotFoundEvent extends Event
{
    const NAME = 'classes.explorers.identifier_not_found';

    /**
     * @var string
     */
    private $identifier;

    public function __construct(string $identifier)
    {

        $this->identifier = $identifier;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}