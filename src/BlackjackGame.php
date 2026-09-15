<?php

declare(strict_types=1);

final class BlackjackGame
{
    private const STARTING_BALANCE = 250;
    private const MIN_BET = 5;
    private const MAX_BET = 100;

    public static function bootstrap(): void
    {
        if (!isset($_SESSION['blackjack']) || !is_array($_SESSION['blackjack'])) {
            $_SESSION['blackjack'] = self::freshState();
        }
    }

    public static function resetBankroll(): array
    {
        $_SESSION['blackjack'] = self::freshState();
        return self::publicState();
    }

    public static function start(int $requestedBet): array
    {
        self::bootstrap();
        $state = &$_SESSION['blackjack'];

        if (($state['status'] ?? 'idle') === 'playing') {
            throw new RuntimeException('Termina la mano actual antes de repartir de nuevo.');
        }

        $bet = self::normalizeBet($requestedBet, (int) $state['balance']);
        $state['bet'] = $bet;
        $state['balance'] -= $bet;
        $state['deck'] = self::buildDeck();
        shuffle($state['deck']);
        $state['player'] = [];
        $state['dealer'] = [];
        $state['status'] = 'playing';
        $state['result'] = null;
        $state['message'] = 'La Rambla ya está despierta. Juega tu mano.';

        $state['player'][] = self::draw($state);
        $state['dealer'][] = self::draw($state);
        $state['player'][] = self::draw($state);
        $state['dealer'][] = self::draw($state);

        $playerScore = self::score($state['player']);
        $dealerScore = self::score($state['dealer']);

        if ($playerScore === 21 || $dealerScore === 21) {
            if ($playerScore === 21 && $dealerScore === 21) {
                self::settle($state, 'push', 'Blackjack para ambos. Empate en el Eixample.');
            } elseif ($playerScore === 21) {
                self::settle($state, 'blackjack', 'Blackjack. Barcelona te sonríe.');
            } else {
                self::settle($state, 'loss', 'Blackjack del crupier. Esta vez gana la casa.');
            }
        }

        return self::publicState();
    }

    public static function hit(): array
    {
        self::bootstrap();
        $state = &$_SESSION['blackjack'];
        self::assertPlaying($state);

        $state['player'][] = self::draw($state);
        $score = self::score($state['player']);

        if ($score > 21) {
            self::settle($state, 'loss', 'Te has pasado. La noche sigue, la mano no.');
        } elseif ($score === 21) {
            return self::stand();
        } else {
            $state['message'] = 'Carta servida. Decide el siguiente movimiento.';
        }

        return self::publicState();
    }

    public static function stand(): array
    {
        self::bootstrap();
        $state = &$_SESSION['blackjack'];
        self::assertPlaying($state);

        while (self::score($state['dealer']) < 17) {
            $state['dealer'][] = self::draw($state);
        }

        $player = self::score($state['player']);
        $dealer = self::score($state['dealer']);

        if ($dealer > 21) {
            self::settle($state, 'win', 'El crupier se pasa. La mano es tuya.');
        } elseif ($player > $dealer) {
            self::settle($state, 'win', 'Más cerca de 21. Victoria para ti.');
        } elseif ($player < $dealer) {
            self::settle($state, 'loss', 'El crupier se queda más cerca de 21.');
        } else {
            self::settle($state, 'push', 'Misma puntuación. Empate.');
        }

        return self::publicState();
    }

    public static function double(): array
    {
        self::bootstrap();
        $state = &$_SESSION['blackjack'];
        self::assertPlaying($state);

        if (count($state['player']) !== 2) {
            throw new RuntimeException('Solo puedes doblar con las dos cartas iniciales.');
        }

        if ((int) $state['balance'] < (int) $state['bet']) {
            throw new RuntimeException('No tienes saldo suficiente para doblar.');
        }

        $state['balance'] -= $state['bet'];
        $state['bet'] *= 2;
        $state['player'][] = self::draw($state);

        if (self::score($state['player']) > 21) {
            self::settle($state, 'loss', 'Doblas, recibes una carta y te pasas de 21.');
            return self::publicState();
        }

        return self::stand();
    }

    public static function publicState(): array
    {
        self::bootstrap();
        $state = $_SESSION['blackjack'];
        $playing = ($state['status'] ?? 'idle') === 'playing';
        $dealerCards = $state['dealer'];

        if ($playing && count($dealerCards) > 1) {
            $dealerCards[1] = [
                'hidden' => true,
                'variant' => 53,
                'motif' => 'Reverso BCN',
            ];
        }

        return [
            'balance' => (float) $state['balance'],
            'bet' => (int) $state['bet'],
            'status' => (string) $state['status'],
            'result' => $state['result'],
            'message' => (string) $state['message'],
            'player' => $state['player'],
            'dealer' => $dealerCards,
            'playerScore' => self::score($state['player']),
            'dealerScore' => $playing ? self::visibleDealerScore($state['dealer']) : self::score($state['dealer']),
            'dealerScoreHidden' => $playing && count($state['dealer']) > 1,
            'canHit' => $playing,
            'canStand' => $playing,
            'canDouble' => $playing && count($state['player']) === 2 && (int) $state['balance'] >= (int) $state['bet'],
            'minBet' => self::MIN_BET,
            'maxBet' => min(self::MAX_BET, max(self::MIN_BET, (int) (floor(((float) $state['balance']) / 5) * 5))),
            'canStart' => !$playing && (float) $state['balance'] >= self::MIN_BET,
        ];
    }

    private static function freshState(): array
    {
        return [
            'balance' => self::STARTING_BALANCE,
            'bet' => 25,
            'status' => 'idle',
            'result' => null,
            'message' => 'Ajusta tu apuesta y reparte cuando quieras.',
            'deck' => [],
            'player' => [],
            'dealer' => [],
        ];
    }

