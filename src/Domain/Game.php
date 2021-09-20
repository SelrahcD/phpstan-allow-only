<?php
declare(strict_types=1);

namespace App\Domain;

final class Game
{
    /**
     * @var Player[]
     */
    private $players = [];

    public function enlistPlayer(Player $player): void
    {
        $this->players[] = $player;

        // This should not create a PHPStan error
        $player->addGame($this);
    }
}