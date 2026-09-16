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
        if (locked) return $.Deferred().reject().promise();
        locked = true;
        setBusy(true);
        render();

        return $.ajax({
            url: 'api/game.php',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify($.extend({ action, csrf }, extra || {}))
        }).done(function (response) {
            if (response.state) {
                state = response.state;
                if (action === 'start' || action === 'reset-bankroll') {
                    selectedBet = Number(state.bet || 25);
                }
            }
        }).fail(function (xhr) {
            const payload = xhr.responseJSON || {};
            if (payload.state) state = payload.state;
            UIkit.notification({
                message: payload.error || 'No se ha podido completar la jugada.',
                status: 'danger',
                pos: 'top-center'
            });
        }).always(function () {
            locked = false;
            setBusy(false);
            render();
        });
    }

    function loadState() {
        $.getJSON('api/game.php').done(function (response) {
            state = response.state;
            selectedBet = Number(state.bet || 25);
            clampSelectedBet();
            render();
        }).fail(function () {
            UIkit.notification({ message: 'No se ha podido cargar la partida.', status: 'danger', pos: 'top-center' });
        });
    }

    function classifyMotif(motif) {
        const name = String(motif || '').toLowerCase();
        if (/sagrada/.test(name)) return 'sagrada';
        if (/eixample|xamfr|superilla|plaça catalunya/.test(name)) return 'grid';
        if (/tibidabo|montjuïc|bunkers|mirador/.test(name)) return 'hill';
        if (/glòries/.test(name)) return 'glories';
        if (/arc de triomf/.test(name)) return 'arch';
        if (/colom/.test(name)) return 'column';
        if (/xemeneies/.test(name)) return 'chimneys';
        if (/mediterrani|barceloneta|port vell|somorrostro/.test(name)) return 'sea';
        if (/panot|trencad|mosaic|rajola|drac|park güell/.test(name)) return 'mosaic';
        if (/batlló|pedrera|vicens|palau güell|modernisme/.test(name)) return 'facade';
        if (/música|sant pau|volta catalana|gòtic/.test(name)) return 'arches';
        if (/fanal|balcó|lletres|persiana|rambla|gràcia|born|raval|sant antoni|poble-sec|sarrià|boqueria|mercat/.test(name)) return 'street';
        return 'skyline';
    }

    function cityArt(card) {
        const type = classifyMotif(card.motif);
        const variant = Number(card.variant || 1);
        const shift = (variant * 7) % 18;
        const dot = 18 + ((variant * 13) % 64);
        let body = '';

        switch (type) {
            case 'sagrada':
                body = '<path d="M16 102V77L27 70V42L33 26L39 42V66H47V31L52 12L57 31V66H65V42L71 26L77 42V70L88 77V102"/>' +
                    '<path class="accent" d="M26 102V82H78V102M37 102V88M52 102V84M67 102V88"/>';
                break;
            case 'grid':
                body = '<path d="M20 20H80V100H20zM20 45H80M20 73H80M43 20V100M66 20V100"/>' +
                    '<path class="accent" d="M43 45l7-7h9l7 7v17l-7 7h-9l-7-7z"/>';
                break;
            case 'hill':
                body = '<path d="M8 94c16-31 32-44 48-38 13 5 21 18 36 38"/>' +
                    '<path class="accent" d="M48 57V34M41 42h14M45 34l3-9 3 9M22 90c9-10 20-15 31-15 13 0 24 5 34 15"/>';
                break;
            case 'glories':
                body = '<path d="M37 102V47c0-22 7-34 15-34s15 12 15 34v55z"/>' +
                    '<path class="accent" d="M40 45h24M40 58h24M40 71h24M40 84h24M47 20v80M57 20v80"/>';
                break;
            case 'arch':
                body = '<path d="M15 102V49h70v53M25 102V57h50v45M36 102V76c0-12 7-20 16-20s16 8 16 20v26"/>' +
                    '<path class="accent" d="M20 42h60M29 42V30h46v12M37 30V21h30v9"/>';
                break;
            case 'column':
                body = '<path d="M38 102h28M42 96h20M48 91V39h8v52M44 39h16M46 32h12"/>' +
                    '<path class="accent" d="M52 30V14M52 14l7 8M52 14l-7 8"/>';
                break;
            case 'chimneys':
                body = '<path d="M20 102V38h12v64M44 102V25h12v77M68 102V46h12v56"/>' +
                    '<path class="accent" d="M18 38h16M42 25h16M66 46h16M13 102h74"/>';
                break;
            case 'sea':
                body = '<path d="M8 72c12-10 22-10 34 0s22 10 34 0 22-10 34 0M8 84c12-10 22-10 34 0s22 10 34 0 22-10 34 0M8 96c12-10 22-10 34 0s22 10 34 0 22-10 34 0"/>' +
                    '<circle class="accent" cx="75" cy="32" r="14"/>';
                break;
            case 'mosaic':
                body = '<path d="M12 26l21-13 17 17 18-15 20 18-16 19 15 18-19 20-18-14-18 17-19-19 15-20z"/>' +
                    '<path class="accent" d="M33 13l5 29M50 30l-8 27M68 15l-9 33M28 54l27 4M72 52L55 73M32 93l10-36M68 90L55 73"/>';
                break;
            case 'facade':
                body = '<path d="M18 102V31c13-8 21 5 34-2 13-7 21-1 30 2v71z"/>' +
                    '<path class="accent" d="M27 45c8-8 16-8 24 0M58 45c8-8 14-8 20 0M27 67c8-8 16-8 24 0M58 67c8-8 14-8 20 0M24 91h52"/>';
                break;
            case 'arches':
                body = '<path d="M12 102V42h76v60M20 102V62c0-14 10-24 22-24s22 10 22 24v40M56 102V62c0-14 8-24 18-24s18 10 18 24"/>' +
                    '<path class="accent" d="M12 42h76M28 32h48M36 22h32"/>';
                break;
            case 'street':
                body = '<path d="M16 102V28h68v74M27 42h16v18H27zM57 42h16v18H57zM24 72h52M31 72v18M69 72v18"/>' +
                    '<path class="accent" d="M22 68h56M20 92h60M50 28V12M50 12l8 9M50 12l-8 9"/>';
                break;
            default:
                body = '<path d="M8 102h84M17 102V72h12v30M34 102V55h14v47M53 102V64h11v38M69 102V43h13v59"/>' +
                    '<path class="accent" d="M34 55l7-14 7 14M69 43l6-16 7 16M12 72h21M49 64h19"/>';
        }

        return '<svg class="bcn-art" viewBox="0 0 100 120" aria-hidden="true" style="--seed:' + shift + '">' +
            '<g>' + body + '</g>' +
            '<circle class="seed-dot" cx="' + dot + '" cy="' + (22 + shift) + '" r="2.2"/>' +
            '</svg>';
    }

    function cardMarkup(card, index) {
        if (card.hidden) {
            return '<article class="playing-card card-back card-enter" style="--delay:' + (index * 70) + 'ms" aria-label="Carta oculta">' +
                '<div class="card-back-grid"><span>21</span><small>BCN</small></div>' +
                '</article>';
        }

        const variant = Number(card.variant || 1);
        const cls = suitClass[card.suit] || 'card--dark';

        return '<article class="playing-card ' + cls + ' card-enter card-city" ' +
            'style="--delay:' + (index * 70) + 'ms;--variant:' + variant + '" ' +
            'aria-label="' + escapeHtml(card.rank + ' de ' + card.suitLabel + ', ' + card.motif) + '">' +
            '<div class="card-corner card-corner--top"><strong>' + escapeHtml(card.rank) + '</strong><span>' + card.symbol + '</span></div>' +
            '<div class="card-city-art">' + cityArt(card) + '<span class="city-suit">' + card.symbol + '</span></div>' +
            '<div class="card-motif"><span>BCN · ' + String(variant).padStart(2, '0') + '</span><strong>' + escapeHtml(card.motif) + '</strong></div>' +
            '<div class="card-corner card-corner--bottom"><strong>' + escapeHtml(card.rank) + '</strong><span>' + card.symbol + '</span></div>' +
            '</article>';
    }

    function renderCards(target, cards) {
        const $target = $(target);
        const signature = JSON.stringify(cards);
        if ($target.data('signature') === signature) return;
        $target.data('signature', signature);
        $target.html((cards || []).map(cardMarkup).join(''));
    }

    function renderHands() {
        const $target = $('#playerHands');
        const hands = state.hands || [];
        const signature = JSON.stringify(hands);
        if ($target.data('signature') === signature) return;
        $target.data('signature', signature);

        if (!hands.length) {
            $target.empty();
            return;
        }

        const html = hands.map(function (hand, handIndex) {
            const resultLabels = { blackjack: 'BLACKJACK', win: 'GANADA', loss: 'PERDIDA', push: 'EMPATE' };
            const result = hand.result ? '<span class="hand-result hand-result--' + hand.result + '">' + (resultLabels[hand.result] || hand.result) + '</span>' : '';
            const active = hand.active ? ' is-active' : '';
            return '<section class="player-hand' + active + '" data-hand="' + handIndex + '">' +
                '<div class="player-hand-meta"><span>MANO ' + (handIndex + 1) + (hand.active ? ' · EN JUEGO' : '') + '</span>' +
                '<strong>' + hand.score + '</strong><small>' + formatMoney(hand.bet) + '</small>' + result + '</div>' +
                '<div class="cards-row player-cards">' + (hand.cards || []).map(cardMarkup).join('') + '</div>' +
                '</section>';
        }).join('');

        $target.html(html);
    }

    function render() {
        if (!state) return;

        clampSelectedBet();
        $('#balance').text(formatMoney(state.balance));
        $('#betValue').text(formatMoney(selectedBet));
        $('#betRange').val(selectedBet).attr('max', Math.max(5, Number(state.maxBet || 5)));
        $('#dealerScore').text(state.dealer.length ? state.dealerScore + (state.dealerScoreHidden ? ' +' : '') : '—');
        $('#gameMessage').text(state.message);

        const activeHand = (state.hands || [])[Number(state.activeHand || 0)];
        if ((state.hands || []).length > 1) {
            $('#playerScore').text(activeHand ? 'Mano ' + (Number(state.activeHand) + 1) + ' · ' + activeHand.score : '—');
        } else {
            $('#playerScore').text(activeHand ? activeHand.score : '—');
        }

        renderCards('#dealerCards', state.dealer || []);
        renderHands();

        const playing = state.status === 'playing';
        setButtonState('#hitButton', !state.canHit || locked, state.canHit ? '' : 'Solo disponible durante una mano activa.');
        setButtonState('#standButton', !state.canStand || locked, state.canStand ? '' : 'Solo disponible durante una mano activa.');
        setButtonState('#doubleButton', !state.canDouble || locked, state.canDouble ? '' : 'Doblar requiere dos cartas iniciales y saldo para igualar la apuesta.');
        setButtonState('#splitButton', !state.canSplit || locked, state.canSplit ? '' : 'Dividir requiere dos cartas iniciales del mismo rango y saldo suficiente.');

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

        const $badge = $('#resultBadge');
        if (state.result) {
            const labels = { blackjack: 'BLACKJACK', win: 'VICTORIA', loss: 'DERROTA', push: 'EMPATE', mixed: 'RONDA MIXTA' };
            $badge.text(labels[state.result] || String(state.result).toUpperCase())
                .attr('class', 'result-badge result-badge--' + state.result)
                .prop('hidden', false);
        } else {
            $badge.prop('hidden', true);
        }

        $('.table-panel').attr('data-status', state.result || state.status);
    }

    function setButtonState(selector, disabled, title) {
        $(selector).prop('disabled', disabled).attr('aria-disabled', disabled ? 'true' : 'false').attr('title', title || '');
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

    function adjustBet(delta) {
        setBet(selectedBet + delta);
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

    $(document).on('click', '#betMinus:not(:disabled)', function () { adjustBet(-5); });
    $(document).on('click', '#betPlus:not(:disabled)', function () { adjustBet(5); });
    $(document).on('input change', '#betRange:not(:disabled)', function () { setBet($(this).val()); });
    $(document).on('click', '.bet-presets button:not(:disabled)', function () { setBet($(this).data('bet')); });
    $(document).on('click', '#startButton:not(:disabled)', function () { api('start', { bet: selectedBet }); });
    $(document).on('click', '#hitButton:not(:disabled)', function () { api('hit'); });
    $(document).on('click', '#standButton:not(:disabled)', function () { api('stand'); });
    $(document).on('click', '#doubleButton:not(:disabled)', function () { api('double'); });
    $(document).on('click', '#splitButton:not(:disabled)', function () { api('split'); });
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
        if (key === 'p' && state.canSplit) api('split');
    });

    loadState();
})(jQuery);
