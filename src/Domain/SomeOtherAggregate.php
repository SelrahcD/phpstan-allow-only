<?php
declare(strict_types=1);

namespace App\Domain;

final class SomeOtherAggregate
{

    public function doSomethingWithPlayers(): void
    {
        $player = new Player();
        $game = new Game();

        // This is expected to be picked-up by PHPStan
        $player->addGame($game);
    }
}