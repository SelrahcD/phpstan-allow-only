<?php

declare(strict_types=1);

class AnEntity
{
    /**
     * @allow-only-from AnAggregate
     * @allow-only-from AnotherAggregate
     */
    public function setSomethingOnlyFromAggregate()
    {
    }

    public function setSomething()
    {
    }

    public function act()
    {
        $this->setSomethingOnlyFromAggregate();
        $this->setSomething();
    }
}

class AnAggregate
{
    public function doSomething()
    {
        $entity = new AnEntity();
        $entity->setSomethingOnlyFromAggregate();
        $entity->setSomething();
    }
}

class NotTheAggregate
{
    /**
     * @var AnEntity
     */
    private $theEntity;

    /**
     * @var AnEntity|null
     */
    private $maybeTheEntity;

    /**
     * @var mixed&AnEntity
     */
    private $theEntityForSure;

    public function doSomethingItShouldntDo()
    {
        $entity = new AnEntity();
        $entity->setSomethingOnlyFromAggregate();
        $entity->setSomething();
        $this->theEntity->setSomethingOnlyFromAggregate();
        $this->theEntity->setSomething();
        $this->maybeTheEntity->setSomethingOnlyFromAggregate();
        $this->maybeTheEntity->setSomething();
        $this->theEntityForSure->setSomethingOnlyFromAggregate();
        $this->theEntityForSure->setSomething();
    }
}

$anEntityOutsideOfAggregate = new AnEntity();
$anEntityOutsideOfAggregate->setSomethingOnlyFromAggregate();
$anEntityOutsideOfAggregate->setSomething();
