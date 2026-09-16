(function ($) {
    'use strict';

    const csrf = $('meta[name="csrf-token"]').attr('content');
    const pacing = {
        start: 1180,
        hit: 560,
        stand: 900,
        double: 900,
        split: 1080,
        'reset-bankroll': 280
    };

    let state = null;
    let selectedBet = 25;
    let selectedSplitBet = 25;
    let splitContextKey = '';
    let locked = false;
    let previousDealer = [];
    let previousHands = [];

    const suitClass = {
        spades: 'card--dark',
        clubs: 'card--dark',
        hearts: 'card--red',
        diamonds: 'card--red'
    };

    function api(action, extra) {
        if (locked) return $.Deferred().reject().promise();

        locked = true;
        setBusy(true);
        render();
        let failed = false;

        return $.ajax({
            url: 'api/game.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify($.extend({ action, csrf }, extra || {}))
        }).done(function (response) {
            if (!response.state) return;

            if (action === 'start' || action === 'reset-bankroll') {
                resetAnimationHistory();
                splitContextKey = '';
            }

            state = response.state;
            if (action === 'start' || action === 'reset-bankroll') {
                selectedBet = Number(state.bet || 25);
            }
        }).fail(function (xhr) {
            failed = true;
            const payload = xhr.responseJSON || {};
            if (payload.state) state = payload.state;
            UIkit.notification({
                message: payload.error || 'No se ha podido completar la jugada.',
                status: 'danger',
                pos: 'top-center'
            });
        }).always(function () {
            render();
            window.setTimeout(function () {
                locked = false;
                setBusy(false);
                render();
            }, failed ? 180 : (pacing[action] || 420));
        });
    }

    function loadState() {
        $.getJSON('api/game.php').done(function (response) {
            state = response.state;
            selectedBet = Number(state.bet || 25);
            clampSelectedBet();
            resetAnimationHistory();
            render();
        }).fail(function () {
            UIkit.notification({ message: 'No se ha podido cargar la partida.', status: 'danger', pos: 'top-center' });
        });
    }

    function resetAnimationHistory() {
        previousDealer = [];
        previousHands = [];
    }

    function cardIdentity(card) {
        if (!card) return '';
        if (card.hidden) return 'hidden:' + (card.asset || 'back');
        return [card.rank, card.suit, card.variant, card.asset].join(':');
    }

    function cardMarkup(card, index, options) {
        options = options || {};
        const animate = Boolean(options.animate);
        const reveal = Boolean(options.reveal);
        const delay = Number(options.delay || 0);
        const animationClass = animate ? (reveal ? ' card-reveal' : ' card-enter') : '';
        const animationStyle = animate ? ' style="--delay:' + delay + 'ms"' : '';

        if (card.hidden) {
            const backAsset = escapeAttr(card.asset || 'assets/cards/back.png');
            return '<article class="playing-card card-back' + animationClass + '"' + animationStyle + ' aria-label="Carta oculta">' +
                '<div class="card-back-grid"><span>21</span><small>BCN</small></div>' +
                '<img class="card-asset" src="' + backAsset + '" alt="" draggable="false" onerror="this.hidden=true">' +
                '</article>';
        }

        const variant = Number(card.variant || 1);
        const cls = suitClass[card.suit] || 'card--dark';
        const asset = escapeAttr(card.asset || ('assets/cards/' + card.suit + '-' + card.rank + '.png'));
        const motifClass = 'motif-' + (((variant - 1) % 8) + 1);

        return '<article class="playing-card ' + cls + ' card-city ' + motifClass + animationClass + '"' + animationStyle +
            ' aria-label="' + escapeAttr(card.rank + ' de ' + card.suitLabel + ', ' + card.motif) + '">' +
            '<div class="card-corner card-corner--top"><strong>' + escapeHtml(card.rank) + '</strong><span>' + escapeHtml(card.symbol) + '</span></div>' +
            '<div class="card-fallback-art" aria-hidden="true"><span>' + escapeHtml(card.symbol) + '</span><i></i></div>' +
            '<div class="card-motif"><span>BCN · ' + String(variant).padStart(2, '0') + '</span><strong>' + escapeHtml(card.motif) + '</strong></div>' +
            '<div class="card-corner card-corner--bottom"><strong>' + escapeHtml(card.rank) + '</strong><span>' + escapeHtml(card.symbol) + '</span></div>' +
            '<img class="card-asset" src="' + asset + '" alt="" draggable="false" onerror="this.hidden=true">' +
            '</article>';
    }

    function renderDealer(cards) {
        const $target = $('#dealerCards');
        cards = cards || [];
        const signature = JSON.stringify(cards);
        if ($target.data('signature') === signature) return;

        const old = previousDealer;
        const firstDeal = old.length === 0;
        const html = cards.map(function (card, index) {
            const oldCard = old[index];
            const changed = !oldCard || cardIdentity(oldCard) !== cardIdentity(card);
            const reveal = Boolean(oldCard && oldCard.hidden && !card.hidden);
            const delay = firstDeal ? 220 + (index * 440) : (changed ? 120 + (index * 120) : 0);
            return cardMarkup(card, index, { animate: changed, reveal, delay });
        }).join('');

        previousDealer = JSON.parse(JSON.stringify(cards));
        $target.data('signature', signature).html(html);
    }

    function renderHands() {
        const $target = $('#playerHands');
        const hands = state.hands || [];
        const signature = JSON.stringify(hands);
        if ($target.data('signature') === signature) return;

        if (!hands.length) {
            previousHands = [];
            $target.data('signature', signature).empty();
            return;
        }

        const splitJustHappened = previousHands.length === 1 && hands.length === 2;
        const firstDeal = previousHands.length === 0;
        const resultLabels = { blackjack: 'BLACKJACK', win: 'GANADA', loss: 'PERDIDA', push: 'EMPATE' };

        const html = hands.map(function (hand, handIndex) {
            const previousCards = (previousHands[handIndex] && previousHands[handIndex].cards) || [];
            const result = hand.result
                ? '<span class="hand-result hand-result--' + hand.result + '">' + (resultLabels[hand.result] || hand.result) + '</span>'
                : '';
            const active = hand.active ? ' is-active' : '';

            const cards = (hand.cards || []).map(function (card, cardIndex) {
                const oldCard = previousCards[cardIndex];
                const changed = !oldCard || cardIdentity(oldCard) !== cardIdentity(card);
                let delay = changed ? 80 : 0;

                if (firstDeal) delay = cardIndex * 440;
                if (splitJustHappened) delay = (handIndex * 260) + (cardIndex * 220);

                return cardMarkup(card, cardIndex, { animate: changed || splitJustHappened, delay });
            }).join('');

            return '<section class="player-hand' + active + '" data-hand="' + handIndex + '">' +
                '<div class="player-hand-meta"><span>MANO ' + (handIndex + 1) + (hand.active ? ' · EN JUEGO' : '') + '</span>' +
                '<strong>' + hand.score + '</strong><small>' + formatMoney(hand.bet) + '</small>' + result + '</div>' +
                '<div class="cards-row player-cards">' + cards + '</div>' +
                '</section>';
        }).join('');

        previousHands = JSON.parse(JSON.stringify(hands));
        $target.data('signature', signature).html(html);
    }

    function render() {
        if (!state) return;

        clampSelectedBet();
        syncSplitBet();

        $('#balance').text(formatMoney(state.balance));
        $('#betValue').text(formatMoney(selectedBet));
        $('#betRange').val(selectedBet).attr('max', Math.max(5, Number(state.maxBet || 5)));
        $('#dealerScore').text((state.dealer || []).length ? state.dealerScore + (state.dealerScoreHidden ? ' +' : '') : '—');
        $('#gameMessage').text(state.message);

        const activeHand = (state.hands || [])[Number(state.activeHand || 0)];
        if ((state.hands || []).length > 1) {
            $('#playerScore').text(activeHand ? 'Mano ' + (Number(state.activeHand) + 1) + ' · ' + activeHand.score : '—');
        } else {
            $('#playerScore').text(activeHand ? activeHand.score : '—');
        }

        renderDealer(state.dealer || []);
        renderHands();

        const playing = state.status === 'playing';
        setButtonState('#hitButton', !state.canHit || locked, state.canHit ? '' : 'Solo disponible durante una mano activa.');
        setButtonState('#standButton', !state.canStand || locked, state.canStand ? '' : 'Solo disponible durante una mano activa.');
        setButtonState('#doubleButton', !state.canDouble || locked, state.canDouble ? '' : 'Doblar requiere dos cartas iniciales y saldo para igualar la apuesta.');
        setButtonState('#splitButton', !state.canSplit || locked, splitButtonTitle());

        const maxBet = Number(state.maxBet || 0);
        const canStartNow = Boolean(state.canStart) && selectedBet >= 5 && selectedBet <= maxBet && !locked;
        setButtonState('#startButton', !canStartNow, canStartNow ? '' : 'Ajusta una apuesta válida para repartir.');
        $('#startButton > span:first').text(state.status === 'finished' ? 'Repartir otra mano' : 'Repartir mano');

        $('#betMinus, #betPlus, #betRange').prop('disabled', playing || locked || !state.canStart);
        $('.bet-presets button').each(function () {
            const value = Number($(this).data('bet'));
            $(this).prop('disabled', playing || locked || !state.canStart || value > maxBet)
                .toggleClass('is-selected', value === selectedBet);
        });

        renderSplitWager(activeHand);
        renderResultBadge();
        $('.table-panel').attr('data-status', state.result || state.status);
    }

    function splitButtonTitle() {
        if (state.canSplit) return '';
        if (state.splitPairDetected && Number(state.maxSplitBet || 0) < 5) return 'Tienes pareja, pero no queda saldo suficiente para abrir otra mano.';
        return 'Dividir requiere dos cartas iniciales del mismo rango.';
    }

    function renderSplitWager(activeHand) {
        const show = Boolean(state.canSplit && activeHand);
        $('#splitWager').prop('hidden', !show);
        if (!show) return;

        const max = Math.max(5, Number(state.maxSplitBet || 5));
        $('#splitFirstBet').text(formatMoney(activeHand.bet));
        $('#splitSecondBet').text(formatMoney(selectedSplitBet));
        $('#splitBetRange').attr('max', max).val(selectedSplitBet).prop('disabled', locked);
        $('#splitBetMinus').prop('disabled', locked || selectedSplitBet <= 5);
        $('#splitBetPlus').prop('disabled', locked || selectedSplitBet >= max);
        $('#splitButtonHelp').text('Mano 2: ' + formatMoney(selectedSplitBet));
    }

    function syncSplitBet() {
        if (!state.canSplit) {
            splitContextKey = '';
            return;
        }

        const active = (state.hands || [])[Number(state.activeHand || 0)];
        const context = active
            ? (active.cards || []).map(cardIdentity).join('|') + ':' + active.bet + ':' + state.maxSplitBet
            : '';

        if (context !== splitContextKey) {
            splitContextKey = context;
            selectedSplitBet = Number(state.suggestedSplitBet || active.bet || 5);
        }

        const max = Number(state.maxSplitBet || 0);
        if (max >= 5) {
            selectedSplitBet = Math.max(5, Math.min(max, selectedSplitBet));
            selectedSplitBet = Math.round(selectedSplitBet / 5) * 5;
        }
    }

    function setSplitBet(value) {
        if (!state || !state.canSplit || locked) return;
        const max = Number(state.maxSplitBet || 0);
        if (max < 5) return;
        selectedSplitBet = Math.max(5, Math.min(max, Number(value)));
        selectedSplitBet = Math.round(selectedSplitBet / 5) * 5;
        render();
    }

    function renderResultBadge() {
        const $badge = $('#resultBadge');
        if (state.result) {
            const labels = { blackjack: 'BLACKJACK', win: 'VICTORIA', loss: 'DERROTA', push: 'EMPATE', mixed: 'RONDA MIXTA' };
            $badge.text(labels[state.result] || String(state.result).toUpperCase())
                .attr('class', 'result-badge result-badge--' + state.result)
                .prop('hidden', false);
        } else {
            $badge.prop('hidden', true);
        }
    }

    function setButtonState(selector, disabled, title) {
        $(selector).prop('disabled', disabled)
            .attr('aria-disabled', disabled ? 'true' : 'false')
            .attr('title', title || '');
    }

    function setBusy(value) {
        $('.game-shell').toggleClass('is-busy', value);
    }

    function clampSelectedBet() {
        if (!state || state.status === 'playing') return;
        const max = Number(state.maxBet || 0);
        if (max < 5) return;
        selectedBet = Math.max(5, Math.min(max, Number(selectedBet || state.bet || 25)));
        selectedBet = Math.round(selectedBet / 5) * 5;
    }

    function setBet(value) {
        if (!state || state.status === 'playing' || locked) return;
        const max = Number(state.maxBet || 0);
        if (max < 5) return;
        selectedBet = Math.max(5, Math.min(max, Number(value)));
        selectedBet = Math.round(selectedBet / 5) * 5;
        render();
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('es-ES', {
            minimumFractionDigits: Number(value) % 1 === 0 ? 0 : 2,
            maximumFractionDigits: 2
        }).format(Number(value)) + ' €';
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    $(document).on('click', '#betMinus:not(:disabled)', function () { setBet(selectedBet - 5); });
    $(document).on('click', '#betPlus:not(:disabled)', function () { setBet(selectedBet + 5); });
    $(document).on('input change', '#betRange:not(:disabled)', function () { setBet($(this).val()); });
    $(document).on('click', '.bet-presets button:not(:disabled)', function () { setBet($(this).data('bet')); });

    $(document).on('click', '#splitBetMinus:not(:disabled)', function () { setSplitBet(selectedSplitBet - 5); });
    $(document).on('click', '#splitBetPlus:not(:disabled)', function () { setSplitBet(selectedSplitBet + 5); });
    $(document).on('input change', '#splitBetRange:not(:disabled)', function () { setSplitBet($(this).val()); });

    $(document).on('click', '#startButton:not(:disabled)', function () { api('start', { bet: selectedBet }); });
    $(document).on('click', '#hitButton:not(:disabled)', function () { api('hit'); });
    $(document).on('click', '#standButton:not(:disabled)', function () { api('stand'); });
    $(document).on('click', '#doubleButton:not(:disabled)', function () { api('double'); });
    $(document).on('click', '#splitButton:not(:disabled)', function () { api('split', { bet: selectedSplitBet }); });
    $(document).on('click', '#resetButton', function () {
        UIkit.modal.confirm('¿Reiniciar el saldo ficticio a 250 €?').then(function () {
            api('reset-bankroll');
        }, function () {});
    });

    $(document).on('keydown', function (event) {
        if (event.target.matches('input, textarea, button')) return;
        if (!state || locked) return;
        const key = event.key.toLowerCase();
        if (key === 'h' && state.canHit) api('hit');
        if (key === 's' && state.canStand) api('stand');
        if (key === 'd' && state.canDouble) api('double');
        if (key === 'p' && state.canSplit) api('split', { bet: selectedSplitBet });
    });

    loadState();
})(jQuery);
