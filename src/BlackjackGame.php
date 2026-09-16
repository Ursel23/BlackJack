<?php

declare(strict_types=1);

final class BlackjackGame
{
    private const STATE_VERSION = 2;
    private const STARTING_BALANCE = 250.0;
    private const MIN_BET = 5;
    private const MAX_BET = 100;
    private const MAX_HANDS = 2;

    public static function bootstrap(): void
    {
        if (
            !isset($_SESSION['blackjack']) ||
            !is_array($_SESSION['blackjack']) ||
            (int) ($_SESSION['blackjack']['version'] ?? 0) !== self::STATE_VERSION
        ) {
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

        $bet = self::normalizeBet($requestedBet, (float) $state['balance']);
        $state['baseBet'] = $bet;
        $state['balance'] -= $bet;
        $state['deck'] = self::buildDeck();
        shuffle($state['deck']);
        $state['dealer'] = [];
        $state['hands'] = [[
            'cards' => [],
            'bet' => $bet,
            'status' => 'playing',
            'result' => null,
            'wasSplit' => false,
        ]];
        $state['activeHand'] = 0;
        $state['status'] = 'playing';
        $state['result'] = null;
        $state['message'] = 'Mano repartida. Decide tu jugada.';

        $state['hands'][0]['cards'][] = self::draw($state);
        $state['dealer'][] = self::draw($state);
        $state['hands'][0]['cards'][] = self::draw($state);
        $state['dealer'][] = self::draw($state);

        $playerScore = self::score($state['hands'][0]['cards']);
        $dealerScore = self::score($state['dealer']);

        if ($playerScore === 21 || $dealerScore === 21) {
            if ($playerScore === 21 && $dealerScore === 21) {
                self::finishNatural($state, 'push', 'Blackjack para ambos. Empate.');
            } elseif ($playerScore === 21) {
                self::finishNatural($state, 'blackjack', 'Blackjack. Pago 3:2.');
            } else {
                self::finishNatural($state, 'loss', 'Blackjack del crupier.');
            }
        }

        return self::publicState();
    }

    public static function hit(): array
    {
        self::bootstrap();
        $state = &$_SESSION['blackjack'];
        $index = self::activeHandIndex($state);

        $state['hands'][$index]['cards'][] = self::draw($state);
        $score = self::score($state['hands'][$index]['cards']);

        if ($score > 21) {
            $state['hands'][$index]['status'] = 'bust';
            $state['hands'][$index]['result'] = 'loss';
            $state['message'] = 'La mano ' . ($index + 1) . ' se pasa de 21.';
            self::advanceOrSettle($state, $index);
        } elseif ($score === 21) {
            $state['hands'][$index]['status'] = 'stood';
            $state['message'] = '21 exactos en la mano ' . ($index + 1) . '.';
            self::advanceOrSettle($state, $index);
        } else {
            $state['message'] = 'Carta servida. Puedes pedir, plantarte o doblar si corresponde.';
        }

        return self::publicState();
    }

    public static function stand(): array
    {
        self::bootstrap();
        $state = &$_SESSION['blackjack'];
        $index = self::activeHandIndex($state);

        $state['hands'][$index]['status'] = 'stood';
        $state['message'] = 'Te plantas con ' . self::score($state['hands'][$index]['cards']) . '.';
        self::advanceOrSettle($state, $index);

        return self::publicState();
    }

    public static function double(): array
    {
        self::bootstrap();
        $state = &$_SESSION['blackjack'];
        $index = self::activeHandIndex($state);
        $hand = $state['hands'][$index];

        if (count($hand['cards']) !== 2) {
            throw new RuntimeException('Solo puedes doblar la apuesta con las dos cartas iniciales de esa mano.');
        }

        $bet = (int) $hand['bet'];
        if ((float) $state['balance'] < $bet) {
            throw new RuntimeException('No tienes saldo suficiente para doblar la apuesta.');
        }

        $state['balance'] -= $bet;
        $state['hands'][$index]['bet'] *= 2;
        $state['hands'][$index]['cards'][] = self::draw($state);
        $score = self::score($state['hands'][$index]['cards']);

        if ($score > 21) {
            $state['hands'][$index]['status'] = 'bust';
            $state['hands'][$index]['result'] = 'loss';
            $state['message'] = 'Doblas la apuesta, recibes una carta y te pasas de 21.';
        } else {
            $state['hands'][$index]['status'] = 'stood';
            $state['message'] = 'Apuesta doblada. Recibes una sola carta y la mano queda plantada.';
        }

        self::advanceOrSettle($state, $index);
        return self::publicState();
    }

    public static function split(): array
    {
        self::bootstrap();
        $state = &$_SESSION['blackjack'];
        $index = self::activeHandIndex($state);
        $hand = $state['hands'][$index];

        if (count($state['hands']) >= self::MAX_HANDS) {
            throw new RuntimeException('En esta versión se permite una única división por ronda.');
        }

        if (count($hand['cards']) !== 2 || ($hand['cards'][0]['rank'] ?? null) !== ($hand['cards'][1]['rank'] ?? null)) {
            throw new RuntimeException('Solo puedes dividir cuando las dos cartas iniciales tienen el mismo valor facial.');
        }

        $bet = (int) $hand['bet'];
        if ((float) $state['balance'] < $bet) {
            throw new RuntimeException('No tienes saldo suficiente para crear la segunda mano.');
        }

        $state['balance'] -= $bet;
        $leftCard = $hand['cards'][0];
        $rightCard = $hand['cards'][1];

        $state['hands'] = [
            [
                'cards' => [$leftCard, self::draw($state)],
                'bet' => $bet,
                'status' => 'playing',
                'result' => null,
                'wasSplit' => true,
            ],
            [
                'cards' => [$rightCard, self::draw($state)],
                'bet' => $bet,
                'status' => 'playing',
                'result' => null,
                'wasSplit' => true,
            ],
        ];
        $state['activeHand'] = 0;
        $state['message'] = 'Pareja dividida: ahora juegas dos manos independientes.';

        self::autoStandTwentyOne($state);
        return self::publicState();
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

        $hands = [];
        foreach ($state['hands'] as $index => $hand) {
            $hands[] = [
                'cards' => $hand['cards'],
                'score' => self::score($hand['cards']),
                'bet' => (int) $hand['bet'],
                'status' => (string) $hand['status'],
                'result' => $hand['result'],
                'wasSplit' => (bool) ($hand['wasSplit'] ?? false),
                'active' => $playing && $index === (int) $state['activeHand'] && $hand['status'] === 'playing',
            ];
        }

        $activeIndex = self::safeActiveIndex($state);
        $activeHand = $hands[$activeIndex] ?? null;
        $canAct = $playing && $activeHand !== null && $activeHand['status'] === 'playing';
        $canDouble = $canAct
            && count($activeHand['cards']) === 2
            && (float) $state['balance'] >= (int) $activeHand['bet'];
        $canSplit = $canAct
            && count($state['hands']) < self::MAX_HANDS
            && count($activeHand['cards']) === 2
            && ($activeHand['cards'][0]['rank'] ?? null) === ($activeHand['cards'][1]['rank'] ?? null)
            && (float) $state['balance'] >= (int) $activeHand['bet'];

        $maxBet = min(self::MAX_BET, (int) (floor(((float) $state['balance']) / 5) * 5));

        return [
            'balance' => (float) $state['balance'],
            'bet' => (int) $state['baseBet'],
            'totalWager' => array_sum(array_map(static fn (array $hand): int => (int) $hand['bet'], $state['hands'])),
            'status' => (string) $state['status'],
            'result' => $state['result'],
            'message' => (string) $state['message'],
            'hands' => $hands,
            'activeHand' => $activeIndex,
            'dealer' => $dealerCards,
            'dealerScore' => $playing ? self::visibleDealerScore($state['dealer']) : self::score($state['dealer']),
            'dealerScoreHidden' => $playing && count($state['dealer']) > 1,
            'canHit' => $canAct,
            'canStand' => $canAct,
            'canDouble' => $canDouble,
            'canSplit' => $canSplit,
            'minBet' => self::MIN_BET,
            'maxBet' => max(0, $maxBet),
            'canStart' => !$playing && (float) $state['balance'] >= self::MIN_BET,
        ];
    }

    private static function freshState(): array
    {
        return [
            'version' => self::STATE_VERSION,
            'balance' => self::STARTING_BALANCE,
            'baseBet' => 25,
            'status' => 'idle',
            'result' => null,
            'message' => 'Elige cuánto quieres apostar y reparte cuando quieras.',
            'deck' => [],
            'dealer' => [],
            'hands' => [],
            'activeHand' => 0,
        ];
    }

    private static function normalizeBet(int $bet, float $balance): int
    {
        if ($balance < self::MIN_BET) {
            throw new RuntimeException('No tienes saldo suficiente. Reinicia el saldo para seguir jugando.');
        }

        if ($bet < self::MIN_BET || $bet > self::MAX_BET || $bet % 5 !== 0) {
            throw new RuntimeException('La apuesta debe estar entre 5 € y 100 €, en pasos de 5 €.');
        }

        if ($bet > $balance) {
            throw new RuntimeException('La apuesta no puede superar tu saldo disponible.');
        }

        return $bet;
    }

    private static function activeHandIndex(array $state): int
    {
        if (($state['status'] ?? null) !== 'playing') {
            throw new RuntimeException('No hay ninguna mano activa.');
        }

        $index = self::safeActiveIndex($state);
        if (!isset($state['hands'][$index]) || ($state['hands'][$index]['status'] ?? null) !== 'playing') {
            throw new RuntimeException('No hay una mano jugable seleccionada.');
        }

        return $index;
    }

    private static function safeActiveIndex(array $state): int
    {
        $index = (int) ($state['activeHand'] ?? 0);
        if ($index < 0 || $index >= count($state['hands'])) {
            return 0;
        }
        return $index;
    }

    private static function advanceOrSettle(array &$state, int $fromIndex): void
    {
        for ($i = $fromIndex + 1, $count = count($state['hands']); $i < $count; $i++) {
            if (($state['hands'][$i]['status'] ?? null) === 'playing') {
                $state['activeHand'] = $i;
                $state['message'] .= ' Continúa con la mano ' . ($i + 1) . '.';
                return;
            }
        }

        self::dealerTurnAndSettle($state);
    }

    private static function autoStandTwentyOne(array &$state): void
    {
        foreach ($state['hands'] as &$hand) {
            if (($hand['status'] ?? null) === 'playing' && self::score($hand['cards']) === 21) {
                $hand['status'] = 'stood';
            }
        }
        unset($hand);

        foreach ($state['hands'] as $index => $hand) {
            if (($hand['status'] ?? null) === 'playing') {
                $state['activeHand'] = $index;
                return;
            }
        }

        self::dealerTurnAndSettle($state);
    }

    private static function dealerTurnAndSettle(array &$state): void
    {
        $hasLiveHand = false;
        foreach ($state['hands'] as $hand) {
            if (($hand['status'] ?? null) !== 'bust') {
                $hasLiveHand = true;
                break;
            }
        }

        if ($hasLiveHand) {
            while (self::score($state['dealer']) < 17) {
                $state['dealer'][] = self::draw($state);
            }
        }

        $dealerScore = self::score($state['dealer']);
        $results = [];

        foreach ($state['hands'] as &$hand) {
            if (($hand['status'] ?? null) === 'bust') {
                $hand['result'] = 'loss';
                $results[] = 'loss';
                continue;
            }

            $playerScore = self::score($hand['cards']);
            $result = 'loss';

            if ($dealerScore > 21 || $playerScore > $dealerScore) {
                $result = 'win';
                $state['balance'] += (int) $hand['bet'] * 2;
            } elseif ($playerScore === $dealerScore) {
                $result = 'push';
                $state['balance'] += (int) $hand['bet'];
            }

            $hand['status'] = 'finished';
            $hand['result'] = $result;
            $results[] = $result;
        }
        unset($hand);

        $state['status'] = 'finished';
        $state['result'] = self::aggregateResult($results);
        $state['message'] = self::resultMessage($results, $dealerScore);
    }

    private static function finishNatural(array &$state, string $result, string $message): void
    {
        $bet = (int) $state['hands'][0]['bet'];

        if ($result === 'blackjack') {
            $state['balance'] += $bet * 2.5;
        } elseif ($result === 'push') {
            $state['balance'] += $bet;
        }

        $state['hands'][0]['status'] = 'finished';
        $state['hands'][0]['result'] = $result;
        $state['status'] = 'finished';
        $state['result'] = $result;
        $state['message'] = $message;
    }

    private static function aggregateResult(array $results): string
    {
        $unique = array_values(array_unique($results));
        if (count($unique) === 1) {
            return $unique[0];
        }
        return 'mixed';
    }

    private static function resultMessage(array $results, int $dealerScore): string
    {
        if (count($results) === 1) {
            return match ($results[0]) {
                'win' => $dealerScore > 21 ? 'El crupier se pasa. La mano es tuya.' : 'Tu mano queda más cerca de 21. Victoria.',
                'push' => 'Misma puntuación. Empate.',
                default => 'El crupier gana esta mano.',
            };
        }

        $wins = count(array_filter($results, static fn (string $r): bool => $r === 'win'));
        $pushes = count(array_filter($results, static fn (string $r): bool => $r === 'push'));
        $losses = count($results) - $wins - $pushes;

        return sprintf('Ronda dividida: %d ganada(s), %d empate(s), %d perdida(s).', $wins, $pushes, $losses);
    }

    private static function draw(array &$state): array
    {
        if (empty($state['deck'])) {
            throw new RuntimeException('El mazo se ha quedado sin cartas.');
        }

        return array_pop($state['deck']);
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
            'spades-7' => 'Monument a Colom', 'spades-8' => 'Plaça Espanya', 'spades-9' => 'Les Tres Xemeneies',
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
            'hearts-10' => 'Poble-sec', 'hearts-J' => 'Barri Gòtic', 'hearts-Q' => 'Sarrià', 'hearts-K' => 'Barcelona de nit',
            'diamonds-A' => 'Panot Flor', 'diamonds-2' => 'Trencadís', 'diamonds-3' => 'Panot Gaudí',
            'diamonds-4' => 'Rosa de foc', 'diamonds-5' => 'Mercat Sant Antoni', 'diamonds-6' => 'La Boqueria',
            'diamonds-7' => 'Lletres de carrer', 'diamonds-8' => 'Rajola hidràulica', 'diamonds-9' => 'Persiana BCN',
            'diamonds-10' => 'Xamfrà', 'diamonds-J' => 'Superilla', 'diamonds-Q' => 'Mosaic mediterrani',
            'diamonds-K' => 'Panot Barcelona',
        ];
    }
}
