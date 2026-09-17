<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/src/BlackjackGame.php';
BlackjackGame::bootstrap();

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}
$csrf = (string) $_SESSION['csrf'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071413">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <title>21 BCN — Blackjack Barcelona</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.21.16/dist/css/uikit.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/v3.css">
</head>
<body>
<div class="bcn-backdrop" aria-hidden="true">
    <div class="bcn-grid"></div>
    <div class="bcn-glow bcn-glow--one"></div>
    <div class="bcn-glow bcn-glow--two"></div>
</div>

<main class="game-shell uk-container uk-container-expand">
    <header class="topbar glass-panel">
        <a class="brand" href="#" aria-label="21 BCN">
            <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
            <span class="brand-copy"><strong>21 BCN</strong><small>BLACKJACK / BARCELONA</small></span>
        </a>

        <div class="topbar-actions">
            <div class="balance-pill">
                <span>Saldo</span>
                <strong id="balance">250 €</strong>
            </div>
            <button class="icon-button" id="rulesButton" type="button" uk-toggle="target: #rules-modal" aria-label="Cómo jugar">
                <span uk-icon="icon: question; ratio: .9"></span>
            </button>
        </div>
    </header>

    <section class="game-layout">
        <aside class="side-panel glass-panel bet-panel">
            <div>
                <p class="eyebrow">APUESTA POR MANO</p>
                <div class="bet-control">
                    <button id="betMinus" class="bet-step" type="button" aria-label="Reducir apuesta">−</button>
                    <div><strong id="betValue">25 €</strong><span>antes de repartir</span></div>
                    <button id="betPlus" class="bet-step" type="button" aria-label="Aumentar apuesta">+</button>
                </div>

                <input id="betRange" class="bet-range" type="range" min="5" max="100" step="5" value="25" aria-label="Cantidad de apuesta">

                <div class="bet-presets" aria-label="Apuestas rápidas">
                    <button type="button" data-bet="5">5 €</button>
                    <button type="button" data-bet="10">10 €</button>
                    <button type="button" data-bet="25">25 €</button>
                    <button type="button" data-bet="50">50 €</button>
                    <button type="button" data-bet="100">100 €</button>
                </div>
            </div>

            <div class="city-note">
                <span class="city-note-index">BCN / 52</span>
                <p>Una baraja.<br>Una ciudad entera.</p>
                <small>Cada carta interpreta un lugar, textura o detalle de Barcelona.</small>
            </div>

            <div class="side-footer">
                <span>Sin registro</span>
                <span>Sin base de datos</span>
            </div>
        </aside>

        <section class="table-panel glass-panel" aria-live="polite">
            <div class="table-orbit table-orbit--one" aria-hidden="true"></div>
            <div class="table-orbit table-orbit--two" aria-hidden="true"></div>
            <div class="bcn-skyline" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>

            <div class="hand-zone dealer-zone">
                <div class="hand-heading">
                    <div>
                        <p class="eyebrow">CRUPIER</p>
                        <h2 id="dealerScore">—</h2>
                    </div>
                    <span class="status-dot"><i></i> CASA BCN</span>
                </div>
                <div class="cards-row" id="dealerCards"></div>
            </div>

            <div class="table-center">
                <div class="center-emblem" aria-hidden="true"><span>21</span><small>BCN</small></div>
                <div class="message-wrap">
                    <p id="gameMessage">Elige cuánto quieres apostar y reparte cuando quieras.</p>
                    <span id="resultBadge" class="result-badge" hidden></span>
                </div>
            </div>

            <div class="hand-zone player-zone">
                <div class="hand-heading">
                    <div>
                        <p class="eyebrow">TU JUGADA</p>
                        <h2 id="playerScore">—</h2>
                    </div>
                    <span class="status-dot status-dot--warm"><i></i> JUGADOR</span>
                </div>
                <div id="playerHands" class="player-hands"></div>
            </div>
        </section>

        <aside class="side-panel action-panel glass-panel">
            <div>
                <p class="eyebrow">ACCIONES</p>
                <div class="action-stack">
                    <button class="game-button game-button--primary" id="startButton" type="button">
                        <span>Repartir mano</span><span class="button-arrow" aria-hidden="true">→</span>
                    </button>
                    <button class="game-button" id="hitButton" type="button" disabled><span>Pedir carta</span><kbd>H</kbd></button>
                    <button class="game-button" id="standButton" type="button" disabled><span>Plantarse</span><kbd>S</kbd></button>
                    <button class="game-button" id="doubleButton" type="button" disabled>
                        <span><strong>Doblar apuesta</strong><small>+ apuesta · 1 carta</small></span><kbd>D</kbd>
                    </button>

                    <div id="splitWager" class="split-wager" hidden>
                        <div class="split-wager__head">
                            <div><span>Mano 1</span><strong id="splitFirstBet">25 €</strong><small>ya apostado</small></div>
                            <div><span>Mano 2</span><strong id="splitSecondBet">25 €</strong><small>elige importe</small></div>
                        </div>
                        <div class="split-wager__controls">
                            <button id="splitBetMinus" type="button" aria-label="Reducir apuesta de la segunda mano">−</button>
                            <input id="splitBetRange" type="range" min="5" max="100" step="5" value="25" aria-label="Apuesta de la segunda mano">
                            <button id="splitBetPlus" type="button" aria-label="Aumentar apuesta de la segunda mano">+</button>
                        </div>
                    </div>

                    <button class="game-button game-button--split" id="splitButton" type="button" disabled>
                        <span><strong>Dividir pareja</strong><small id="splitButtonHelp">2 cartas iguales → 2 manos</small></span><kbd>P</kbd>
                    </button>
                </div>

                <div class="action-help">
                    <p><b>Doblar</b> duplica la apuesta de esa mano, recibes una sola carta y te plantas.</p>
                    <p><b>Dividir</b>: la mano 1 conserva su apuesta y puedes elegir cuánto arriesgar en la mano 2 antes de separar las cartas.</p>
                </div>
            </div>

            <button class="text-button" id="resetButton" type="button">Reiniciar saldo</button>
        </aside>
    </section>

    <footer class="game-footer">
        <span>BARCELONA · 41.3874° N</span>
        <span>52 CARTAS · 52 REFERENCIAS</span>
        <span>V3 / 2026</span>
    </footer>
</main>

<div id="rules-modal" uk-modal>
    <div class="uk-modal-dialog uk-modal-body rules-modal glass-panel">
        <button class="uk-modal-close-default" type="button" uk-close></button>
        <p class="eyebrow">REGLAS RÁPIDAS</p>
        <h2>Acércate a 21 sin pasarte.</h2>
        <p>Las figuras valen 10 y el as vale 1 u 11. El crupier pide hasta 17. Un blackjack natural paga 3:2.</p>
        <p><strong>Doblar apuesta</strong>: duplicas la apuesta de la mano activa, recibes exactamente una carta y te plantas automáticamente.</p>
        <p><strong>Dividir pareja</strong>: si tus dos cartas iniciales tienen el mismo rango, se separan en dos manos. En 21 BCN la primera conserva su apuesta original y puedes elegir la apuesta de la segunda antes de confirmar el split.</p>
        <p class="uk-text-small uk-text-muted">El saldo es ficticio y solo vive en tu sesión del navegador.</p>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/uikit@3.21.16/dist/js/uikit.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/uikit@3.21.16/dist/js/uikit-icons.min.js"></script>
<script src="assets/js/game.js"></script>
</body>
</html>