    private static function normalizeBet(int $bet, int $balance): int
    {
        if ($balance < self::MIN_BET) {
            throw new RuntimeException('No tienes saldo suficiente. Reinicia el saldo para seguir jugando.');
        }

        $bet = max(self::MIN_BET, min(self::MAX_BET, $bet));
        if ($bet > $balance) {
            throw new RuntimeException('La apuesta no puede superar tu saldo.');
        }

        return (int) (floor($bet / 5) * 5);
    }

    private static function assertPlaying(array $state): void
    {
        if (($state['status'] ?? null) !== 'playing') {
            throw new RuntimeException('No hay ninguna mano activa.');
        }
    }

    private static function draw(array &$state): array
    {
        if (empty($state['deck'])) {
            throw new RuntimeException('El mazo se ha quedado sin cartas.');
        }

        return array_pop($state['deck']);
    }

    private static function settle(array &$state, string $result, string $message): void
    {
        $bet = (int) $state['bet'];

        if ($result === 'blackjack') {
            $state['balance'] += $bet * 2.5;
        } elseif ($result === 'win') {
            $state['balance'] += $bet * 2;
        } elseif ($result === 'push') {
            $state['balance'] += $bet;
        }

        $state['status'] = 'finished';
        $state['result'] = $result;
        $state['message'] = $message;
    }

    private static function score(array $hand): int
    {
        $total = 0;
        $aces = 0;

        foreach ($hand as $card) {
            if (!empty($card['hidden'])) {
                continue;
            }

            $rank = $card['rank'] ?? null;
            if ($rank === 'A') {
                $aces++;
                $total += 11;
            } elseif (in_array($rank, ['J', 'Q', 'K'], true)) {
                $total += 10;
            } else {
                $total += (int) $rank;
            }
        }

        while ($total > 21 && $aces > 0) {
            $total -= 10;
            $aces--;
        }

        return $total;
    }

    private static function visibleDealerScore(array $hand): int
    {
        return empty($hand) ? 0 : self::score([$hand[0]]);
    }

    private static function buildDeck(): array
    {
        $suits = [
            'spades' => ['symbol' => '♠', 'label' => 'Picas'],
            'clubs' => ['symbol' => '♣', 'label' => 'Tréboles'],
            'hearts' => ['symbol' => '♥', 'label' => 'Corazones'],
            'diamonds' => ['symbol' => '♦', 'label' => 'Diamantes'],
        ];
        $ranks = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
        $motifs = self::motifs();
        $deck = [];
        $variant = 1;

        foreach ($suits as $suit => $meta) {
            foreach ($ranks as $rank) {
                $key = $suit . '-' . $rank;
                $deck[] = [
                    'rank' => $rank,
                    'suit' => $suit,
                    'symbol' => $meta['symbol'],
                    'suitLabel' => $meta['label'],
                    'motif' => $motifs[$key],
                    'variant' => $variant++,
                ];
            }
        }

        return $deck;
    }

    private static function motifs(): array
    {
        return [
            'spades-A' => 'Eixample', 'spades-2' => 'Sagrada Família', 'spades-3' => 'Montjuïc',
            'spades-4' => 'Tibidabo', 'spades-5' => 'Torre Glòries', 'spades-6' => 'Arc de Triomf',
            'spades-7' => 'Colón', 'spades-8' => 'Plaça Espanya', 'spades-9' => 'Les Tres Xemeneies',
            'spades-10' => 'Bunkers del Carmel', 'spades-J' => 'Mirador de l’Alcalde',
            'spades-Q' => 'Plaça Catalunya', 'spades-K' => 'Skyline BCN',
            'clubs-A' => 'Park Güell', 'clubs-2' => 'Casa Batlló', 'clubs-3' => 'La Pedrera',
            'clubs-4' => 'Palau de la Música', 'clubs-5' => 'Recinte Sant Pau', 'clubs-6' => 'Casa Vicens',
            'clubs-7' => 'Palau Güell', 'clubs-8' => 'Fanal de Gràcia', 'clubs-9' => 'Balcó modernista',
            'clubs-10' => 'Drac de Gaudí', 'clubs-J' => 'Columnes Güell', 'clubs-Q' => 'Volta catalana',
            'clubs-K' => 'Modernisme',
            'hearts-A' => 'Mediterrani', 'hearts-2' => 'Barceloneta', 'hearts-3' => 'Port Vell',
            'hearts-4' => 'Somorrostro', 'hearts-5' => 'Rambla del Poblenou', 'hearts-6' => 'Gràcia',
            'hearts-7' => 'El Born', 'hearts-8' => 'Raval', 'hearts-9' => 'Sant Antoni',
            'hearts-10' => 'Poble-sec', 'hearts-J' => 'Gòtic', 'hearts-Q' => 'Sarrià', 'hearts-K' => 'Barcelona de nit',
            'diamonds-A' => 'Panot Flor', 'diamonds-2' => 'Trencadís', 'diamonds-3' => 'Panot Gaudí',
            'diamonds-4' => 'Rosa de foc', 'diamonds-5' => 'Mercat Sant Antoni', 'diamonds-6' => 'Boqueria',
            'diamonds-7' => 'Lletres de carrer', 'diamonds-8' => 'Rajola hidràulica', 'diamonds-9' => 'Persiana BCN',
            'diamonds-10' => 'Xamfrà', 'diamonds-J' => 'Superilla', 'diamonds-Q' => 'Mosaic mediterrani',
            'diamonds-K' => 'Panot Barcelona',
        ];
    }
}
