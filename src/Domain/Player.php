<?php
declare(strict_types=1);

namespace App\Domain;

final class Player
{

    /**
     * @var Game[]
     */
    private $games = [];

    /**
     * @allow-only-from App\Domain\Game
     */
    public function addGame(Game $game): void
    {
        $this->games[] = $game;
    }
}