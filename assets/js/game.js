(function ($) {
    'use strict';

    const csrf = $('meta[name="csrf-token"]').attr('content');
    let state = null;
    let selectedBet = 25;
    let locked = false;

    const suitClass = {
        spades: 'card--dark',
        clubs: 'card--dark',
        hearts: 'card--red',
        diamonds: 'card--red'
    };

    function api(action, extra) {
        if (locked) return;
        locked = true;
        setBusy(true);

        return $.ajax({
            url: 'api/game.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify($.extend({ action, csrf }, extra || {}))
        }).done(function (response) {
            if (response.state) {
                state = response.state;
                selectedBet = state.bet;
                render();
            }
        }).fail(function (xhr) {
            const payload = xhr.responseJSON || {};
            if (payload.state) {
                state = payload.state;
                render();
            }
            UIkit.notification({ message: payload.error || 'No se ha podido completar la jugada.', status: 'danger', pos: 'top-center' });
        }).always(function () {
            locked = false;
            setBusy(false);
        });
    }

    function loadState() {
        $.getJSON('api/game.php').done(function (response) {
            state = response.state;
            selectedBet = state.bet;
            render();
        }).fail(function () {
            UIkit.notification({ message: 'No se ha podido cargar la partida.', status: 'danger', pos: 'top-center' });
        });
    }

    function cardMarkup(card, index) {
        if (card.hidden) {
            return '<article class="playing-card card-back card-enter" style="--delay:' + (index * 70) + 'ms" aria-label="Carta oculta">' +
                '<div class="card-back-grid"><span>21</span><small>BCN</small></div>' +
                '</article>';
        }

        const variant = Number(card.variant || 1);
        const pattern = ((variant - 1) % 8) + 1;
        const x = 18 + ((variant * 17) % 64);
        const y = 16 + ((variant * 29) % 68);
        const rotation = -22 + ((variant * 11) % 44);
        const scale = 82 + ((variant * 7) % 30);
        const cls = suitClass[card.suit] || 'card--dark';

        return '<article class="playing-card ' + cls + ' card-enter pattern-' + pattern + '" ' +
            'style="--delay:' + (index * 70) + 'ms;--mx:' + x + '%;--my:' + y + '%;--mr:' + rotation + 'deg;--ms:' + scale + '%" ' +
            'aria-label="' + escapeHtml(card.rank + ' de ' + card.suitLabel + ', ' + card.motif) + '">' +
            '<div class="card-corner card-corner--top"><strong>' + escapeHtml(card.rank) + '</strong><span>' + card.symbol + '</span></div>' +
            '<div class="card-art" aria-hidden="true"><i></i><b>' + String(variant).padStart(2, '0') + '</b></div>' +
            '<div class="card-motif"><span>BCN</span><strong>' + escapeHtml(card.motif) + '</strong></div>' +
            '<div class="card-corner card-corner--bottom"><strong>' + escapeHtml(card.rank) + '</strong><span>' + card.symbol + '</span></div>' +
            '</article>';
    }

    function renderCards(target, cards) {
        const $target = $(target);
        const signature = JSON.stringify(cards);
        if ($target.data('signature') === signature) return;
        $target.data('signature', signature);
        $target.html(cards.map(cardMarkup).join(''));
    }

    function render() {
        if (!state) return;

        $('#balance').text(formatMoney(state.balance));
        $('#betValue').text(formatMoney(selectedBet));
        $('#playerScore').text(state.player.length ? state.playerScore : '—');
        $('#dealerScore').text(state.dealer.length ? state.dealerScore + (state.dealerScoreHidden ? ' +' : '') : '—');
        $('#gameMessage').text(state.message);

        renderCards('#dealerCards', state.dealer);
        renderCards('#playerCards', state.player);

        const playing = state.status === 'playing';
        $('#hitButton').prop('disabled', !state.canHit || locked);
        $('#standButton').prop('disabled', !state.canStand || locked);
        $('#doubleButton').prop('disabled', !state.canDouble || locked);
        $('#betMinus, #betPlus').prop('disabled', playing || locked);
        $('#startButton').prop('disabled', !state.canStart || locked)
            .find('span:first').text(state.status === 'finished' ? 'Otra mano' : 'Repartir');

        const $badge = $('#resultBadge');
        if (state.result) {
            const labels = { blackjack: 'BLACKJACK', win: 'VICTORIA', loss: 'DERROTA', push: 'EMPATE' };
            $badge.text(labels[state.result] || state.result.toUpperCase())
                .attr('class', 'result-badge result-badge--' + state.result)
                .prop('hidden', false);
        } else {
            $badge.prop('hidden', true);
        }

        $('.table-panel').attr('data-status', state.result || state.status);
    }

    function setBusy(value) {
        $('.game-shell').toggleClass('is-busy', value);
    }

    function adjustBet(delta) {
        if (!state || state.status === 'playing') return;
        const ceiling = Math.min(100, state.balance);
        selectedBet = Math.max(5, Math.min(ceiling, selectedBet + delta));
        selectedBet = Math.round(selectedBet / 5) * 5;
        $('#betValue').text(formatMoney(selectedBet));
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

    $('#betMinus').on('click', function () { adjustBet(-5); });
    $('#betPlus').on('click', function () { adjustBet(5); });
    $('#startButton').on('click', function () { api('start', { bet: selectedBet }); });
    $('#hitButton').on('click', function () { api('hit'); });
    $('#standButton').on('click', function () { api('stand'); });
    $('#doubleButton').on('click', function () { api('double'); });
    $('#resetButton').on('click', function () {
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
    });

    loadState();
})(jQuery);
